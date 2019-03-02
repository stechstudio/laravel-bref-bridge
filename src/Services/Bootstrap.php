<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 13:14
 */

namespace STS\Bref\Bridge\Services;

use Bref\Runtime\PhpFpm;
use STS\AwsEvents\Events\ApiGatewayProxyRequest;
use STS\AwsEvents\Events\Event;
use Threaded;
use const PTHREADS_INHERIT_NONE;

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
    /** @var string */
    private $vendorAutoload;
    /** @var PhpFpm */
    private $phpFpm;

    /**
     * Handle all the initialization here. This is where all the "cold start"
     * logic should be.
     */
    public function __construct()
    {
        self::consoleLog('Cold Start');
        $this->vendorAutoload = sprintf('%s/%s', getenv('LAMBDA_TASK_ROOT'), '/laravel/vendor/autoload.php');
        $this->rumtimeAPI = (string) getenv('AWS_LAMBDA_RUNTIME_API');
        $this->initInvocationFetcher();
        $this->initInvocationError();

        /**
         * $phpFpm = new PhpFpm($handler);
         * $phpFpm->start();
         */
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
        $this->next = curl_init(sprintf('http://%s/2018-06-01/runtime/invocation/next', $this->rumtimeAPI));
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

    public function getPhpFpm(): PhpFpm
    {
        return $this->phpFpm;
    }

    public function setPhpFpm(PhpFpm $phpFpm): void
    {
        $this->phpFpm = $phpFpm;
    }

    public function startPhpFpm(): void
    {
        $this->phpFpm->start();
    }

    public function getVendorAutoload(): string
    {
        return $this->vendorAutoload;
    }

    public function setVendorAutoload(string $vendorAutoload): void
    {
        $this->vendorAutoload = $vendorAutoload;
    }

    /**
     * This is where the magic happens.
     */
    public function handleInvocation(): void
    {
        self::consoleLog('handleInvocation');
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

    public function executeLambda(): void
    {
        $event = Event::fromString($this->requestBody);

        if (ApiGatewayProxyRequest::supports($event)) {
            $this->phpFpm->ensureStillRunning();
            $this->reportResult($this->phpFpm->proxy($event->toArray())->toApiGatewayFormat());
            return;
        }

        try {
            $store = new Threaded;
            $thread = new LambdaRunner($store, $this->requestBody, json_encode($this->context));
            $thread->start(PTHREADS_INHERIT_NONE) && $thread->join();
            $this->reportResult($store->shift());
        } catch (\Throwable $e) {
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
        // Initialize a new cURL resource for reporting.
        $this->initInvocationResult();

        // Now we can encode everything.
        $response_json = json_encode($response);
        self::consoleLog($response_json);
        // Set up curl
        curl_setopt(
            $this->result,
            CURLOPT_URL,
            sprintf(
                'http://%s/2018-06-01/runtime/invocation/%s/response',
                $this->rumtimeAPI,
                $this->requestId
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
