<?php

namespace App\Tools;

use App\Generator\HaConfig;
use RuntimeException;

class Crane
{
    public static function getConfig(string $image)
    {
        $command = "crane config " . escapeshellarg($image) . " 2>&1";
        $output = shell_exec($command);
        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }
        return $data;
    }

    public static function getUpdateDetailed(string $image, string $tag): array
    {
        // 1. Alle Tags abrufen, um nach neueren Versionen mit gleicher Major/Minor zu suchen
        $allTags = self::getTags($image);
        $result = [
            'fix'   => null,
            'minor' => null,
            'major' => null
        ];
        if (!empty($allTags)) {
            // Wenn der aktuelle Tag eine Version ist (z.B. 1.2.3)
            if (preg_match('/^v?(\d+)\.(\d+)(\.\d+)?(-.+)?$/', $tag, $currentMatches)) {
                $major = $currentMatches[1];
                $minor = $currentMatches[2];

                foreach ($allTags as $t) {
                    if (self::sameSchema($t, $tag) === false) {
                        continue;
                    }
                    if (preg_match('/^v?(\d+)\.(\d+)(\.\d+)?(-.+)?$/', $t, $tMatches)) {
                        if ($tMatches[1] == $major && $tMatches[2] == $minor) {
                            if (version_compare($t, $tag, '>')) {
                                $result['fix'] = $t;
                            }
                        } else if ($tMatches[1] == $major) {
                            if (version_compare($t, $tag, '>')) {
                                $result['minor'] = $t;
                            }
                        } else if ($tMatches[1] > $major) {
                            $result['major'] = $t;
                        }
                    }
                }
            }
        }
        return $result;
    }

    public static function getTags(string $image): array
    {
        // crane ls verwenden
        $command = "crane ls " . escapeshellarg($image) . " 2>&1";
        $output = shell_exec($command);
        $tags = explode("\n", trim($output));
        return array_filter($tags, function ($tag) {
            return !empty($tag) && !str_contains($tag, "error") && !str_contains($tag, "standard_init_linux");
        });
    }

    protected static function sameSchema(string $tag1, string $tag2)
    {
        if (str_starts_with($tag1, 'v') && str_starts_with($tag2, 'v') === false) {
            return false;
        }
        if (str_starts_with($tag1, 'v') === false && str_starts_with($tag2, 'v')) {
            return false;
        }
        $match1 = preg_match('/^v?(\d+)\.(\d+)(\.\d+)?(-.+)?$/', $tag1, $parts1);
        $match2 = preg_match('/^v?(\d+)\.(\d+)(\.\d+)?(-.+)?$/', $tag2, $parts2);
        if ($match1 !== $match2) {
            return false;
        }
        if (count($parts1) !== count($parts2)) {
            return false;
        }
        if (count($parts1) === 5 && $parts1[4] !== $parts2[4]) {
            return false;
        }
        return true;
    }

    public static function getArchitectures(string $fullImage): array
    {
        $command = "crane manifest " . escapeshellarg($fullImage) . " 2>&1";
        $output = shell_exec($command);
        $data = json_decode($output, true);
        $allowedArchitectures = HaConfig::ARCHITECTURES;
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }
        if (array_key_exists('manifest', $data)) {
            $foundArchitectures = [];
            foreach ($data['manifest'] as $manifest) {
                $architecture = $manifest['platform']['architecture'] ?? null;
                $os = $manifest['platform']['os'] ?? null;
                $variant = $manifest['platform']['variant'] ?? null;
                if ($architecture === null || $os !== 'linux') {
                    continue;
                }
                if (in_array($architecture, $allowedArchitectures)) {
                    $foundArchitectures[] = $architecture;
                } else if (in_array($architecture . $variant, $allowedArchitectures)) {
                    $foundArchitectures[] = $architecture . $variant;
                }
            }
            return $foundArchitectures;
        }
        if (array_key_exists('architecture', $data) && in_array($data['architecture'], $allowedArchitectures)) {
            return [$data['architecture']];
        }
        return [];
    }
}