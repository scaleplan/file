<?php

namespace Scaleplan\File;

use Scaleplan\File\Exceptions\FileReturnedException;
use Scaleplan\File\Exceptions\FileSaveException;
use Scaleplan\File\Exceptions\FileUploadException;
use Scaleplan\File\Exceptions\FileValidationException;
use Scaleplan\Helpers\Helper;
use function Scaleplan\Helpers\get_env;
use function Scaleplan\Helpers\get_required_env;
use function Scaleplan\Translator\translate;

/**
 * Хэлпер манипуляций над файлами
 *
 * Class FileHelper
 *
 * @package Scaleplan\File
 */
class FileHelper
{
    /**
     * Максимальный размер загружаемых файлов (в мегабатах)
     */
    public const FILE_UPLOAD_MAX_SIZE = 300;

    public const FREAD_DEFAULT_LENGTH = 1024;

    public const FILES_DIRECTORY_PATH = '/files';

    public const DIRECTORY_MODE = 0775;

    /**
     * Сохранить массив в csv-файл
     *
     * @param array $data - массив данных
     * @param string $filePath - путь к директории файла
     * @param string $fileName - имя файла для сохраниения
     *
     * @throws FileReturnedException
     */
    public static function returnASCSVFile(array $data, string $filePath, string $fileName = 'tmp.csv') : void
    {
        $fileName = $filePath . '/' . $fileName;

        static::returnFile($fileName);

        $fp = fopen($fileName, 'wb');
        //fputs($fp, '\xEF\xBB\xBF');
        foreach ($data as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);

        static::returnFile($fileName);

        throw new FileReturnedException();
    }

    /**
     * Вернуть файл пользователю
     *
     * @param string $filePath - путь к файлу
     */
    public static function returnFile(string $filePath) : void
    {
        if (file_exists($filePath)) {
            // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
            // если этого не сделать файл будет читаться в память полностью!
            if (ob_get_level()) {
                ob_end_clean();
            }
            // заставляем браузер показать окно сохранения файла
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($filePath));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            // читаем файл и отправляем его пользователю
            if ($fd = fopen($filePath, 'rb')) {
                while (!feof($fd)) {
                    print fread($fd, static::FREAD_DEFAULT_LENGTH);
                }

                fclose($fd);
            }

            exit;
        }
    }

    /**
     * @param array $file
     * @param string $uploadPath
     * @param int $index
     *
     * @return UploadFile|null
     *
     * @throws Exceptions\EnvNotFoundException
     * @throws FileSaveException
     * @throws \Throwable
     */
    public static function saveFile(array &$file, string &$uploadPath, int &$index = -1) : ?UploadedFile
    {
        if ($index >= 0) {
            $fn = &$file['name'][$index];
            $tn = &$file['tmp_name'][$index];
            $fe = &$file['error'][$index];
            $fs = &$file['size'][$index];
            $ft = &$file['type'][$index];
        } else {
            $fn = &$file['name'];
            $tn = &$file['tmp_name'];
            $fe = &$file['error'];
            $fs = &$file['size'];
            $ft = &$file['type'];
        }

        switch ($fe) {
            case UPLOAD_ERR_OK:
                break;

            case UPLOAD_ERR_NO_FILE:
                return null;

            case UPLOAD_ERR_INI_SIZE:
                throw new FileSaveException(translate('helpers.php-config-file-size-exceeded'), 413);

            case UPLOAD_ERR_FORM_SIZE:
                throw new FileSaveException(translate('helpers.form-file-size-exceeded'), 413);

            case UPLOAD_ERR_PARTIAL:
                throw new FileSaveException(translate('helpers.file-corrupted'), 400);

            case UPLOAD_ERR_NO_TMP_DIR:
                throw new FileSaveException(translate('helpers.tmp-not-found'), 500);

            case UPLOAD_ERR_CANT_WRITE:
                throw new FileSaveException(translate('helpers.file-write-failed', ['fn' => $fn,]), 500);

            case UPLOAD_ERR_EXTENSION:
                throw new FileSaveException(translate('helpers.php-stop-file-upload'), 500);
        }

        $nameArray = explode('.', $fn);
        $ext = strtolower(end($nameArray));
        $newName = preg_replace(
            '/[\s,\/:;\?!*&^%#@$|<>~`]/',
            '',
            str_replace(
                ' ',
                '_',
                str_replace($ext, '', $fn) . microtime(true)
            )
        );

        $fileMaxSizeMb = (int)(get_env('FILE_UPLOAD_MAX_SIZE') ?? static::FILE_UPLOAD_MAX_SIZE);

        if (!file_exists($tn)) {
            throw new FileSaveException(translate('helpers.tmp-file-not-found', ['tn' => $tn,]));
        }

        if (!is_uploaded_file($tn)) {
            throw new FileSaveException(translate('helpers.file-write-failed', ['fn' => $fn,]), 500);
        }

        if ((int)$fs > (1048576 * $fileMaxSizeMb)) {
            throw new FileSaveException(
                translate('helpers.config-file-size-exceeded', ['file-max-size-mb' => $fileMaxSizeMb,]),
                413
            );
        }

        if (!static::validateFileExt($ext)) {
            throw new FileSaveException(translate('helpers.file-extension-not-supported', ['ext' => $ext,]), 415);
        }

//            if (!($validExt = static::validateFileMimeType($tn))) {
//                throw new FileSaveException('Неподдерживаемый тип файла', 415);
//            }
//
//            if ($validExt !== $ext) {
//                $ext = $validExt;
//            }

        $newName = "$newName.$ext";
        $path = "$uploadPath/$newName";
        if (!copy($tn, $path)) {
            throw new FileSaveException(translate('helpers.file-saving-failed', ['fn' => $fn,]), 500);
        }

        $path = getenv('FILES_URL_PREFIX') . strtr(
                $path,
                [
                    get_required_env('BUNDLE_PATH')          => '',
                    get_required_env('FILES_DIRECTORY_PATH') => '',
                ]
            );

        return new UploadedFile($fn, $path, (int)$fs, $ft);
    }

    /**
     * Функция загрузки массива файлов на сервер
     *
     * @param array $files - массив файлов, которые прислала форма и мета-информация о них
     *
     * @return UploadFile[]
     *
     * @throws Exceptions\EnvNotFoundException
     * @throws Exceptions\HelperException
     * @throws FileSaveException
     * @throws FileUploadException
     * @throws \Throwable
     */
    public static function saveFiles(array $files) : array
    {
        $result = [];
        foreach ($files as $field => &$file) {
            $uploadPath = static::getFilePath($field);
            if (!is_dir($uploadPath)
                && !mkdir($uploadPath, static::DIRECTORY_MODE, true)
                && chmod($uploadPath, static::DIRECTORY_MODE)
            ) {
                throw new FileSaveException(translate('helpers.destination-dir-creating-error'), 500);
            }

            if (\is_array($file['name'])) {
                foreach ($file['name'] as $index => &$fn) {
                    if ($moveFile = static::saveFile($file, $uploadPath, $index)) {
                        $result[$field][] = $moveFile;
                    }
                }

                unset($fn);
            } elseif ($moveFile = static::saveFile($file, $uploadPath)) {
                $result[$field] = $moveFile;
            }
        }

        unset($file);
        return $result;
    }

    /**
     * Проверка расширения файла на возможность загрузки
     *
     * @param $extName - расширение файла
     *
     * @return bool
     *
     * @throws Exceptions\EnvNotFoundException
     * @throws Exceptions\HelperException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public static function validateFileExt(string &$extName) : bool
    {
        if (empty(Helper::getConf(get_required_env('EXTS_CONFIG_NAME'))[strtolower($extName)])) {
            return false;
        }

        return true;
    }

    /**
     * Проверка mime-типа файла
     *
     * @param string $filePath - путь к файлу
     *
     * @return string|null
     *
     * @throws Exceptions\EnvNotFoundException
     * @throws Exceptions\HelperException
     * @throws FileValidationException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public static function validateFileMimeType(string &$filePath) : ?string
    {
        if (!file_exists($filePath)) {
            throw new FileValidationException(translate('helpers.file-not-exist', ['file-path' => $filePath,]));
        }

        if (empty($validExt = Helper::getConf(get_required_env('MIMES_CONFIG_NAME'))[mime_content_type($filePath)])) {
            return null;
        }

        return $validExt;
    }

    /**
     * Возвращает путь к директории с заданным видом файлов
     *
     * @param string $fileKind - вид файлов
     *
     * @return string
     *
     * @throws Exceptions\EnvNotFoundException
     * @throws Exceptions\HelperException
     * @throws FileUploadException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public static function getFilePath(string $fileKind) : string
    {
        $locations = Helper::getConf(get_required_env('FILE_LOCATIONS_CONFIG_NAME'), false);
        $location = null;
        if (!\is_array($locations) || empty($location = $locations[$fileKind] ?? null)) {
            foreach (array_keys($locations) as $field) {
                if (@preg_match($field, $fileKind)) {
                    $location = $locations[$field];
                    break;
                }
            }
        }

        if (!$location) {
            throw new FileUploadException(
                translate('helpers.file-destination-path-not-set', ['file-kind' => $fileKind,])
            );
        }

        return get_required_env('BUNDLE_PATH')
            . get_required_env('FILES_DIRECTORY_PATH')
            . $location;
    }

    /**
     * Найти все файлы в каталоге, включая вложенные директории
     *
     * @param string $dirPath - путь к каталогу
     * @param array|null $extensions - фильтр по расширению файла
     *
     * @return array
     */
    public static function getRecursivePaths(string $dirPath, array $extensions = null) : array
    {
        if (!\is_dir($dirPath)) {
            return [];
        }

        $dirPath = rtrim($dirPath, '/\ ');
        $paths = \scandir($dirPath, SCANDIR_SORT_NONE);
        unset($paths[0], $paths[1]);
        $result = [];

        foreach ($paths as $path) {
            $path = "$dirPath/$path";
            if (!\is_dir($path)) {
                $result[] = $path;
                continue;
            }

            $result += array_map(static function ($item) use ($path) {
                return "$path/$item";
            }, static::getRecursivePaths($path));
        }

        if (null !== $extensions) {
            $result = preg_grep('~\.(' . implode('|', $extensions) . ')$~', $result);
        }

        return $result;
    }
}
