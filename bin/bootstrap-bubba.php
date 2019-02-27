#!/opt/php/bin/php
<?php declare(strict_types=1);

use Bref\Runtime\PhpFpm;
use STS\Bref\Bridge\Services\Bootstrap;

ini_set('display_errors', '1');
error_reporting(E_ALL);

/**
 * Handle composers vendor autoloading.
 */
$vendorAutoLoad = sprintf('%s/%s', getenv('LAMBDA_TASK_ROOT'), '/laravel/vendor/autoload.php');

if (! is_file($vendorAutoLoad)) {
    echo "Composers `$vendorAutoLoad` doesn't exist";
    exit(1);
}

require $vendorAutoLoad;


/**
 * Now we can instantiate the bootstrap and kick it off.
 */
$bootstrap = new Bootstrap;
$indexPhp = sprintf('%s/%s', getenv('LAMBDA_TASK_ROOT'), '/laravel/public/index.php');
$bootstrap->setPhpFpm(new PhpFpm($indexPhp));
$bootstrap->setLambdaFunction();

while (true) {
    try {
        $bootstrap->handleInvocation();
    } catch (Throwable $e) {
        CLIBootstrap::consoleLog($e->getMessage());
        CLIBootstrap::consoleLog($e->getTraceAsString());
        exit(1);
    }
}
