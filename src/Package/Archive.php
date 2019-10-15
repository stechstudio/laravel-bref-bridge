<?php declare(strict_types=1);

namespace STS\Bref\Bridge\Package;

use Carbon\Carbon;
use GisoStallenberg\FilePermissionCalculator\FilePermissionCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use STS\Bref\Bridge\Exceptions\Package;
use Symfony\Component\Process\Process;
use ZipArchive;
use function base_path;
use function config;
use function copy;
use function copyFolder;
use function rmFolder;
use function storage_path;
use function str_replace;
use function strpos;

class Archive
{
    /** @var array */
    protected static $files
        = [
            'vendor/autoload.php',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/autoload_files.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_psr4.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_static.php',
            'vendor/composer/ClassLoader.php',
            'vendor/composer/installed.json',
        ];
    /** @var  ZipArchive */
    protected $zipArchive;
    /** @var string */
    private $path;

    public function __construct(string $path)
    {
        $this->zipArchive = new ZipArchive;
        $this->path       = $path;
        $this->init();
    }

    /**
     * Initialize the Archive. Overwrite and create whatever was there.
     */
    public function init(): void
    {
        $res = $this->zipArchive->open($this->path,
            \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new Package($this->message($res), $res);
        }
    }

    /**
     * Convert ZipArchive Codes to Human Readable Messages
     */
    protected function message(int $code): string
    {
        switch ($code) {
            case 0:
                return 'No error';
            case 1:
                return 'Multi-disk zip archives not supported';
            case 2:
                return 'Renaming temporary file failed';
            case 3:
                return 'Closing zip archive failed';
            case 4:
                return 'Seek error';
            case 5:
                return 'Read error';
            case 6:
                return 'Write error';
            case 7:
                return 'CRC error';
            case 8:
                return 'Containing zip archive was closed';
            case 9:
                return 'No such file';
            case 10:
                return 'File already exists';
            case 11:
                return 'Can\'t open file';
            case 12:
                return 'Failure to create temporary file';
            case 13:
                return 'Zlib error';
            case 14:
                return 'Malloc failure';
            case 15:
                return 'Entry has been changed';
            case 16:
                return 'Compression method not supported';
            case 17:
                return 'Premature EOF';
            case 18:
                return 'Invalid argument';
            case 19:
                return 'Not a zip archive';
            case 20:
                return 'Internal error';
            case 21:
                return 'Zip archive inconsistent';
            case 22:
                return 'Can\'t remove file';
            case 23:
                return 'Entry has been deleted';
            default:
                return 'An unknown error has occurred('.intval($code).')';
        }
    }

    /**
     * Just do everything for me so I don't have to
     * think about it, but tell me where you put it.
     *
     * @return mixed
     */
    public static function laravel()
    {
        $package        = self::make();
        $vendorFileList = $package->collectComposerLibraries();
        $package->addCollection($vendorFileList);
        $package->addFile(base_path('/vendor/bin/bootstrap'),
            'bootstrap');
        $package->setPermissions(
            'bootstrap',
            FilePermissionCalculator::fromStringRepresentation('-r-xr-xr-x')
                ->getDecimal()
        );
        $phpConfig = storage_path('php/conf.d');
        if (File::isDirectory($phpConfig)) {
            $files = new Collection(File::allFiles($phpConfig));
            $files->each(function (SplFileInfo $file) use (&$package): void {
                $package->addFile($file->getRealPath(),
                    sprintf('php/conf.d/%s', $file->getBasename()));
            });
        }
        $package->close();

        return $package->getPath();
    }

    public static function make(string $filePath = ''): Archive
    {
        if (empty($filePath)) {
            $filePath = self::generateArchiveName();
        }

        return new static($filePath);
    }

    /**
     * Standardize the generation of the archive name.
     */
    protected static function generateArchiveName(): string
    {
        $archiveName = sprintf(
            '%s_%s_%s.zip',
            strtoupper(env('APP_NAME', 'default')),
            env('APP_VERSION', '0.0.1'),
            Carbon::now(env('APP_TIMEZONE', 'UTC'))->format('Y-m-d-H-i-s-u')
        );

        return storage_path($archiveName);
    }

    /**
     * We create a temporary directory to deploy composer vendor libraries too w/out and development libraries
     * We will deploy that.
     */
    public function collectComposerLibraries(): Collection
    {
        $tmpDir = \tempDir('serverlessVendor', true)->getPathname();
        copyFolder(base_path(), $tmpDir);
        rmFolder($tmpDir.'/vendor');
        $this->collectComposerFiles($tmpDir, 'composer.json');
        $this->collectComposerFiles($tmpDir, 'composer.lock');
        copyFolder(base_path('database/seeds'), $tmpDir.'/database/seeds');
        copyFolder(base_path('database/factories'),
            $tmpDir.'/database/factories');
        $cmdKey = config('composer.default');
        $cmd    = config('composer.'.$cmdKey);
        dd($cmd);
        $process = new Process($cmd);
        $process->setWorkingDirectory($tmpDir);
        $process->run();
        $process = new Process(['composer', 'dump-autoload']);
        $process->setWorkingDirectory($tmpDir);
        $process->run();

        rmFolder($tmpDir.'/database');

        return $this->getFileCollection($tmpDir);
    }

    protected function collectComposerFiles(
        string $tmpDir,
        string $source
    ): void {
        copy(base_path($source), sprintf('%s/%s', $tmpDir, $source));
    }

    /**
     * Works from a base directory and add all files that are not blacklisted.
     */
    public function getFileCollection(string $basePath): Collection
    {
        $fileList = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath,
                \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        return collect(iterator_to_array($fileList))->reject(
            function (\SplFileInfo $fileInfo, string $path) use ($basePath) {
                return $this->ignore($fileInfo, $path, $basePath);
            }
        )->mapWithKeys(
            function (\SplFileInfo $fileInfo, string $path) use ($basePath) {
                return $this->transform($fileInfo, $path, $basePath);
            }
        );
    }

    /**
     * Determines whether to ignore the file or path
     */
    protected function ignore(
        \SplFileInfo $fileInfo,
        string $path,
        string $basePath
    ): bool {
        foreach (config('bref.packaging.ignore') as $pattern) {
            if (strpos($pattern, base_path()) !== false) {
                $pattern = str_replace(base_path(), $basePath, $pattern);
            }

            if (! empty($pattern) && strpos($path, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transforms the iterator list into something usable for Archiving
     *
     * @return array
     */
    protected function transform(
        \SplFileInfo $fileInfo,
        string $path,
        string $basePath
    ): array {
        $key = ltrim(substr($path, strlen($basePath)), '/');

        /** The $key will be path inside the archive from the archive root. */
        return [
            $key =>
                collect([
                    'path'        => $fileInfo->getRealPath(),
                    'isDir'       => $fileInfo->isDir(),
                    'permissions' => $this->getPermissions($fileInfo, $key),
                ]),
        ];
    }

    protected function getPermissions(\SplFileInfo $fileInfo, string $key): int
    {
        $perms = $fileInfo->isDir()
            ?
            /** Directories get read/execute */
            FilePermissionCalculator::fromStringRepresentation('-r-xr-xr-x')
                ->getDecimal()
            :
            /** Every file defaults to read only (you can't write to the lambda package dir structure) */
            FilePermissionCalculator::fromStringRepresentation('-r--r--r--')
                ->getDecimal();
        /** If it is a configured Executable though, let us make it 555 as well.  */
        if (in_array($key, config('bref.packaging.executables'))) {
            $perms
                = FilePermissionCalculator::fromStringRepresentation('-r-xr-xr-x')
                ->getDecimal();
        }

        return $perms;
    }

    public function addCollection(Collection $collection): Archive
    {
        $collection->each(
            function ($data, $entryName): void {
                $entryName = 'laravel/'.$entryName;

                if ($data->get('isDir', false)) {
                    $this->addEmptyDir($entryName);
                } else {
                    $this->addFile($data->get('path'), $entryName);
                }
                $this->setPermissions($entryName, $data->get('permissions'));
            }
        );
        $this->reset();

        return $this;
    }

    /**
     * Add a directory to the archive
     *
     * @return $this
     */
    public function addEmptyDir(string $entryName): Archive
    {
        $res = $this->zipArchive->addEmptyDir($entryName);
        if ($res !== true) {
            throw new Package($this->zipArchive->getStatusString(), 66);
        }

        return $this;
    }

    /**
     * Add a file to the archive
     *
     * @return $this
     */
    public function addFile(string $path, string $entryName): Archive
    {
        $res = $this->zipArchive->addFile($path, $entryName);
        if ($res !== true) {
            throw new Package($this->zipArchive->getStatusString(), 66);
        }

        return $this;
    }

    public function setPermissions(string $entryName, int $permissions): Archive
    {
        $permissions = ($permissions & 0xffff) << 16;
        $this->zipArchive->setExternalAttributesName($entryName,
            \ZipArchive::OPSYS_UNIX, $permissions);

        return $this;
    }

    /**
     * Close and reopen archive to ensure we release file descriptors
     */
    public function reset(): Archive
    {
        $this->close();
        $this->open();

        return $this;
    }

    /**
     * Close the archive and release files.
     *
     * @return $this
     */
    public function close(): Archive
    {
        $res = $this->zipArchive->close();
        if ($res !== true) {
            throw new Package($this->zipArchive->getStatusString(), 66);
        }

        return $this;
    }

    /**
     * Opens the archive.
     *
     * @return $this
     */
    public function open(): Archive
    {
        $res = $this->zipArchive->open($this->path);
        if ($res !== true) {
            throw new Package($this->message($res), $res);
        }

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
