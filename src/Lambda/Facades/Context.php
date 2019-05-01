<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-04-30
 * Created Time: 20:57
 */

namespace STS\Bref\Bridge\Lambda\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \STS\AwsEvents\Contexts\Context fromJson(string $jsonContext)
 * @method static array toArray()
 * @method static string getFunctionName()
 * @method static \STS\AwsEvents\Contexts\Context setFunctionName(string $functionName)
 * @method static string getFunctionVersion()
 * @method static \STS\AwsEvents\Contexts\Context setFunctionVersion(string $functionVersion)
 * @method static string getLogGroupName()
 * @method static \STS\AwsEvents\Contexts\Context setLogGroupName(string $logGroupName)
 * @method static string getLogStreamName()
 * @method static \STS\AwsEvents\Contexts\Context setLogStreamName(string $logStreamName)
 * @method static string getMemoryLimitInMb()
 * @method static \STS\AwsEvents\Contexts\Context setMemoryLimitInMb(string $memoryLimitInMb)
 * @method static string getInvokedFunctionArn()
 * @method static \STS\AwsEvents\Contexts\Context setInvokedFunctionArn(string $invokedFunctionArn)
 * @method static string getAwsRequestId()
 * @method static \STS\AwsEvents\Contexts\Context setAwsRequestId(string $awsRequestId)
 * @method static int getRuntimeDeadlineMs()
 * @method static \STS\AwsEvents\Contexts\Context setRuntimeDeadlineMs(int $runtimeDeadlineMs)
 * @method static string getXRayTraceId()
 * @method static \STS\AwsEvents\Contexts\Context setXRayTraceId(string $xRayTraceId):
 * @method static int getTimeRemaining()
 * @method static \STS\AwsEvents\Contexts\Identity getIdentity()
 * @method static \STS\AwsEvents\Contexts\Context setIdentity(\STS\AwsEvents\Contexts\Identity $identity)
 * @method static \STS\AwsEvents\Contexts\Client getClient()
 * @method static \STS\AwsEvents\Contexts\Context setClient(STS\AwsEvents\Contexts\Client $client)
 * @mixin Facade
 */
class Context extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bref.lambda.context';
    }
}
