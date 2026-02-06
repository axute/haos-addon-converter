<?php

namespace App\Controllers;

use App\Generator\{Dockerfile, HAconfig, HArepository, Metadata};
use Psr\Http\Message\{ResponseInterface as Response, ServerRequestInterface as Request};

class GenerateController
{
    private function getDataDir(): string
    {
        return getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data';
    }

    public function generate(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        $addonName = $data['name'] ?? '';
        $image = $data['image'] ?? '';
        $image_tag = $data['image_tag'] ?? '';
        if (!empty($image_tag) && !str_contains($image, ':')) {
            $image .= ':' . $image_tag;
        }

        if (empty($addonName) || empty($image)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Name and image are required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $isSelfConvert = $data['self_convert'] ?? false;
        $slug = $this->generateSlug($addonName, $isSelfConvert);

        $dataDir = str_replace('\\', '/', $this->getDataDir());
        $addonPath = $dataDir . '/' . $slug;

        if (!is_dir($addonPath)) {
            mkdir($addonPath, 0777, true);
        }

        // Metadaten initial speichern/laden
        $imageConfig = $this->getImageConfig($image);
        $origEntrypoint = $imageConfig['config']['Entrypoint'] ?? null;
        $origCmd = $imageConfig['config']['Cmd'] ?? null;
        $addonMetadata = new Metadata();
        $addonMetadata->add('detected_pm', $data['detected_pm'] ?? null);
        $addonMetadata->add('quirks', $data['quirks'] ?? false);
        $addonMetadata->add('allow_user_env', $data['allow_user_env'] ?? false);
        $addonMetadata->add('bashio_version', $data['bashio_version'] ?? '0.17.5');
        $addonMetadata->add('has_startup_script', !empty($data['startup_script'] ?? ''));
        $addonMetadata->add('original_entrypoint', $origEntrypoint);
        $addonMetadata->add('original_cmd', $origCmd);
        $this->saveMetadata($addonPath, $addonMetadata);

        // Dateien generieren
        $this->generateConfigYaml($addonPath, $data, $slug);
        $this->handleIcon($addonPath, $data['icon_file'] ?? '');
        $this->generateReadme($addonPath, $data);
        $this->handleHelperFiles($addonPath, $data, $image, $origEntrypoint, $origCmd);
        $this->generateDockerfile($addonPath, $data, $image);

        // repository.yaml im Haupt-data-Verzeichnis erstellen/aktualisieren
        $this->ensureRepositoryYaml($dataDir);

        $result = [
            'status' => 'success',
            'path' => realpath($addonPath)
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function generateSlug(string $addonName, bool $isSelfConvert): string
    {
        if ($isSelfConvert) {
            return 'haos_addon_converter';
        }

        $slug = strtolower($addonName);
        $slug = str_replace([' ', '-', '.'], '_', $slug);
        $slug = preg_replace('/[^a-z0-9_]/', '', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        return trim($slug, '_');
    }

    private function generateConfigYaml(string $addonPath, array $data, string $slug): void
    {
        $haConfig = new HAconfig(
            $data['name'], $data['version'] ?? '1.0.0', $slug, $data['description'] ?? 'Converted HA Add-on',
        );
        $haConfig->addEnvironment('HAOS_CONVERTER_BASHIO_VERSION', $data['bashio_version'] ?? '0.17.5');

        if (!empty($data['detected_pm'])) {
            $haConfig->addEnvironment('HAOS_CONVERTER_PM', $data['detected_pm']);
        }

        if (!empty($data['ingress'])) {
            $haConfig->setIngress(
                port: $data['ingress_port'] ?? 80,
                stream: !empty($data['ingress_stream']),
                icon: $data['panel_icon'] ?? null);
        } elseif (!empty($data['webui_port'])) {
            $haConfig->setWebUI(port: $data['webui_port']);
        }

        if (isset($data['backup'])) {
            $haConfig->setBackup($data['backup']);
        }

        if (!empty($data['map'])) {
            foreach ($data['map'] as $map) {
                /** @var string[] $map */
                $haConfig->addMap(type: $map[0], readOnly: $map[1] === 'ro');
            }
        }

        if (!empty($data['ports'])) {
            foreach ($data['ports'] as $p) {
                if (!empty($p['container'])) {
                    $haConfig->addPort($p['container'], $p['host'] ?? null);
                }
            }
        }

        if (!empty($data['env_vars']) || !empty($data['allow_user_env'])) {

            foreach ($data['env_vars'] ?? [] as $var) {
                if (!empty($var['key'])) {
                    $key = $var['key'];
                    $value = $var['value'] ?? '';
                    $editable = $var['editable'] ?? false;

                    if (!empty($data['quirks']) && $editable) {
                        $haConfig->addOption($key, $value, 'str?');
                    } else {
                        $haConfig->addEnvironment($key, $value);
                    }
                }
            }

            if (!empty($data['allow_user_env'])) {
                $haConfig->addOption('env_vars', [], ['str']);
            }
        }

        file_put_contents($addonPath . '/' . HAconfig::FILENAME, $haConfig);
    }

    private function handleIcon(string $addonPath, string $iconFile): void
    {
        if (!empty($iconFile)) {
            if (preg_match('/^data:image\/(\w+);base64,/', $iconFile, $type)) {
                $iconData = substr($iconFile, strpos($iconFile, ',') + 1);
                $iconData = base64_decode($iconData);
                if ($iconData !== false) {
                    file_put_contents($addonPath . '/icon.png', $iconData);
                }
            }
        }
    }

    private function generateReadme(string $addonPath, array $data): void
    {
        $longDescription = $data['long_description'] ?? '';
        $addonName = $data['name'];
        $description = $data['description'] ?? 'Converted HA Add-on';

        if (!empty($longDescription)) {
            $readmeContent = $longDescription;
            if (file_exists($addonPath . '/icon.png') && !str_contains($readmeContent, '![Logo](icon.png)')) {
                $readmeContent = "![Logo](icon.png)\n\n" . $readmeContent;
            }
            file_put_contents($addonPath . '/README.md', $readmeContent);
        } elseif (file_exists($addonPath . '/icon.png')) {
            file_put_contents($addonPath . '/README.md', "![Logo](icon.png)\n\n# $addonName\n\n$description");
        }
    }

    private function handleHelperFiles(string $addonPath, array $data, string $image, $origEntrypoint, $origCmd): void
    {
        [$quirks, $hasEditableEnv] = $this->getQuirksConfig($data);
        $allowUserEnv = $data['allow_user_env'] ?? false;
        $startupScript = $data['startup_script'] ?? '';


        $needsRunSh = ($quirks && ($hasEditableEnv || !empty($startupScript) || $allowUserEnv)) || $allowUserEnv;

        if ($needsRunSh) {
            copy(__DIR__ . '/../../helper/run.sh', $addonPath . '/run.sh');
            chmod($addonPath . '/run.sh', 0755);

            file_put_contents($addonPath . '/original_entrypoint', (is_array($origEntrypoint) && !empty($origEntrypoint)) ? implode(' ', $origEntrypoint) : ($origEntrypoint ?? ''));
            file_put_contents($addonPath . '/original_cmd', (is_array($origCmd) && !empty($origCmd)) ? implode(' ', $origCmd) : ($origCmd ?? ''));

            if (!empty($startupScript)) {
                file_put_contents($addonPath . '/start.sh', $startupScript);
                chmod($addonPath . '/start.sh', 0755);
            }
        }
    }

    private function generateDockerfile(string $addonPath, array $data, string $image): void
    {
        [$quirks, $hasEditableEnv] = $this->getQuirksConfig($data);
        $allowUserEnv = $data['allow_user_env'] ?? false;
        $startupScript = $data['startup_script'] ?? '';


        $dockerfile = new Dockerfile($image);

        $needsRunSh = ($quirks && ($hasEditableEnv || !empty($startupScript) || $allowUserEnv)) || $allowUserEnv;

        if ($needsRunSh) {
            if ($allowUserEnv) {
                $dockerfile->addCommand('COPY --from=hairyhenderson/gomplate:stable /gomplate /bin/gomplate');
            }

            $dockerfile->addCommand("\n# Add wrapper script");
            $dockerfile->addCommand("COPY run.sh /run.sh");
            $dockerfile->addCommand("RUN chmod +x /run.sh");

            $dockerfile->addCommand("\n# Add stored original entrypoint/cmd");
            $dockerfile->addCommand("COPY original_entrypoint /run/original_entrypoint");
            $dockerfile->addCommand("COPY original_cmd /run/original_cmd");

            if (!empty($startupScript)) {
                $dockerfile->addCommand("\n# Add startup script");
                $dockerfile->addCommand("COPY start.sh /start.sh");
                $dockerfile->addCommand("RUN chmod +x /start.sh");
            }

            $dockerfile->addCommand("\nENTRYPOINT [\"/run.sh\"]");
            $dockerfile->addCommand("CMD []");
        }

        file_put_contents($addonPath . '/' . Dockerfile::FILENAME, (string)$dockerfile);
    }

    private function getImageConfig(string $image): array
    {
        $command = "crane config " . escapeshellarg($image) . " 2>&1";
        $output = shell_exec($command);
        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        return $data;
    }

    private function ensureRepositoryYaml($dataDir): void
    {
        $repoFile = $dataDir . '/' . HArepository::FILENAME;
        if (!file_exists($repoFile)) {
            $haRepository = new HARepository('My HAOS Add-on Repository');
            $haRepository->setMaintainer('HAOS Add-on Converter');
            file_put_contents($repoFile, $haRepository);
        }
    }

    private function saveMetadata(string $addonPath, Metadata $newMetadata): void
    {
        $metadataFile = $addonPath . '/' . Metadata::FILENAME;
        $oldMetadata = [];
        if (file_exists($metadataFile)) {
            $oldMetadata = json_decode(file_get_contents($metadataFile), true) ?: [];
        }
        $metadata = array_merge($oldMetadata, $newMetadata->getAll());

        file_put_contents($metadataFile, new Metadata($metadata));
    }

    public function getQuirksConfig(array $data): array
    {
        $quirks = $data['quirks'] ?? false;
        $hasEditableEnv = false;
        foreach ($data['env_vars'] ?? [] as $var) {
            if (!empty($var['editable'])) {
                $hasEditableEnv = true;
                break;
            }
        }
        return [$quirks, $hasEditableEnv];
    }
}
