<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use STS\Bref\Bridge\Console\SAM\ConfigSam;
use STS\Bref\Bridge\Console\SAM\Deploy;
use STS\Bref\Bridge\Console\SAM\Package;
use STS\Bref\Bridge\Console\SAM\StartApi;
use STS\Bref\Bridge\Console\SAM\Update;
use STS\Bref\Bridge\Events\SamConfigurationRequested;
use STS\Bref\Bridge\Events\SamDeploymentRequested;
use STS\Bref\Bridge\Events\SamPackageRequested;
use STS\Bref\Bridge\Events\SamUpdateRequested;
use STS\Bref\Bridge\Lambda\Router;
use STS\Bref\Bridge\Services\ConfigureSam;
use STS\Bref\Bridge\Services\DeployFunction;
use STS\Bref\Bridge\Services\PackageFunction;
use STS\Bref\Bridge\Services\SAM\Configuration as SamConfiguration;
use STS\Bref\Bridge\Services\SAM\Deployment as SamDeployment;
use STS\Bref\Bridge\Services\SAM\Package as SamPackage;
use STS\Bref\Bridge\Services\SAM\Update as SamUpdate;
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

            SamConfigurationRequested::class => [SamConfiguration::class],
            SamPackageRequested::class       => [SamPackage::class],
            SamDeploymentRequested::class    => [SamDeployment::class],
            SamUpdateRequested::class        => [SamUpdate::class],
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
     * Register the application services.
     */
    public function register(): void
    {
        $this->commands($this->commandList);
    }
}
