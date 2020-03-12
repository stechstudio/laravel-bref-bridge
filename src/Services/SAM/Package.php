<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Services\SAM;

use Illuminate\Support\Facades\File;
use STS\Bref\Bridge\Events\SamPackageRequested;
use STS\Bref\Bridge\Package\Archive;
use Symfony\Component\Process\Process;
use function array_slice;
use function config;
use function count;
use function glob;
use function storage_path;

class Package
{
    public function handle(SamPackageRequested $event): void
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

        $this->rotatePackages();

        $event->info('Package at: '.$packagePath);
        $event->info('Running the SAM Package command, generating template file.');
        $process = new Process([
            'sam',
            'package',
            '--output-template-file',
            '.stack.yaml',
            '--s3-bucket',
            env('BREF_S3_BUCKET'),
        ], null, $_ENV);
        $process->setWorkingDirectory(base_path());
        $process->start();

        foreach ($process as $type => $data) {
            echo $data;
        }
        $event->info('Packaging Complete');
    }

    protected function rotatePackages(): void
    {
        $zipFiles = glob(storage_path('*.zip'));
        $keep     = config('bref.keep') + 1;
        $count    = count($zipFiles);
        if ($count > $keep) {
            $length = $count - $keep;
            File::delete(array_slice($zipFiles, 0, $length));
        }
    }
}
