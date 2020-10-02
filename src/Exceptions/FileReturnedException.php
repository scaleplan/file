<?php
declare(strict_types=1);

namespace Scaleplan\File\Exceptions;

/**
 * Class FileReturnedException
 *
 * @package Scaleplan\File\Exceptions
 */
class FileReturnedException extends FileException
{
    public const MESSAGE = 'helpers.file-creating-error';
    public const CODE = 500;
}
