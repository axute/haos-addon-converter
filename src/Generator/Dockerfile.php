<?php

namespace App\Generator;

class Dockerfile implements \Stringable
{
    public const string FILENAME = 'Dockerfile';
    protected string $content = '';
    public function __construct(protected string $image)
    {
        $this->addCommand('FROM ' . $this->image);
    }
    public function addCommand(string $command): void
    {
        $this->content .= "\n" . $command;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}