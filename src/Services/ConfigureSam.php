<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Services;

use Dotenv\Dotenv;
use STS\Bref\Bridge\Events\SamConfigurationRequested;
use Symfony\Component\Yaml\Yaml;
use function base_path;
use function config;

class ConfigureSam
{
    /** @var Array */
    protected $config;

    /**
     * Handles configuration of our AWS Serverless Application Model template
     */
    public function handle(SamConfigurationRequested $event): void
    {
        $this->config = Yaml::parseFile(base_path('template.yaml'), Yaml::PARSE_CUSTOM_TAGS);
        $this->setFunctionName(config('bref.name'));
        $this->config['Resources']['LaravelFunction']['Properties']['FunctionName'] = config('bref.description');
        $this->config['Resources']['LaravelFunction']['Properties']['Timeout'] = config('bref.timeout');
        $this->config['Resources']['LaravelFunction']['Properties']['MemorySize'] = config('bref.memory_size');
        $this->config['Resources']['LaravelFunction']['Properties']['Layers'] = config('bref.layers');
        $this->setEnvironmentVariables();
        file_put_contents(base_path('template.yaml'), Yaml::dump($this->config, 10, 4));
    }

    /**
     * Sets the function names for us.
     */
    protected function setFunctionName(string $functionName): void
    {
        $this->config['Resources']['LaravelFunction']['Properties']['FunctionName'] = $functionName;
        $this->config['Resources']['JobQueue']['Properties']['QueueName'] = 'job-queue-' . $functionName;
        $this->config['Resources']['LaravelFunctionExecutionRole']['Properties']['RoleName'] = 'function-role-' . $functionName;
    }

    /**
     * Given a list of variable names, or defaults to retrieving them from .env
     * we get and set the environment variables in the SAM template
     *
     * @param array $variableNames
     */
    protected function setEnvironmentVariables(array $variableNames = []): void
    {
        /* First, load up the variable from the .env file */
        $this->fromDotEnv($variableNames);

        /* Then load up (and overwrite DotEnv) required variables from Config */
        foreach (config('bref.env.required', []) as $name => $value) {
            $this->config['Resources']['LaravelFunction']['Properties']['Environment']['Variables'][$name] = $value;
        }
    }

    protected function fromDotEnv(array $variableNames): void
    {
        if (empty($variableNames)) {
            $dot = Dotenv::create(base_path());
            $dot->load();
            $variableNames = $dot->getEnvironmentVariableNames();
        }

        foreach ($variableNames as $variableName) {
            // These are hard coded, global settings. Ignore them in the .env file.
            // You can always edit template.yml yourself if you want to modify them.
            if (in_array_icase($variableName, config('bref.env.env_file_ignore'))) {
                continue;
            }
            $this->config['Resources']['LaravelFunction']['Properties']['Environment']['Variables'][$variableName] = (string) env(
                $variableName,
                ''
            );
        }
    }
}
