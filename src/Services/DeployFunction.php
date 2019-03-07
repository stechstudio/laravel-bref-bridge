<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Services;

use STS\Bref\Bridge\Events\DeploymentRequested;
use Symfony\Component\Process\Process;

class DeployFunction
{
    public function handle(DeploymentRequested $event): void
    {
        $process = new Process([
            'sam',
            'deploy',
            '--template-file',
            '.stack.yaml',
            '--capabilities',
            'CAPABILITY_NAMED_IAM',
            '--stack-name',
            config('bref.name'),
        ]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(600);
        $process->start();

        foreach ($process as $type => $data) {
            echo $data;
        }
    }
}
