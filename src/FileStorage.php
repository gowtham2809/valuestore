<?php


namespace Spatie\Valuestore;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Dotenv\Dotenv;

class FileStorage
{

    private $bucket = null;

    public function __construct()
    {
        if (!$this->getEnv('FILESYSTEM_DRIVER')) {
            $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
            $vendorDir = dirname($reflection->getFileName(), 3);
            $dotenv = Dotenv::createImmutable($vendorDir);
            $dotenv->load();
        }
    }

    public static function getBasePath()
    {
        $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
        $vendorDir = dirname($reflection->getFileName(), 3);
        return $vendorDir;
    }

    public static function getContent(string $fileName)
    {
        $driver = (new static())->getEnv('FILESYSTEM_DRIVER');
        switch ($driver) {
            case 's3':
                return (new static())->getFileFromS3($fileName);
            default:
                return (new static())->getFileFromLocal($fileName);
        }
    }

    public static function setContent(string $fileName, $content)
    {
        $driver = (new static())->getEnv('FILESYSTEM_DRIVER');
        switch ($driver) {
            case 's3':
                (new static())->putFileToS3($fileName, $content);
                break;
            default:
                (new static())->putFileToLocal($fileName, $content);
        }
    }

    public static function fileExists(string $fileName)
    {
        $driver = (new static())->getEnv('FILESYSTEM_DRIVER');
        switch ($driver) {
            case 's3':
                return (new static())->fileExistsS3($fileName);
            default:
                return (new static())->fileExistsLocal($fileName);
        }
    }

    public static function deleteFile(string $fileName)
    {
        $driver = (new static())->getEnv('FILESYSTEM_DRIVER');
        switch ($driver) {
            case 's3':
                (new static())->deleteFileS3($fileName);
                break;
            default:
                (new static())->deleteFileLocal($fileName);
        }
    }

    private function fileExistsS3(string $fileName)
    {
        try {
            $s3 = $this->getS3Client();
            return $s3->doesObjectExist($this->bucket, $fileName) ? true : false;
        } catch (S3Exception $e) {
            throw $e;
        }
    }

    private function fileExistsLocal($fileName)
    {
        return file_exists($fileName);
    }

    private function getFileFromS3(string $fileName)
    {
        try {
            $s3 = $this->getS3Client();
            $result = $s3->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $fileName
            ]);
            $content = $result['Body']->getContents();
            return $content;
        } catch (S3Exception $e) {
            throw $e;
        }
        return null;
    }

    private function getFileFromLocal(string $fileName)
    {
        return file_get_contents($fileName);
    }

    private function putFileToS3(string $fileName, $content)
    {
        try {
            $s3 = $this->getS3Client();
            $s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $fileName,
                'Body' => $content
            ]);
        } catch (S3Exception $e) {
            throw $e;
        }
    }

    private function putFileToLocal(string $fileName, $content)
    {
        file_put_contents($fileName, $content);
    }

    private function deleteFileS3(string $fileName)
    {
        try {
            $s3 = $this->getS3Client();
            $s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $fileName,
            ]);
        } catch (S3Exception $e) {
            throw $e;
        }
    }

    private function deleteFileLocal(string $fileName)
    {
        unlink($fileName);
    }

    private function getS3Client()
    {
        $client = new S3Client([
            'version' => 'latest',
            'region' => $this->getEnv('AWS_DEFAULT_REGION')
        ]);

        $this->bucket = $this->getEnv('AWS_BUCKET');

        return $client;
    }

    private function getEnv($key)
    {
        return getEnv($key);
    }
}
