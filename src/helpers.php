<?php declare(strict_types=1);

if (! function_exists('runningInLambda')) {
    /**
     * Heps us check to see if we are running in a Lambda context
     * or not.
     */
    function runningInLambda(): bool
    {
        return getenv('BREF_LAMBDA_ENV') !== false;
    }
}
if (! function_exists('tempDir')) {
    /**
     * Creates a Temporary Directory for us.
     */
    function tempDir(string $prefix = '', bool $deleteOnShutdown = true): SplFileInfo
    {
        $tmpFile = tempnam(sys_get_temp_dir(), $prefix);
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        mkdir($tmpFile);
        if (is_dir($tmpFile)) {
            if ($deleteOnShutdown) {
                register_shutdown_function(function () use ($tmpFile): void {
                    rmFolder($tmpFile);
                });
            }
            return new SplFileInfo($tmpFile);
        }
    }
}
if (! function_exists('rmFolder')) {
    /**
     * Recursively Delete a Directory
     */
    function rmFolder(string $location): bool
    {
        if (! is_dir($location)) {
            return false;
        }
        $contents = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($location, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var SplFileInfo $file */
        foreach ($contents as $file) {
            if (is_link($file->getPathname()) && ! file_exists($file->getPathname())) {
                @unlink($file->getPathname());
                continue;
            }
            if (! $file->isReadable()) {
                throw new RuntimeException("{$file->getFilename()} is not readable.");
            }
            switch ($file->getType()) {
                case 'dir':
                    rmFolder($file->getRealPath());
                    break;
                case 'link':
                    unlink($file->getPathname());
                    break;
                default:
                    unlink($file->getRealPath());
            }
        }
        return rmdir($location);
    }
}
if (! function_exists('copyFolder')) {
    /**
     * Recursively Copy a Directory
     */
    function copyFolder(string $source, string $destination): bool
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0777, true);
        }
        $directory = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);

        /** @var  RecursiveDirectoryIterator $iterator */
        $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);

        /** @var SplFileInfo $item */
        foreach ($iterator as $key => $item) {
            if ($item->isFile() || $item->isLink()) {
                copy($item->getRealPath(), $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
                continue;
            }

            if ($item->isDir()) {
                $destDir = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                if (! is_dir($destDir)) {
                    @mkdir($destDir);
                }
                continue;
            }

            dd($iterator);
        }
        return true;
    }
}

if (! function_exists('exceptionToArray')) {
    function exceptionToArray(?Throwable $e): array
    {
        if ($e === null) {
            return [];
        }
        return [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stack_trace' => $e->getTrace(),
            'previous' => exceptionToArray($e->getPrevious()),
        ];
    }
}

if (! function_exists('lambda')) {
    function lambda(string $event, string $context): string
    {
        $app = require_once __DIR__ . '/bootstrap/app.php';

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

        /** @var \STS\Bref\Bridge\Lambda\Kernel $kernel */
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

        $results = $kernel->handle($event, $context);

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

        $kernel->terminate($input, $status);

        return $results;
    }
}
