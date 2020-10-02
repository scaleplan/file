<?php
declare(strict_types=1);

namespace Scaleplan\File\Exceptions;

/**
 * Class FileUploadException
 *
 * @package Scaleplan\File\Exceptions
 */
class FileUploadException extends FileException
{
    public const MESSAGE = 'helpers.file-upload-error';
    public const CODE = 500;
}
