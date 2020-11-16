<?php

namespace Scaleplan\File;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Class UploadedFile
 *
 * @package Scaleplan\File
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var string
     */
    protected $type;

    /**
     * UploadFile constructor.
     *
     * @param string $name
     * @param string $path
     * @param int $size
     * @param string $type
     */
    public function __construct(string $name, string $path, int $size, string $type)
    {
        $this->name = $name;
        $this->path = $path;
        $this->size = $size;
        $this->type = $type;
    }

    /**
     * @return \Psr\Http\Message\StreamInterface|Stream
     */
    public function getStream()
    {
        return new Stream($this->path);
    }

    /**
     * @param string $targetPath
     */
    public function moveTo($targetPath) : void
    {
        if (false === is_dir(dirname($targetPath))) {
            throw new \InvalidArgumentException(
                'Invalid path provided for move operation; must be a non-empty string'
            );
        }

        $result = php_sapi_name() === PHP_SAPI
            ? rename($this->path, $targetPath)
            : move_uploaded_file($this->path, $targetPath);

        if (false === $result) {
            throw new \RuntimeException(
                sprintf('Uploaded file could not be moved to %s', $targetPath)
            );
        }
    }

    /**
     * @return int
     */
    public function getSize() : int
    {
        return $this->size;
    }

    /**
     * @return int
     */
    public function getError() : int
    {
        return UPLOAD_ERR_OK;
    }

    /**
     * @return string
     */
    public function getClientFilename() : string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getClientMediaType() : string
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function toArray() : array
    {
        return ['name' => $this->name, 'path' => $this->path,];
    }
}
