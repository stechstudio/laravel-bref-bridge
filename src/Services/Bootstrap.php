<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 13:14
 */

namespace STS\Bref\Bridge\Services;

use Bref\Runtime\PhpFpm;
use parallel\Runtime;
use STS\AwsEvents\Events\ApiGatewayProxyRequest;
use STS\AwsEvents\Events\Event;
use STS\Bref\Bridge\Lambda\Contracts\Application as LambdaContract;
use STS\Bref\Bridge\Lambda\Kernel;
use function array_key_exists;
use function finfo_open;

class Bootstrap
{
    /**
     * AWS Runtime Version we are using.
     *
     * @var string
     */
    private $rumtimeAPI;
    /**
     * The invocation ID of the event being processed.
     *
     * @var string
     */
    private $requestId;
    /**
     * The body of the invocation, the event.
     *
     * @var string
     */
    private $requestBody;
    /**
     * A cURL handle for retrieving the next task.
     *
     * @var resource
     */
    private $next;
    /**
     * A cURL handle for reporting errors.
     *
     * @var resource
     */
    private $error;
    /**
     * A cURL handle for reporting results.
     *
     * @var resource
     */
    private $result;
    /**
     * A place to store a lot of context information.
     *
     * @var array
     */
    private $context;
    /**
     * Absolute path to our vendor/autoload.
     *
     * @var string
     */
    private $vendorAutoload;

    /**
     * Reference to the PHP FPM object we are using.
     *
     * @var PhpFpm
     */
    private $phpFpm;
    /** @var resource|false */
    private $finfo;

    /**
     * Handle all the initialization here. This is where all the "cold start"
     * logic should be.
     */
    public function __construct()
    {
        self::consoleLog('Cold Start');
        $this->vendorAutoload = sprintf('%s/%s', getenv('LAMBDA_TASK_ROOT'),
            '/laravel/vendor/autoload.php');
        $this->rumtimeAPI = (string) getenv('AWS_LAMBDA_RUNTIME_API');
        $this->initInvocationFetcher();
        $this->initInvocationError();
        $this->finfo = finfo_open(FILEINFO_MIME_TYPE);
    }

    /**
     * Helper function to template writing to the console from
     * the bootstrap.
     */
    public static function consoleLog(string $message): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Initialize the cURL handle for getting the next task.
     */
    protected function initInvocationFetcher(): void
    {
        $this->next = curl_init(sprintf('http://%s/2018-06-01/runtime/invocation/next',
            $this->rumtimeAPI));
        curl_setopt($this->next, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->next, CURLOPT_FAILONERROR, true);
        curl_setopt($this->next, CURLOPT_HEADERFUNCTION, [$this, 'writeHeader']);
        curl_setopt($this->next, CURLOPT_WRITEFUNCTION, [$this, 'writeData']);
    }

    /**
     * Initialize the cURL handle for reporting errors.
     */
    protected function initInvocationError(): void
    {
        $this->error = curl_init();
        curl_setopt($this->error, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->error, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Get the PHP FPM object we are using
     */
    public function getPhpFpm(): PhpFpm
    {
        return $this->phpFpm;
    }

    /**
     * Set the PHP FPM object to use.
     */
    public function setPhpFpm(PhpFpm $phpFpm): void
    {
        $this->phpFpm = $phpFpm;
    }

    /**
     * Start up PHP FPM
     */
    public function startPhpFpm(): void
    {
        $this->phpFpm->start();
    }

    /**
     * Get our vendor autoload path.
     */
    public function getVendorAutoload(): string
    {
        return $this->vendorAutoload;
    }

    /**
     * Set the vendor autoload path.
     */
    public function setVendorAutoload(string $vendorAutoload): void
    {
        $this->vendorAutoload = $vendorAutoload;
    }

    /**
     * This is where the magic happens.
     */
    public function handleInvocation(): void
    {
        // Reset all our task specific variables.
        $this->clearTaskParams();
        try {
            $this->getNextTask();
        } catch (\Throwable $e) {
            self::consoleLog('No task, or other error');
            // An exception here simply triggers a return.
            return;
        }
        // Execute the users lambda.
        $this->executeLambda();
    }

    /**
     * Ensures we start every task with no data from
     * the last task.
     */
    protected function clearTaskParams(): void
    {
        $this->requestId = '';
        $this->requestBody = '';
        $this->context = [];
    }

    /**
     * Handles fetching the next task
     */
    public function getNextTask(): void
    {
        // Politely ask cURL to fetch
        curl_exec($this->next);

        // Check for any errors while fetching.
        if (curl_error($this->next)) {
            self::consoleLog('Failed to fetch next Lambda invocation: ' . curl_error($this->next));
            throw new \RuntimeException(curl_error($this->next));
        }

        // Check to ensure that we figured out what the Invocation ID is.
        if (empty($this->requestId)) {
            self::consoleLog('Failed to determine Lambda invocation ID');
            throw new \RuntimeException('Failed to determine Lambda invocation ID');
        } else {
            curl_setopt(
                $this->error,
                CURLOPT_URL,
                sprintf(
                    'http://%s/2018-06-01/runtime/invocation/%s/response',
                    $this->rumtimeAPI,
                    $this->requestId
                )
            );
        }
        // We also need the invocation body, which is where the actual event is.
        if (empty($this->requestBody)) {
            self::consoleLog('Empty Event');
            $response = [
                'statusCode' => 500,
                'message' => 'An empty event was received',
                'errorType' => 'InvalidEvent',
                'requestId' => $this->requestId,
            ];
            $response_json = json_encode($response);
            curl_setopt($this->error, CURLOPT_POSTFIELDS, $response_json);
            curl_setopt(
                $this->error,
                CURLOPT_HTTPHEADER,
                [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($response_json),
                ]
            );
            curl_exec($this->error);
            throw new \RuntimeException('Failed to determine Lambda invocation ID');
        }
    }

    /**
     * We effectively have two paths of execution.
     *
     * If this even is from API Gateway, then we will handle it like any other
     * HTTP Request, and simply proxy it over to PHP FPM
     *
     * If it is any other event, we will spin off a pthread and bootstrap into
     * the Laravel Lambda Kernel.
     *
     * Finally, we will report back any results to the Lambda API signaling we are done.
     */
    public function executeLambda(): void
    {
        $event = Event::fromString($this->requestBody);

        // The PHP FPM Path
        if (ApiGatewayProxyRequest::supports($event)) {
            /** @var ApiGatewayProxyRequest $event */
            $urlPath = ltrim($event->get('path'), '/');
            $path = sprintf('%s/laravel/public/%s', getenv('LAMBDA_TASK_ROOT'), $urlPath);
            if (is_file($path)) {
                $mimeType = $this->finfo->file($path);
                $base64Encode = ! substr($mimeType, 0, 4) === 'text';

                $this->reportResult([
                    'isBase64Encoded' => $base64Encode,
                    'statusCode' => 200,
                    'headers' => ['Content-type' => $mimeType],
                    'body' => $base64Encode ?
                        'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($path)) :
                        file_get_contents($path),
                ]);
                return;
            }

            $this->phpFpm->ensureStillRunning();
            $this->reportResult($this->phpFpm->proxy($event->toArray())->toApiGatewayFormat());
            return;
        }

        // The threaded path.
        $autoLoader = '/var/task/laravel/vendor/autoload.php';

        if (file_exists('/var/task/laravel/bootstrap/custom-autoload.php')) {
            $autoLoader = '/var/task/laravel/bootstrap/custom-autoload.php';
        }

        $runtime = new Runtime($autoLoader);
        try {
            $future = $runtime->run(function (): array {
                define('LARAVEL_START', microtime(true));
                $app = require_once '/var/task/laravel/bootstrap/app.php';

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

                $app->useStoragePath($storagePath);

                /*
                |--------------------------------------------------------------------------
                | Run The Artisan Application
                |--------------------------------------------------------------------------
                |
                | When we run the console application, the current CLI command will be
                | executed in this console and the response sent back to a terminal
                | or another output device for the developers. Here goes nothing!
                |
                */

                // Inject the Lambda Kernel
                $app->singleton(
                    LambdaContract::class,
                    Kernel::class
                );

                /** @var Kernel $kernel */
                $kernel = $app->make(LambdaContract::class);

                $results = $kernel->handle($this->requestBody, json_encode($this->context));

                /*
                |--------------------------------------------------------------------------
                | Shutdown The Application
                |--------------------------------------------------------------------------
                |
                | Once Artisan has finished running, we will fire off the shutdown events
                | so that any final work may be done by the application before we shut
                | down the process. This is the last thing to happen to the request.
                |
                */

                $kernel->terminate(0);

                return $results;
            }
            );
        } catch (\Throwable $e) {
            // This is all error handling.
            self::consoleLog('ERROR: ' . $e->getMessage());
            $response = [
                'statusCode' => 500,
                'message' => $e->getMessage(),
                'errorType' => 'FailedFunction',
                'requestId' => $this->requestId,
            ];
            $response_json = json_encode($response);
            curl_setopt($this->error, CURLOPT_POSTFIELDS, $response_json);
            curl_setopt($this->error, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($response_json),
            ]);
            curl_exec($this->error);
            throw new \RuntimeException('Failed to execute the Lambda Function.');
        }
    }

    /**
     * Send the result of the lambda back.
     */
    public function reportResult(array $response): void
    {
        self::consoleLog('reportResult');
        $this->initInvocationResult();

        $endpoint = $this->isErrorResponse($response) ? 'error' : 'response';

        // Now we can encode everything.
        $response_json = json_encode($response);
        self::consoleLog($response_json);
        // Set up curl
        curl_setopt(
            $this->result,
            CURLOPT_URL,
            sprintf(
                'http://%s/2018-06-01/runtime/invocation/%s/%s',
                $this->rumtimeAPI,
                $this->requestId,
                $endpoint
            )
        );
        curl_setopt($this->result, CURLOPT_POSTFIELDS, $response_json);
        curl_setopt($this->result, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($response_json),
        ]);
        // Send the results
        curl_exec($this->result);
        // Check for any errors while sending.
        if (curl_error($this->result)) {
            self::consoleLog('Failed to report lambda results: ' . curl_error($this->result));
            throw new \RuntimeException(curl_error($this->result));
        }
    }

    /**
     * Initialize the cURL handle for reporting results.
     */
    protected function initInvocationResult(): void
    {
        $this->result = curl_init();
        curl_setopt($this->result, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->result, CURLOPT_RETURNTRANSFER, true);
    }

    protected function isErrorResponse(array $response): bool
    {
        return array_key_exists('bref_status', $response) && $response['bref_status'] === 'error';
    }

    /**
     * Callback for processing headers when getting a new task.
     *
     * @param resource $ch A Curl Resource Handle
     */
    protected function writeHeader($ch, string $header): int
    {
        $headerLength = strlen($header);
        if (! preg_match('/:\s*/', $header)) {
            return $headerLength;
        }
        [$name, $value] = preg_split('/:\s*/', $header, 2);
        $this->context[strtolower(trim($name))] = trim($value);
        if (strtolower($name) === 'lambda-runtime-aws-request-id') {
            $this->requestId = trim($value);
        }
        return $headerLength;
    }

    /**
     * Callback for handling the response body of the get new task.
     *
     * @param resource $ch A Curl Resource Handle
     */
    protected function writeData($ch, string $chunk): int
    {
        $this->requestBody .= $chunk;
        return strlen($chunk);
    }
}
