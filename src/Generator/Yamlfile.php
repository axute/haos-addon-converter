<?php

namespace App\Generator;

use JsonSerializable;
use Stringable;
use Symfony\Component\Yaml\Yaml;

abstract class Yamlfile implements Stringable, JsonSerializable
{

    public function __toString(): string {

        return Yaml::dump($this->jsonSerialize(), 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_COMPACT_NESTED_MAPPING);
    }
}