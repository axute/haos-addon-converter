<?php

namespace App\Controllers;

use App\Addon\FilesWriter;
use Psr\Http\Message\{ResponseInterface as Response, ServerRequestInterface as Request};

class GenerateController extends ControllerAbstract
{

    public function generate(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        try {
            $addonFiles = new FilesWriter($data);
            $result = $addonFiles->create();
            return $this->success($response, $result);
        } catch (\Exception $exception) {
            return $this->errorMessage($response, $exception->getMessage());
        }
    }

}
