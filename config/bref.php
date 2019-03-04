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
    |Packaging
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
    | Ignored .env Variables
    |--------------------------------------------------------------------------
    |
    | This value is the name of your Lambda. This value is used when the
    | framework needs to generate the lambda function names.
    |
    */
    'env' => [
        'ignore' => [
            // Hardcoded to 'tmp'
            'APP_STORAGE',

            // True
            'BREF_LAMBDA_ENV',

            // stderr (ensures everything logs into cloudwatch)
            'LOG_CHANNEL',

            // file (because, what else really makes sense?
            'CACHE_DRIVER',

            // Array
            'SESSION_DRIVER',

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
