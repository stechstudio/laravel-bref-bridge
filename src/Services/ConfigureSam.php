<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Services;

use Dotenv\Dotenv;
use InvalidArgumentException;
use STS\Bref\Bridge\Events\SamConfigurationRequested;
use Symfony\Component\Yaml\Yaml;
use function base_path;
use function config;
use function count;
use function is_array;

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
        $this->config['Resources']['LaravelFunction']['Properties']['Description'] = config('bref.description');
        $this->setFunctionTimeout();
        $this->setFunctionMemorySize();
        $this->setFunctionLayers();
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

    protected function setFunctionTimeout(): void
    {
        if (config('bref.timeout') > 900) {
            throw new InvalidArgumentException('The bref timeout can not exceed 900 seconds (15 minutes).');
        }
        $this->config['Resources']['LaravelFunction']['Properties']['Timeout'] = config('bref.timeout');
    }

    protected function setFunctionMemorySize(): void
    {
        if (config('bref.memory_size') % 64 !== 0 || config('bref.memory_size') < 128) {
            throw new InvalidArgumentException('The bref memory size must be between 128 MB to 3,008 MB, in 64 MB increments..');
        }
        $this->config['Resources']['LaravelFunction']['Properties']['MemorySize'] = config('bref.memory_size');
    }

    protected function setFunctionLayers(): void
    {
        if (! is_array(config('bref.layers')) || count(config('bref.layers')) === 0 || count(config('bref.layers')) > 5) {
            throw new InvalidArgumentException('You must provide at least one layer and no more than five layers.');
        }
        $this->config['Resources']['LaravelFunction']['Properties']['Layers'] = config('bref.layers');
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
