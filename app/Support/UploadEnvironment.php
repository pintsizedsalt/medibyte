<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UploadEnvironment
{
    /**
     * Bytes PHP keeps in memory before it spills a request body to disk.
     * Anything larger needs a writable temporary directory, and when that
     * directory is not writable PHP discards the whole body instead of
     * reporting it — which is why uploads fail for some machines only.
     */
    public const BODY_MEMORY_LIMIT = 16384;

    private const MAX_BODY = '64M';

    public static function tempDirectory(): string
    {
        return storage_path('framework/tmp');
    }

    public static function ensureTempDirectory(): string
    {
        $path = self::tempDirectory();

        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * Settings handed to the built-in server as `-d` flags, which is the only
     * way to set `upload_tmp_dir` because it cannot be changed at runtime.
     *
     * @return array<string, string>
     */
    public static function iniOverrides(): array
    {
        return [
            'upload_tmp_dir' => self::ensureTempDirectory(),
            'post_max_size' => self::MAX_BODY,
            'upload_max_filesize' => self::MAX_BODY,
        ];
    }

    /**
     * A body PHP could not spool arrives as a request with no fields at all,
     * which would otherwise surface as a confusing "field is required".
     */
    public static function assertBodyArrived(Request $request, string $field = 'file'): void
    {
        $declared = (int) $request->server('CONTENT_LENGTH', 0);

        $arrived = $declared <= self::BODY_MEMORY_LIMIT
            || $request->getContent() !== ''
            || $request->request->count() > 0
            || $request->files->count() > 0;

        if ($arrived) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'PHP discarded this upload: the request body is larger than post_max_size, or PHP cannot write its temporary files directory. Run the app with "composer dev" (or "php artisan serve" from this folder), which points upload_tmp_dir at storage/framework/tmp, then try again.',
        ]);
    }
}
