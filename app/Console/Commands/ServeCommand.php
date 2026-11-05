<?php

namespace App\Console\Commands;

use App\Support\UploadEnvironment;
use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'serve')]
class ServeCommand extends BaseServeCommand
{
    public function handle()
    {
        UploadEnvironment::ensureTempDirectory();

        return parent::handle();
    }

    /**
     * The framework builds `[php, -S, host:port, router]`; the ini flags have
     * to sit between the binary and `-S`, and they only work as command line
     * arguments because `upload_tmp_dir` is a system-only setting.
     *
     * @return array<int, string>
     */
    protected function serverCommand(): array
    {
        $command = parent::serverCommand();
        $overrides = [];

        foreach (UploadEnvironment::iniOverrides() as $key => $value) {
            $overrides[] = '-d';
            $overrides[] = $key.'='.$value;
        }

        return array_merge(array_slice($command, 0, 1), $overrides, array_slice($command, 1));
    }
}
