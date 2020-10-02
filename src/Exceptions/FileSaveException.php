<?php
declare(strict_types=1);

namespace Scaleplan\File\Exceptions;

/**
 * Class FileSaveException
 *
 * @package Scaleplan\File\Exceptions
 */
class FileSaveException extends FileException
{
    public const MESSAGE = 'helpers.file-saving-error';
    public const CODE = 500;
}
