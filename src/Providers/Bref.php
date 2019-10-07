<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use STS\Bref\Bridge\Console\ConfigSam;
use STS\Bref\Bridge\Console\Deploy;
use STS\Bref\Bridge\Console\Package;
use STS\Bref\Bridge\Console\StartApi;
use STS\Bref\Bridge\Console\Update;
use STS\Bref\Bridge\Events\DeploymentRequested;
use STS\Bref\Bridge\Events\LambdaPackageRequested;
use STS\Bref\Bridge\Events\SamConfigurationRequested;
use STS\Bref\Bridge\Events\UpdateRequested;
use STS\Bref\Bridge\Lambda\Contracts\Registrar;
use STS\Bref\Bridge\Lambda\Router;
use STS\Bref\Bridge\Services\ConfigureSam;
use STS\Bref\Bridge\Services\DeployFunction;
use STS\Bref\Bridge\Services\PackageFunction;
use STS\Bref\Bridge\Services\UpdateFunction;
use STS\LBB\Facades\LambdaRoute;
use function base_path;

class Bref extends ServiceProvider
{
    /**
     * The event => listener mappings for Bref.
     *
     * @var array
     */
    protected $listen
        = [

            SamConfigurationRequested::class => [ConfigureSam::class],
            LambdaPackageRequested::class    => [PackageFunction::class],
            DeploymentRequested::class       => [DeployFunction::class],
            UpdateRequested::class           => [UpdateFunction::class],
        ];

    /**
     * Bref Console Commands to register.
     *
     * @var array
     */
    protected $commandList
        = [
            Package::class,
            ConfigSam::class,
            StartApi::class,
            Deploy::class,
            Update::class,
        ];

    /**
     * Default path to laravel configuration file in the package
     *
     * @var string
     */
    protected $configPath = __DIR__.'/../../config/bref.php';

    /**
     * Default path to the SAM Template in the package
     *
     * @var string
     */
    protected $samTemplatePath = __DIR__.'/../../config/cloudformation.yaml';

    /**
     * Default path to publish the lambda routes file from.
     *
     * @var string
     */
    protected $routesPath = __DIR__.'/../../routes/lambda.php';

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->handlePublishing();

        $this->registerEventListeners();
        LambdaRoute::registerFromFile(base_path('routes/lambda.php'));
    }

    /**
     * Publish any artifacts to laravel user space
     */
    public function handlePublishing(): void
    {
        $publishConfigPath = config_path('bref.php');

        $this->publishes([$this->configPath => $publishConfigPath],
            'bref-configuration');
        $this->publishes([$this->routesPath => base_path('routes/lambda.example.php')],
            'bref-routes');
        $this->publishes([$this->samTemplatePath => base_path('template.yaml')],
            'bref-sam-template');
    }

    /**
     * Handle registering any event listeners.
     */
    public function registerEventListeners(): void
    {
        foreach ($this->listens() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Get the events and handlers.
     *
     * @return array
     */
    public function listens(): array
    {
        return $this->listen;
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['LambdaRouter', Registrar::class];
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            Registrar::class,
            Router::class
        );

        $this->app->alias(Registrar::class, 'bref.lambda.router');

        $this->commands($this->commandList);
    }
}
