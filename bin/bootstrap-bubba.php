#!/opt/bin/php -dextension=pthreads.so
<?php declare(strict_types=1);

use Bref\Runtime\PhpFpm;
use STS\Bref\Bridge\Services\Bootstrap;

/*
|--------------------------------------------------------------------------
| MAIN SCRIPT
|--------------------------------------------------------------------------
|
| These are the bootstrap commands executed by the script.
|
*/

runtimeIniSettings();
handleVendorAutoload();

/** @var Bootstrap $bootstrap */
$bootstrap = getBootstrap();
initializePhpFpm($bootstrap);

while (true) {
    try {
        $bootstrap->handleInvocation();
    } catch (Throwable $t) {
        print $t->getMessage();
        print $t->getTraceAsString();
        exit(1);
    }
}

/*
|--------------------------------------------------------------------------
| FUNCTION DEFINITIONS
|--------------------------------------------------------------------------
|
| Everything from this point down is a function that helps with our
| bootstrapping efforts.
|
*/


/**
 * If the runtime encounters an error during initialization,
 * it posts an error message to the initialization error path.
 *
 * @see https://docs.aws.amazon.com/lambda/latest/dg/runtimes-api.html#runtimes-api-initerror
 */
function initializationError(string $errorMessage, string $errorType): void
{
    $data = [
        'errorMessage' => $errorMessage,
        'errorType' => $errorType,
    ];

    $payload = json_encode($data);

    echo "$payload\n";

    // Prepare new cURL resource
    $url = sprintf('http://%s/2018-06-01/runtime/init/error', getenv('AWS_LAMBDA_RUNTIME_API'));

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    // Set HTTP Header for POST request
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ]
    );

    // Submit the POST request
    curl_exec($ch);
    // Close cURL session handle
    curl_close($ch);
    // Now die
    exit(1);
}


/**
 * We will require the vendor autoload ... or die trying.
 */
function handleVendorAutoload(): void
{
    $taskRoot = getenv('LAMBDA_TASK_ROOT') ?: realpath(__DIR__ . '/..');
    $path = is_file("$taskRoot/laravel/vendor/autoload.php") ? '/laravel/vendor/autoload.php' : '/vendor/autoload.php';

    /**
     * Handle composers vendor autoloading.
     */
    $vendorAutoLoad = sprintf('%s/%s', $taskRoot, $path);

    if (! is_file($vendorAutoLoad)) {
        initializationError(
            "Composers `$vendorAutoLoad` doesn't exist",
            'InvalidVendorAutoloader'
        );
    }

    require $vendorAutoLoad;
}

/**
 * Here we handle any ini settings we want to ensure are available at runtime.
 */
function runtimeIniSettings(): void
{
    /**
     * We ensure this is turned on so that errors will be printed to
     * stderr, which will ultimately put them the Cloudwatch Logs for
     * the Lambda Function.
     */
    ini_set('display_errors', 'stderr');

    /**
     * We ensure that All errors and warnings will be reported, and
     * thus logged in the Cloudwatch Logs.
     */
    ini_set('error_reporting', (string) E_ALL);
}

/**
 * Get the bootstrap or die trying.
 */
function getBootstrap(): Bootstrap
{
    /**
     * Now we can instantiate the bootstrap
     */
    try {
        $bootstrap = new Bootstrap;
    } catch (Throwable $t) {
        initializationError(
            "Could not create Bootstrap. {$t->getMessage()}",
            'InvalidBootstrapCreation'
        );
    }
    return $bootstrap;
}

/**
 * Initialize PHP FPM or die trying
 */
function initializePhpFpm(Bootstrap $bootstrap): void
{
    /**
     * Initialize PHP FPM
     */
    try {
        $indexPhp = sprintf('%s/%s', getenv('LAMBDA_TASK_ROOT'), '/laravel/public/index.php');
        $bootstrap->setPhpFpm(new PhpFpm($indexPhp));
        $bootstrap->startPhpFpm();
    } catch (Throwable $t) {
        initializationError(
            "Could not initialize PHP FPM. {$t->getMessage()}",
            'InvalidPhpFpmInitialization'
        );
    }
}
