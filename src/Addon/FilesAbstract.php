<?php

namespace App\Addon;

abstract class FilesAbstract
{
    public static function getAddonDir(string $slug): string
    {
        return self::getDataDir() . '/' . $slug;
    }

    public static function getDataDir(): string
    {
        return str_replace('\\', '/', getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data');
    }
}