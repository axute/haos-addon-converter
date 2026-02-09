<?php

namespace App\Tools;

use App\Addon\FilesReader;
use App\Generator\HaConfig;
use Exception;
use Symfony\Component\Yaml\Yaml;

class Converter
{
    public const string REPOSOTIRY_PATH = 'axute/haos-addon-converter';
    public const string PUBLIC_IMAGE_NAME = 'ghcr.io/axute/haos-addon-converter';
    public const string SLUG = 'haos_addon_converter';

    public static function getTags(): array
    {
        $imageName = self::REPOSOTIRY_PATH;
        $tags = ['latest'];

        try {
            $tokenUrl = "https://ghcr.io/token?scope=repository:$imageName:pull&service=ghcr.io";
            $tokenJson = @file_get_contents($tokenUrl);
            if ($tokenJson) {
                $tokenData = json_decode($tokenJson, true);
                $token = $tokenData['token'] ?? '';

                if ($token) {
                    $tagsUrl = "https://ghcr.io/v2/$imageName/tags/list";
                    $opts = [
                        'http' => [
                            'method' => 'GET',
                            'header' => "Authorization: Bearer $token\r\n"
                        ]
                    ];
                    $context = stream_context_create($opts);
                    $tagsJson = @file_get_contents($tagsUrl, false, $context);
                    if ($tagsJson) {
                        $tagsData = json_decode($tagsJson, true);
                        if (isset($tagsData['tags']) && is_array($tagsData['tags'])) {
                            $tags = $tagsData['tags'];
                            // Sort tags, latest should be first or handled specially
                            rsort($tags);
                            // Ensure 'latest' is in there if not present (though it should be)
                            if (in_array('latest', $tags)) {
                                // Move latest to the front
                                $tags = array_diff($tags, ['latest']);
                            }
                            array_unshift($tags, 'latest');
                        }
                    }
                }
            }
        } catch (Exception) {
            // Fallback to ['latest']
        }
        return $tags;
    }

    public static function selfConvert(string $tag): array
    {
        $slug = self::SLUG;
        $configFile = FilesReader::getAddonDir($slug) . '/' . HaConfig::FILENAME;
        $currentVersion = '1.0.0';

        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
            $currentVersion = $config['version'] ?? '1.0.0';
        }

        // Daten für die Generierung vorbereiten
        $data = [
            'name'           => 'HAOS Add-on Converter',
            'image'          => self::PUBLIC_IMAGE_NAME . ":" . $tag,
            'description'    => 'Web-Converter zum Konvertieren von Docker-Images in Home Assistant Add-ons.',
            'version'        => $currentVersion,
            'url'            => 'https://github.com/axute/haos-addon-converter',
            'ingress'        => true,
            'ingress_port'   => 80,
            'ingress_entry'  => '/',
            'timeout'        => 20,
            'watchdog'       => 'http://[HOST]:[PORT:80]/',
            'ingress_stream' => false,
            'panel_icon'     => 'mdi:toy-brick',
            'panel_title'    => 'Addon Converter',
            'backup'         => 'hot',
            'self_convert'   => true,
            'map'            => [
                [
                    'folder' => 'addons',
                    'mode'   => 'rw'
                ]
            ],
            'env_vars'       => [
                [
                    'key'   => 'CONVERTER_DATA_DIR',
                    'value' => '/addons'
                ]
            ]
        ];

        // Icon hinzufügen falls vorhanden
        $iconPath = __DIR__ . '/../../icon.png';
        if (file_exists($iconPath)) {
            $iconData = file_get_contents($iconPath);
            $data['icon_file'] = 'data:image/png;base64,' . base64_encode($iconData);
        }
        return $data;
    }
}