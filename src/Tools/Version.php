<?php

namespace App\Tools;

class Version implements \Stringable, \JsonSerializable
{
    public function __construct(
        public int    $major,
        public int    $minor,
        public int    $patch,
        public string $preRelease = '',
        public string $buildMetadata = ''
    )
    {
    }

    public static function fromSemverTag(string $tag): ?static
    {
        // Regex für SemVer 2.0.0 (https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string)
        // Leicht angepasst für optionales 'v' Präfix
        $pattern = '/^v?(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

        if (preg_match($pattern, $tag, $matches)) {
            return self::fromArray($matches);
        }

        return null;
    }

    public function __toString(): string
    {
        $version = sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
        if ($this->preRelease !== '') {
            $version .= '-' . $this->preRelease;
        }
        if ($this->buildMetadata !== '') {
            $version .= '+' . $this->buildMetadata;
        }

        return $version;
    }

    public function getTag(): string
    {
        return 'v' . $this->__toString();
    }

    public function isStable(): bool
    {
        return $this->preRelease === '';
    }

    public function hasMetadata(): bool
    {
        return $this->buildMetadata !== '';
    }

    public function isNewerThan(self $other): bool
    {
        return $this->compare($other) === 1;
    }

    public function compare(self $other, null|string $operator = null): int|bool
    {
        return version_compare($this->__toString(), $other->__toString(), $operator);
    }

    public function __debugInfo(): array
    {
        return [
            'major' => $this->major,
            'minor' => $this->minor,
            'patch' => $this->patch,
            'prerelease' => $this->preRelease,
            'buildmetadata' => $this->buildMetadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    public static function fromArray(array $data): self
    {
        return new static($data['major'], $data['minor'], $data['patch'], $data['prerelease'] ?? '', $data['buildmetadata'] ?? '');
    }
}