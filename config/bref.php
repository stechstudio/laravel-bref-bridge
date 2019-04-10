<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bref Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your Lambda. This value is used when the
    | framework needs to generate the lambda function names.
    |
    */
    'name' => env('BREF_NAME', env('APP_NAME')),

    /*
    |--------------------------------------------------------------------------
    | Bref Description
    |--------------------------------------------------------------------------
    |
    | This value is the description of your Lambda. This value is used when the
    | framework needs to generate the lambda function descriptions.
    |
    */
    'description' => env('BREF_DESCRIPTION', ''),

    /*
    |--------------------------------------------------------------------------
    | Bref Region
    |--------------------------------------------------------------------------
    |
    | This value is the region of your Lambda. This value is used when the
    | framework needs to generate the lambda function regions.
    |
    */
    'region' => env('BREF_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),

    /*
    |--------------------------------------------------------------------------
    | Bref Function Timeout
    |--------------------------------------------------------------------------
    |
    | This value is the timeout, in seconds, to configure the lambda function
    | for. The API Gateway timeout is 30 seconds, so that is our default.
    | The maximum timeout is 900 seconds (15 minutes).
    |
    */
    'timeout' => env('BREF_FUNCTION_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Bref Function Memory Size
    |--------------------------------------------------------------------------
    |
    | The amount of memory that your function has access to. Increasing the
    | function's memory also increases it's CPU allocation. The default
    | value is 128 MB and the maximum value is 3,008 MB. The value
    | must be an integer multiple of 64 MB.
    |
    */
    'memory_size' => env('BREF_FUNCTION_MEMORY_SIZE', 3008),

    /*
    |--------------------------------------------------------------------------
    | Bref Function Layers
    |--------------------------------------------------------------------------
    |
    | A list of function layers to add to the function's execution environment.
    | Specify each layer by ARN, including the version, in the order they
    | should be layered, with a maximum of five layers.
    |
    */
    'layers' => array_filter([
        0 => env('BREF_FUNCTION_LAYER_1', 'arn:aws:lambda:us-east-1:209497400698:layer:php-73-fpm:2'),
        1 => env('BREF_FUNCTION_LAYER_2'),
        2 => env('BREF_FUNCTION_LAYER_3'),
        3 => env('BREF_FUNCTION_LAYER_4'),
        4 => env('BREF_FUNCTION_LAYER_5'),
    ]),

    /*
    |--------------------------------------------------------------------------
    | Keep
    |--------------------------------------------------------------------------
    |
    | The number of latest packages to keep on the filesystem.
    |
    */
    'keep' => env('BREF_PACKAGE_KEEP', 3),

    /*
    |--------------------------------------------------------------------------
    | SQS Job Queue
    |--------------------------------------------------------------------------
    |
    | Lambda consumption of job queues gets configured here. Publishing jobs
    | to queues still works as normal, So no changes there. Just update the .env
    |
    */

    'sqs' => [
        'jobs' => [
            'trigger' => env('SQS_TRIGGER_JOBS', false),
            'arn' => env('SQS_JOB_QUEUE_ARN', 'defaltz'),
            'batch_size' => env('SQS_JOB_QUEUE_BATCH_SIZE', 1),
            'credentials' => [
                'access_key_id' => env('SQS_KEY'),
                'secret_access_key' => env('SQS_SECRET'),
            ],
            'options' => [
                'delay' => env('SQS_JOB_DELAY', 0),
                'sleep' => env('SQS_JOB_SLEEP', 3),
                'force' => env('SQS_JOB_FORCE', false),
                'memory' => env('SQS_JOB_MEMORY', env('AWS_LAMBDA_FUNCTION_MEMORY_SIZE', 128)),
                'timeout' => env('SQS_JOB_TIMEOUT', 60),
                'max_retries' => env('SQS_JOB_MAX_RETRIES', 0),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Keep
    |--------------------------------------------------------------------------
    |
    | The number of latest packages to keep on the filesystem.
    |
    */
    'keep' => 3,

    /*
    |--------------------------------------------------------------------------
    | Packaging
    |--------------------------------------------------------------------------
    |
    | This array configures the files that should be ignored when packaging
    | your application, as well as identifying executable files.
    |
    */
    'packaging' => [
        'ignore' => [
            // Directories & Fully Qualified Paths
            base_path('tests'),
            base_path('storage'),
            base_path('.idea'),
            base_path('.git'),
            base_path('server.php'),
            base_path('.env'),
            base_path('.env.example'),
            base_path('versions.json'),
            base_path('.php_cs.cache'),
            base_path('.stack.yaml'),
            base_path('phpunit.xml'),
            base_path('template.yaml'),
            // File Names
            '.gitignore',
            '.gitkeep',
            '.htaccess',
            'readme.md',
            'composer.json',
            'composer.lock',
            '.DS_Store',
            '.editorconfig',
            '.gitattributes',
            'package.json',
        ],
        // Any executables should be here.
        'executables' => [
            'artisan',
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Function Environment Variables
    |--------------------------------------------------------------------------
    |
    | Environment variables that are accessible from
    | function code during execution.
    |
    */
    'env' => [
        /**
         * These are values that are required to be set. There are sane defaults,
         * but you can override them in your .env
         */
        'required' => [
            'APP_STORAGE' => env('BREF_APP_STORAGE', '/tmp/storage'),
            /* Make it easy to determine we are running in Lambda. Use `runningInLambda()` helper. */
            'BREF_LAMBDA_ENV' => true,
            /* Log to stderr so that everything goes to cloudwatch. */
            'LOG_CHANNEL' => env('BREF_LOG_CHANNEL', 'stderr'),
            'CACHE_DRIVER' => env('BREF_CACHE_DRIVER', 'file'),
            'SESSION_DRIVER' => env('BREF_SESSION_DRIVER', 'array'),
            'QUEUE_CONNECTION' => env('BREF_QUEUE_CONNECTION', 'sqs'),
        ],
        /**
         * These are values that are completely ignored from the .env file.
         * Remove them from this list to enable them.
         */
        'env_file_ignore' => [
            // Set by Cloudformation `!GetAtt JobQueue.Arn`
            'SQS_JOB_QUEUE_ARN',

            // Set by Cloudformation `!Sub 'https://sqs.${AWS::Region}.amazonaws.com/${AWS::AccountId}'`
            'SQS_PREFIX',

            // Set by Cloudformation `!GetAtt JobQueue.QueueName`
            'SQS_QUEUE',

            /*
            |--------------------------------------------------------------------------
            | Reserved Environment Variables
            |--------------------------------------------------------------------------
            |
            | Lambda Reserved Keys that are currently not supported for modification
            |
            */

            // The handler location configured on the function.
            '_HANDLER',

            // The AWS region where the Lambda function is executed.
            'AWS_REGION',
            'AWS_DEFAULT_REGION',

            // The runtime identifier, prefixed by AWS_Lambda_. For example, AWS_Lambda_java8.
            'AWS_EXECUTION_ENV',

            // The name of the function.
            'AWS_LAMBDA_FUNCTION_NAME',

            // The amount of memory available to the function in MB.
            'AWS_LAMBDA_FUNCTION_MEMORY_SIZE',

            // The version of the function being executed.
            'AWS_LAMBDA_FUNCTION_VERSION',

            // The name of the Amazon CloudWatch Logs group and stream for the function.
            'AWS_LAMBDA_LOG_GROUP_NAME',
            'AWS_LAMBDA_LOG_STREAM_NAME',

            // Access keys obtained from the function's execution role.
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_SESSION_TOKEN',

            // en_US.UTF-8. This is the locale of the runtime.
            'LANG',

            // The environment's timezone (UTC). The execution environment uses NTP to synchronize the system clock.
            'TZ',

            // The path to your Lambda function code.
            'LAMBDA_TASK_ROOT',

            // The path to runtime libraries.
            'LAMBDA_RUNTIME_DIR',

            // /usr/local/bin:/usr/bin/:/bin:/opt/bin
            'PATH',

            // /lib64:/usr/lib64:$LAMBDA_RUNTIME_DIR:$LAMBDA_RUNTIME_DIR/lib:$LAMBDA_TASK_ROOT:$LAMBDA_TASK_ROOT/lib:/opt/lib
            'LD_LIBRARY_PATH',

            // (Node.js) /opt/nodejs/node8/node_modules/:/opt/nodejs/node_modules:$LAMBDA_RUNTIME_DIR/node_modules
            'NODE_PATH',

            // (Python) $LAMBDA_RUNTIME_DIR.
            'PYTHONPATH',

            // (Ruby) $LAMBDA_TASK_ROOT/vendor/bundle/ruby/2.5.0:/opt/ruby/gems/2.5.0.
            'GEM_PATH',

            // (custom runtime) The host and port of the runtime API.
            'AWS_LAMBDA_RUNTIME_API',
        ],
    ],
];
