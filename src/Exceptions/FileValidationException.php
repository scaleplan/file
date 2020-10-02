<?php
declare(strict_types=1);

namespace Scaleplan\File\Exceptions;

/**
 * Class FileSaveException
 *
 * @package Scaleplan\File\Exceptions
 */
class FileValidationException extends FileException
{
    public const MESSAGE = 'helpers.file-validation-error';
    public const CODE = 422;
}
