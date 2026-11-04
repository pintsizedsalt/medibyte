<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AvatarController extends Controller
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function store(Request $request)
    {
        $data = $request->validate([
            'image_data' => 'required|string',
        ]);

        $user = $request->user();

        $bytes = base64_decode($this->base64Payload($data['image_data']), true);

        if ($bytes === false) {
            throw ValidationException::withMessages([
                'image' => 'The photo could not be read. Please choose it again.',
            ]);
        }

        // The cropper always re-encodes to JPEG, so anything else is not a photo it made.
        if (! str_starts_with($bytes, "\xFF\xD8\xFF")) {
            throw ValidationException::withMessages([
                'image' => 'Please choose a JPG photo.',
            ]);
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'image' => 'The photo must be 2 MB or smaller.',
            ]);
        }

        $path = 'avatars/'.$user->id.'.jpg';

        Storage::disk('local')->put($path, $bytes);

        $user->forceFill(['avatar_path' => $path])->save();

        return response()->json(['avatar_url' => $user->avatar_url], 201);
    }

    public function show(Request $request)
    {
        $user = $request->user();

        abort_unless($user->avatar_path, 404);
        abort_unless(Storage::disk('local')->exists($user->avatar_path), 404);

        return response()->file(Storage::disk('local')->path($user->avatar_path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function base64Payload(string $value): string
    {
        $value = trim($value);

        if (! str_starts_with($value, 'data:')) {
            return $value;
        }

        $comma = strpos($value, ',');

        return $comma === false ? '' : substr($value, $comma + 1);
    }
}
