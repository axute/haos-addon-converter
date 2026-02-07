<?php

namespace App\Controllers;

use App\Addon\{FilesReader, FilesWriter};
use App\Generator\{Dockerfile, HaRepository};
use App\Tools\{Bashio, Converter, Crane, Remover, Scripts};
use Exception;
use Psr\Http\Message\{ResponseInterface as Response, ServerRequestInterface as Request};

class AddonController extends ControllerAbstract
{
    public function list(Request $request, Response $response): Response
    {
        $dataDir = FilesReader::getDataDir();
        $addons = [];

        if (is_dir($dataDir)) {
            $dirs = array_filter(glob($dataDir . '/*'), 'is_dir');
            foreach ($dirs as $dir) {
                $slug = basename($dir);
                try {
                    $reader = new FilesReader($slug);
                    $addons[] = $reader->jsonSerialize();
                } catch (Exception) {
                    continue;
                }
            }
        }

        // Alphabetisch sortieren nach Name
        usort($addons, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $repository = HaRepository::getInstance()?->jsonSerialize();
        return $this->success($response, [
            'addons'     => $addons,
            'repository' => $repository
        ]);
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $reader = new FilesReader($args['slug']);
            return $this->success($response, $reader->jsonSerialize());
        } catch (Exception $e) {
            return $this->errorMessage($response, $e->getMessage());
        }
    }

    public function getImageTags(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $image = $queryParams['image'] ?? '';

        if (empty($image)) {
            return $this->success($response);
        }
        $tags = Crane::getTags($image);

        if (empty($tags)) {
            return $this->success($response, ['latest']);
        }

        // Tags nach Version sortieren (neueste oben)
        usort($tags, function ($a, $b) {
            if ($a === 'latest') return -1;
            if ($b === 'latest') return 1;

            // Handle versions like "1.2.3" vs "1.2"
            $a_v = preg_replace('/[^0-9.]/', '', $a);
            $b_v = preg_replace('/[^0-9.]/', '', $b);

            if ($a_v && $b_v && $a_v !== $b_v) {
                return version_compare($b_v, $a_v);
            }

            // Fallback: SHA-Tags ans Ende sortieren
            $a_is_sha = (str_starts_with($a, 'sha256-'));
            $b_is_sha = (str_starts_with($b, 'sha256-'));
            if ($a_is_sha && !$b_is_sha) return 1;
            if (!$a_is_sha && $b_is_sha) return -1;

            return strcasecmp($b, $a);
        });
        return $this->success($response, array_values($tags));
    }

    public function getTags(Request $request, Response $response): Response
    {
        return $this->success($response, Converter::getTags());
    }

    public function detectPackageManager(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $image = $queryParams['image'] ?? '';
        $tag = $queryParams['tag'] ?? 'latest';

        if (empty($image)) {
            return $this->success($response, ['pm' => 'unknown']);
        }

        $fullImage = $image . ($tag ? ':' . $tag : '');
        $cache = Scripts::getDetectPMCache();

        if (isset($cache[$fullImage])) {
            return $this->success($response, [
                'pm'     => $cache[$fullImage],
                'cached' => true
            ]);
        }

        // Cache speichern
        $pm = Scripts::detectPM($fullImage);
        $cache[$fullImage] = $pm;
        Scripts::setDetectPmCache($cache);
        return $this->success($response, ['pm' => $pm]);
    }

    public function selfConvert(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $params = json_decode($body, true);
        $tag = $params['tag'] ?? 'latest';

        try {
            $addonFiles = new FilesWriter(Converter::selfConvert($tag));
            $result = $addonFiles->create();
            return $this->success($response, $result);
        } catch (Exception $exception) {
            return $this->errorMessage($response, $exception->getMessage());
        }
    }

    public function getBashioVersions(Request $request, Response $response): Response
    {
        return $this->success($response, Bashio::getVersions());
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];

        // System addon cannot be deleted
        if ($slug === 'haos_addon_converter') {
            return $this->errorMessage($response, 'System add-on cannot be deleted', 403);
        }
        try {
            Remover::removeAddon($slug);
            return $this->success($response, ['status' => 'success']);
        } catch (Exception $e) {
            return $this->errorMessage($response, $e->getMessage());
        }
    }


    public function checkImageUpdate(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $dataDir = FilesReader::getDataDir();
        try {
            $addon = new FilesReader($slug);
            $image = $addon->getImage();
            $tag = $addon->getImageTag();
        } catch (Exception $e) {
            return $this->errorMessage($response, $e->getMessage());
        }

        if (empty($image)) {
            return $this->errorMessage($response, 'Could not detect image in Dockerfile', 200);
        }

        $fullImage = $image . ':' . $tag;
        $cacheFile = $dataDir . '/.cache/update_check_' . md5($fullImage) . '.json';
        $force = $request->getQueryParams()['force'] ?? false;

        // Cache für 6 Stunden (außer force=1)
        if (!$force && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 21600)) {
            return $this->success($response, json_decode(file_get_contents($cacheFile), true));
        }

        $result = [
            'status'      => 'success',
            'has_update'  => false,
            'new_tag'     => null,
            'image'       => $fullImage,
            'current_tag' => $tag
        ];
        $updates = Crane::getUpdateDetailed($image, $tag);
        $result = array_merge($result, $updates);
        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0777, true);
        }
        file_put_contents($cacheFile, json_encode($result));
        return $this->success($response, $result);
    }
}
