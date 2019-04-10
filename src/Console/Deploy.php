<?php declare(strict_types=1);

/**
 * User: bubba
 * Date: 2019-01-31
 * Time: 16:27
 */

namespace STS\Bref\Bridge\Console;

use Aws\CloudFormation\CloudFormationClient;
use Illuminate\Console\Command;
use STS\Bref\Bridge\Events\DeploymentRequested;
use function is_array;

class Deploy extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'bref:deploy';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Package (zip) the application in preparation for deployment, upload it to S3, and generate the .stack.yaml';

    public function handle(): int
    {
        event(new DeploymentRequested);

        $client = new CloudFormationClient([
            'version' => 'latest',
            'region' => config('bref.region'),
        ]);
        $result = $client->describeStacks([
            'StackName' => config('bref.name'),

        ]);


        $outputs = $result->search('Stacks[0].Outputs');
        if ($outputs === null || ! is_array($outputs)) {
            return 1;
        }
        $this->output->writeln('<fg=yellow>*****************************</>');
        foreach ($outputs as $output) {
            $this->output->writeln(
                sprintf(
                    '<fg=yellow>%s:</> <fg=green>%s</>',
                    $output['Description'],
                    $output['OutputValue']
                )
            );
        }

        return 0;
    }
}
