<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Providers;

use Illuminate\Support\Facades\Config;
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

class Bref extends ServiceProvider
{
    /**
     * The event => listener mappings for Bref.
     *
     * @var array
     */
    protected $listen = [

        SamConfigurationRequested::class => [ConfigureSam::class],
        LambdaPackageRequested::class => [PackageFunction::class],
        DeploymentRequested::class => [DeployFunction::class],
        UpdateRequested::class => [UpdateFunction::class],
    ];

    /**
     * Bref Console Commands to register.
     *
     * @var array
     */
    protected $commandList = [
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
    protected $configPath = __DIR__ . '/../../config/bref.php';

    /**
     * Default path to the SAM Template in the package
     *
     * @var string
     */
    protected $samTemplatePath = __DIR__ . '/../../config/cloudformation.yaml';

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        // if we are running in lambda, lets shuffle some things around.
        if (runningInLambda()) {
            $this->setupStorage();
            $this->setupSessionDriver();
        }
        $this->handlePublishing();

        $this->registerEventListeners();
    }

    /**
     * Since the lambda filesystem is readonly except for
     * `/tmp` we need to customize the storage area.
     */
    public function setupStorage(): void
    {
        $storagePath = '/tmp/storage';

        $storagePaths = [
            '/app/public',
            '/framework/cache/data',
            '/framework/sessions',
            '/framework/testing',
            '/framework/views',
            '/logs',
        ];

        // Only make the dirs if we have not previously made them
        if (! is_dir($storagePath . end($storagePaths))) {
            reset($storagePaths);
            foreach ($storagePaths as $path) {
                mkdir($storagePath . $path, 0777, true);
            }
        }

        $this->app->useStoragePath($storagePath);
        $this->app['config']['view.compiled'] = realpath(storage_path('framework/views'));
    }

    /**
     * Lambda cannot persist sessions to disk.
     */
    public function setupSessionDriver(): void
    {
        // if you try to we will override
        // you and save you from yourself.
        if (env('SESSION_DRIVER') === 'file') {
            // If you need sessions, store them
            // in redis, a database, or cookies
            // anything that scales horizontally
            putenv('SESSION_DRIVER=array');
            Config::set('session.driver', 'array');
        }
    }

    /**
     * Publish any artifacts to laravel user space
     */
    public function handlePublishing(): void
    {
        // helps deal with Lumen vs Laravel differences
        if (function_exists('config_path')) {
            $publishConfigPath = config_path('bref.php');
        } else {
            $publishConfigPath = base_path('config/bref.php');
        }

        $this->publishes([$this->configPath => $publishConfigPath], 'bref-configuration');
        $this->publishes([$this->samTemplatePath => base_path('template.yaml')], 'bref-sam-template');
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
     * Register the application services.
     */
    public function register(): void
    {
        if (is_a($this->app, 'Laravel\Lumen\Application')) {
            $this->app->configure('bref');
        }
        $this->app->singleton(
            Registrar::class,
            Router::class
        );
        $this->mergeConfigFrom($this->configPath, 'bref');
        $this->commands($this->commandList);
    }
}
