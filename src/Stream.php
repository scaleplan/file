<?php

namespace Scaleplan\File;

use Psr\Http\Message\StreamInterface;

/**
 * Class Stream
 *
 * @package Scaleplan\File
 */
class Stream implements StreamInterface
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var resource
     */
    protected $resource;

    /**
     * Stream constructor.
     *
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return resource
     */
    public function getResource()
    {
        if (null === $this->resource) {
            $this->resource = fopen($this->filename, 'cb+');
        }

        return $this->resource;
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return file_get_contents($this->filename);
    }

    public function close() : void
    {
        if (null !== $this->resource) {
            fclose($this->resource);
        }
    }

    public function detach() : void
    {
        $this->close();
    }

    /**
     * @return int|null
     */
    public function getSize() : ?int
    {
        return filesize($this->filename) ?: null;
    }

    /**
     * @return int
     */
    public function tell() : int
    {
        if ($this->resource) {
            $result = ftell($this->resource);
            if (false === $result) {
                throw new \RuntimeException('tell error.');
            }

            return $result;
        }

        return 0;
    }

    /**
     * @return bool
     */
    public function eof() : bool
    {
        return $this->resource ? feof($this->resource) : false;
    }

    /**
     * @return bool
     */
    public function isSeekable() : bool
    {
        return true;
    }

    /**
     * @param int $offset
     * @param int $whence
     */
    public function seek($offset, $whence = SEEK_SET) : void
    {
        fseek($this->getResource(), $offset, $whence);
    }

    public function rewind() : void
    {
        if ($this->resource) {
            rewind($this->resource);
        }
    }

    /**
     * @return bool
     */
    public function isWritable() : bool
    {
        return true;
    }

    /**
     * @param string $string
     *
     * @return int
     */
    public function write($string) : int
    {
        $result = fwrite($this->getResource(), $string);
        if (false === $result) {
            throw new \RuntimeException('Write error.');
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isReadable() : bool
    {
        return true;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function read($length) : string
    {
        $result = fread($this->getResource(), $length);
        if (false === $result) {
            throw new \RuntimeException('Write error.');
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getContents() : string
    {
        if ($this->resource) {
            $result = stream_get_contents($this->resource);
            if (false === $result) {
                throw new \RuntimeException('stream_get_contents error.');
            }

            return $result;
        }

        return $this->__toString();
    }

    /**
     * @param null $key
     *
     * @return array|mixed|null
     */
    public function getMetadata($key = null)
    {
        return $key
            ? stream_get_meta_data($this->getResource())[$key] ?? null
            : stream_get_meta_data($this->getResource());
    }
}
