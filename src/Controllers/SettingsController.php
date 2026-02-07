<?php

namespace App\Controllers;

use App\Addon\FilesReader;
use App\Generator\HaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends ControllerAbstract
{

    public function get(Request $request, Response $response): Response
    {
        $repoFile = FilesReader::getDataDir() . '/' . HaRepository::FILENAME;
        $haRepository = new HaRepository('My HAOS Add-on Repository');
        $haRepository->setMaintainer('HAOS Add-on Converter');

        if (file_exists($repoFile)) {
            $existing = HaRepository::fromFile($repoFile);
            $haRepository->setName($existing->getName());
            $haRepository->setMaintainer($existing->getMaintainer());
            $haRepository->setUrl($existing->getUrl());
        }
        return $this->success($response, $haRepository->jsonSerialize());
    }

    public function update(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        $name = $data['name'] ?? 'My HAOS Add-on Repository';
        $maintainer = $data['maintainer'] ?? 'HAOS Add-on Converter';
        $url = $data['url'] ?? null;

        $dataDir = FilesReader::getDataDir();
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }

        $repoFile = $dataDir . '/' . HaRepository::FILENAME;
        $haRepository = new HaRepository($name);
        $haRepository->setMaintainer($maintainer);
        if (!empty($url)) {
            $haRepository->setUrl($url);
        }

        file_put_contents($repoFile, $haRepository);
        return $this->success($response,['status'=>'success']);
    }
}
