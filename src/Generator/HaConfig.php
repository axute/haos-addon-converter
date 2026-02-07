<?php

namespace App\Generator;

use InvalidArgumentException;

class HaConfig extends Yamlfile
{
    public const string FILENAME = 'config.yaml';
    public const array ARCHITECTURES = [
        'aarch64',
        'arm64',
        'amd64',
        'armhf',
        'armv7',
        'i386'
    ];
    public const array ARCHITECTURES_SUPPORTED_LONGTERM = [
        'aarch64',
        'arm64',
        'amd64',
    ];
    const int INGRESS_PORT = 80;
    const string PANEL_ICON = 'mdi:puzzle';
    protected string $startup = 'application';
    protected string $boot = 'auto';
    protected ?string $backup = null;
    protected ?string $webui = null;
    protected array $ports = [];
    protected array $ports_description = [];
    protected array $map = [];
    protected array $options = [];
    protected bool $ingress = false;
    protected int $ingress_port = self::INGRESS_PORT;
    protected bool $ingress_stream = false;
    protected string $panel_icon = 'mdi:puzzle';
    protected ?string $panel_title = null;
    protected string $ingress_entry = '/';
    protected array $schema = [];
    protected bool $tmpfs = false;
    protected array $environment = [];
    protected ?string $url = null;

    public function __construct(protected string $name, protected string $version, protected string $slug, protected string $description, protected array $architectures = self::ARCHITECTURES)
    {

    }

    public function setTmpfs(bool $tmpfs): static
    {
        $this->tmpfs = $tmpfs;
        return $this;
    }

    public function addEnvironment(string $key, mixed $value): static
    {
        $this->environment[$key] = $value;
        return $this;
    }

    public function addOption(string $key, mixed $value, mixed $schema): static
    {
        $this->options[$key] = $value;
        $this->schema[$key] = $schema;
        return $this;
    }

    public function addPort(int $portContainer, int|null $portHost, string $protocol = 'tcp', ?string $description = null): static
    {
        $this->ports[] = [$portContainer . '/' . $protocol => $portHost];
        if (!empty($description)) $this->ports_description[] = [$portContainer . '/' . $protocol => $description];
        return $this;
    }

    public function setBoot(string $boot): static
    {
        if (!in_array($boot, [
            'auto',
            'manual',
            'disabled'
        ])) {
            throw new InvalidArgumentException('Invalid boot mode: ' . var_export($boot, true));
        }
        $this->boot = $boot;
        return $this;
    }

    public function addMap(string $type, bool $readOnly, ?string $path = null): static
    {
        $entry = [
            'type' => $type,
            'read_only' => $readOnly,
        ];
        if ($path) $entry['path'] = $path;
        $this->map[] = $entry;
        return $this;
    }

    public function setBackup(?string $backup): static
    {
        if (!in_array($backup, [
            'hot',
            'cold',
            'disabled',
            null
        ], true)) {
            throw new InvalidArgumentException('Invalid backup mode: ' . var_export($backup, true));
        }
        if ($backup === null || $backup === 'disabled') {
            $this->backup = null;
            return $this;
        }
        $this->backup = $backup;
        return $this;
    }

    public function setWebui(int|null $port, string $path = '/', string $scheme = 'http'): static
    {
        if ($port === null) {
            $this->webui = null;
            return $this;
        }
        if (in_array($scheme, [
                'http',
                'https'
            ]) === false) {
            throw new InvalidArgumentException('Invalid scheme: ' . var_export($scheme, true));
        }
        $this->webui = $scheme . '://[HOST]:[PORT:' . $port . ']' . $path;
        return $this;
    }

    public function setIngress(int $port = self::INGRESS_PORT, bool $stream = false, ?string $title = null, ?string $icon = null, string $ingressEntry = '/'): static
    {
        $this->ingress = true;
        $this->ingress_port = $port;
        $this->ingress_stream = $stream;
        $this->ingress_entry = $ingressEntry;
        $this->panel_title = $title ?? $this->name;
        $this->panel_icon = $icon ?? self::PANEL_ICON;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $config = [];
        $config['name'] = $this->name;
        $config['version'] = $this->version;
        $config['slug'] = $this->slug;
        $config['description'] = $this->description;
        $config['arch'] = $this->architectures;
        $config['startup'] = $this->startup;
        $config['boot'] = $this->boot;
        if ($this->url !== null) {
            $config['url'] = $this->url;
        }
        if ($this->tmpfs) {
            $config['tmpfs'] = $this->tmpfs;
        }
        if ($this->backup !== null) {
            $config['backup'] = $this->backup;
        }
        if (count($this->environment) > 0) {
            $config['environment'] = $this->environment;
        }
        if ($this->webui !== null) {
            $config['webui'] = $this->webui;
        } else if ($this->ingress) {
            $config['ingress'] = true;
            $config['ingress_port'] = $this->ingress_port;
            $config['ingress_stream'] = $this->ingress_stream;
            $config['ingress_entry'] = $this->ingress_entry;
            $config['panel_icon'] = $this->panel_icon;

            if ($this->panel_title !== null) {
                $config['panel_title'] = $this->panel_title;
            }
        }
        if (count($this->ports) > 0) {
            $config['ports'] = $this->ports;
            if (count($this->ports_description) > 0) {
                $config['ports_description'] = $this->ports_description;
            }
        }
        if (count($this->map) > 0) {
            $config['map'] = $this->map;
        }
        if (count($this->options) > 0) {
            $config['options'] = $this->options;
            $config['schema'] = $this->schema;
        }
        return $config;
    }

    public function setUrl(?string $url): HaConfig
    {
        $this->url = $url;
        return $this;
    }
}