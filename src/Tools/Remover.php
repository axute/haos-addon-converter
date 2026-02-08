<?php

namespace App\Tools;

use App\Addon\FilesReader;

class Remover
{
    public static function removeAddon(string $slug): void
    {
        // System addon cannot be deleted
        if ($slug === Converter::SLUG) {
            throw new \RuntimeException('System add-on cannot be deleted');
        }

        $addonDir = FilesReader::getAddonDir( $slug);

        if (!is_dir($addonDir)) {
            throw new \RuntimeException("$addonDir is not a directory");
        }

        // Verzeichnis rekursiv löschen
        self::recursiveRmdir($addonDir);
    }
    public static function recursiveRmdir($dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
                        self::recursiveRmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}