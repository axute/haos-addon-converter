<?php

namespace App\Controllers;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;

abstract class ControllerAbstract
{
    protected function success(Response $response, array $result = []): MessageInterface|Response
    {
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    protected function errorMessage(Response $response, string $message, int $status = 400): MessageInterface|Response
    {
        $result = ['status'  => 'error',
                   'message' => $message
        ];
        return $this->error($response, $result, $status);
    }

    protected function error(Response $response, array $result, int $status = 400): MessageInterface|Response
    {
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}