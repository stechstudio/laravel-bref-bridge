<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Services\SAM;

use STS\Bref\Bridge\Events\SamUpdateRequested;
use Symfony\Component\Process\Process;

class Update
{
    public function handle(SamUpdateRequested $event): void
    {
        $this->runUpdate(config('bref.name'));
    }

    protected function runUpdate(string $functionName): void
    {
        $process = new Process([
            'aws',
            'lambda update-function-code',
            '--function-name',
            $functionName,
            '--zip-file',
            'fileb://storage/latest.zip',
        ]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(600);
        $process->start();

        foreach ($process as $type => $data) {
            echo $data;
        }
    }
}
