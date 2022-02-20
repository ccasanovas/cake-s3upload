<?php
namespace Ccasanovas\S3UploadSDK\File\Writer;

use Josegonzalez\Upload\File\Writer\DefaultWriter;
use League\Flysystem\FilesystemInterface;

class AwsS3Writer extends DefaultWriter
{

    /**
    extended to change how the name of the tempPath is created.
    the DefaultWriter appends a .temp suffix that breaks the way the
    AwsS3Adapter figures out the mimetype, so this writer changes it to a prefix.
     */
    public function writeFile(FilesystemInterface $filesystem, $file, $path)
    {
        $stream = @fopen($file, 'r');
        if ($stream === false) {
            return false;
        }

        $success = false;

        //changed start
        $pinfo = pathinfo($path);
        $tempPath = $pinfo['dirname'] . DS . 'temp.' . $pinfo['basename'];
        //change end

        $this->deletePath($filesystem, $tempPath);
        if ($filesystem->writeStream($tempPath, $stream)) {
            $this->deletePath($filesystem, $path);
            $success = $filesystem->rename($tempPath, $path);
        }
        $this->deletePath($filesystem, $tempPath);
        is_resource($stream) && fclose($stream);

        return $success;
    }
}
