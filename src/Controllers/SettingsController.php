<?php

namespace App\Controllers;

use App\Generator\HArepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController
{
    private function getDataDir(): string
    {
        return getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data';
    }

    public function get(Request $request, Response $response): Response
    {
        $dataDir = $this->getDataDir();
        $repoFile = $dataDir . '/' . HArepository::FILENAME;
        $haRepository = new HArepository('My HAOS Add-on Repository');
        $haRepository->setMaintainer('HAOS Add-on Converter');

        if (file_exists($repoFile)) {
            $existing = HArepository::fromFile($repoFile);
            $haRepository->setMaintainer($existing->getMaintainer());
            $haRepository->setUrl($existing->getUrl());
        }

        $response->getBody()->write(json_encode($haRepository));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        $name = $data['name'] ?? 'My HAOS Add-on Repository';
        $maintainer = $data['maintainer'] ?? 'HAOS Add-on Converter';

        $dataDir = $this->getDataDir();
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }

        $repoFile = $dataDir . '/' . HArepository::FILENAME;
        $haRepository = new HArepository($name);
        $haRepository->setMaintainer($maintainer);

        file_put_contents($repoFile, $haRepository);

        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
