<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Services;

use Dotenv\Dotenv;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use STS\Bref\Bridge\Events\SamConfigurationRequested;
use Symfony\Component\Yaml\Yaml;
use function array_key_exists;
use function base_path;
use function in_array;

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
        $this->setEnvironmentVariables();
        $this->setSqsJobEvent();
        file_put_contents(base_path('template.yaml'), Yaml::dump($this->config, 10, 4));
    }

    /**
     * Sets the function names for us.
     */
    protected function setFunctionName(string $functionName): void
    {
        if (array_key_exists('Laravel', $this->config['Resources'])) {
            $this->config['Resources']['Laravel']['Properties']['FunctionName'] = $functionName;
            $this->config['Resources']['JobQueue']['Properties']['QueueName'] = $functionName . 'JobQueue';
        }
    }

    /**
     * Given a list of variable names, or defaults to retrieving them from .env
     * we get and set the environment variables in the SAM template
     *
     * @param array $variableNames
     */
    protected function setEnvironmentVariables(array $variableNames = []): void
    {
        if (empty($variables)) {
            $dot = Dotenv::create(base_path());
            $dot->load();
            $variableNames = $dot->getEnvironmentVariableNames();
        }

        foreach ($variableNames as $variableName) {
            // These are hard coded, global settings. Ignore them in the .env file.
            // You can always ecit template.yml yourself if you want to modify them.
            if (in_array($variableName, config('bref.env.ignore'))) {
                continue;
            }
            $this->config['Resources']['Laravel']['Properties']['Environment']['Variables'][$variableName] = (string) env(
                $variableName,
                ''
            );
        }
    }

    public function setSqsJobEvent(): void
    {
        if (! config('bref.sqs.jobs.trigger')) {
            return;
        }

        if (array_key_exists('Laravel', $this->config['Resources'])) {
            $this->config['Resources']['Laravel']['Properties']['Events']['SqsJobs']['Type'] = 'SQS';
            $this->config['Resources']['Laravel']['Properties']['Events']['SqsJobs']['Properties']['Queue'] = config('bref.sqs.jobs.arn');
            $this->config['Resources']['Laravel']['Properties']['Events']['SqsJobs']['Properties']['BatchSize'] = config('bref.sqs.jobs.batch_size');
        }
    }

    /**
     * Check all the routes defined in laravel and ensure we have them setup in
     * the API Gateway for our function.
     */
    protected function setRoutes(): void
    {
        // Handle the website events.
        $this->config['Resources']['Website']['Properties']['Events'] = [];

        /** @var RouteCollection $routeCollection */
        $routeCollection = Route::getRoutes();

        /** @var \Illuminate\Routing\Route $route */
        foreach ($routeCollection->getRoutes() as $route) {
            $methods = $route->methods();
            collect($methods)->each(function (string $method) use ($route): void {
                [$name, $config] = $this->routing($method, $route->uri, $route->getName());
                $this->config['Resources']['Website']['Properties']['Events'][$name] = $config;
            });
        }
    }

    /**
     * Figure out the routing for me.
     *
     * @return array
     */
    protected function routing(string $method, string $uri, string $name = ''): array
    {
        $routeName = $uri === '/' ? 'root' : preg_replace('/[^A-Za-z0-9\-]/', '', $uri);
        $name = empty($name) ? $name : sprintf('%s%s', ucfirst(strtolower($method)), ucfirst(strtolower($routeName)));
        $method = strtoupper($method);
        $path = $uri[0] === '/' ? $uri : '/' . $uri;
        $config = [
            'Type' => 'Api',
            'Properties' => [
                'Path' => $path,
                'Method' => $method,
            ],
        ];
        return [$name, $config];
    }
}
