<?php

namespace App\Controllers;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

abstract class ControllerAbstract
{
    protected function success(Response $response, array $result = []): MessageInterface|Response
    {
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    protected function debug(Response $response, mixed $data = []): MessageInterface|Response {
        $result = ['status'  => 'error',
                   'message' => 'see console for details',
                   'details' => $data,
        ];
        return $this->error($response, $result);
    }
    protected function errorMessage(Response $response, string|Throwable $message, int $status = 400): MessageInterface|Response
    {
        if($message instanceof Throwable) {
            if(getenv('HAOS_DEBUG') !== null && in_array(getenv('HAOS_DEBUG'), ['true', true, '1',1], true)) {
                $message = (string)$message;
            } else {
                $message = $message->getMessage();
            }
        }
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