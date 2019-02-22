<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Services;

use STS\Bref\Bridge\Events\LambdaPackageRequested;
use STS\Bref\Bridge\Package\Archive;
use Symfony\Component\Process\Process;

class PackageFunction
{
    public function handle(LambdaPackageRequested $event): void
    {
        if (env('BREF_S3_BUCKET', false) === false) {
            exit('You must provide the S3 bucket to upload the package to in the BREF_S3_BUCKET environment variable.');
        }

        $event->info('Creating Archive');
        $packagePath = Archive::laravel();
        if (file_exists(storage_path('latest.zip'))) {
            unlink(storage_path('latest.zip'));
        }
        symlink($packagePath, storage_path('latest.zip'));
        $event->info('Package at: ' . $packagePath);
        $event->info('Running the SAM Package command, generating template file.');
        $process = new Process([
            'sam',
            'package',
            '--output-template-file',
            '.stack.yaml',
            '--s3-bucket',
            env('BREF_S3_BUCKET'),
        ]);
        $process->setWorkingDirectory(base_path());
        $process->start();

        foreach ($process as $type => $data) {
            echo $data;
        }
        $event->info('Packaging Complete');
    }
}
