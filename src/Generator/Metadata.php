<?php

namespace App\Generator;

use JsonSerializable;
use Stringable;

class Metadata implements JsonSerializable, Stringable
{
    public const string FILENAME = 'metadata.json';
    public function __construct(protected array $data = []) {

    }
    public function add(string $key, $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
    public function has(string $key): bool {
        return array_key_exists($key, $this->data);
    }
    public function remove(string $key): static {
        unset($this->data[$key]);
        return $this;
    }
    public function getAll(): array {
        return $this->data;
    }
}