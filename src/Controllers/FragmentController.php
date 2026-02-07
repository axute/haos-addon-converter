<?php

namespace App\Controllers;

use App\Addon\FilesReader;
use App\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Yaml\Yaml;

class FragmentController extends ControllerAbstract
{

    private function render(Response $response, string $template, array $data = []): Response
    {
        $view = new View();
        $html = $view->render('fragments/' . $template . '.html.twig', $data);
        $response->getBody()->write($html);
        return $response;
    }

    public function addonList(Request $request, Response $response): Response
    {
        $addonController = new AddonController();
        $tempResponse = new \Slim\Psr7\Response();
        $listResponse = $addonController->list($request, $tempResponse);
        $data = json_decode((string)$listResponse->getBody(), true);

        return $this->render($response, 'addon-list', [
            'addons' => $data['addons'] ?? [],
            'repository' => $data['repository'] ?? null
        ]);
    }

    public function addonDetails(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $details = (new FilesReader($slug))->jsonSerialize();
        return $this->render($response, 'addon-details', [
            'addon' => $details,
            'slug' => $slug
        ]);
    }

    public function checkUpdate(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $force = $request->getQueryParams()['force'] ?? null;
        
        $addonController = new AddonController();
        $tempResponse = new \Slim\Psr7\Response();
        
        // Query Parameter an den AddonController weiterreichen
        if ($force) {
            $request = $request->withQueryParams(['force' => $force]);
        }
        
        $updateResponse = $addonController->checkImageUpdate($request, $tempResponse, ['slug' => $slug]);
        $updateData = json_decode((string)$updateResponse->getBody(), true);

        return $this->render($response, 'update-status', [
            'update' => $updateData,
            'slug' => $slug
        ]);
    }
}
