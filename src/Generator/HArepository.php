<?php

namespace App\Generator;

use Symfony\Component\Yaml\Yaml;

class HArepository extends Yamlfile
{
    public const string FILENAME = 'repository.yaml';
    protected ?string $maintainer = null;
    protected ?string $url = null;

    public function __construct(protected string $name)
    {

    }

    public static function fromFile(string $file): static
    {
        $content = Yaml::parseFile($file);
        $instance = new static($content['name']);
        if (isset($content['maintainer'])) $instance->setMaintainer($content['maintainer']);
        if (isset($content['url'])) $instance->setUrl($content['url']);
        return $instance;
    }

    public function setMaintainer(?string $maintainer): static
    {
        $this->maintainer = $maintainer;
        return $this;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $output = [
            'name' => $this->name,
        ];
        if ($this->maintainer) $output['maintainer'] = $this->maintainer;
        if ($this->url) $output['url'] = $this->url;
        return $output;
    }

    public function getMaintainer(): ?string
    {
        return $this->maintainer;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setName(string $name): HArepository
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }
}