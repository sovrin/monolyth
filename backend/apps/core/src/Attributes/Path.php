<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Path {

    public function __construct(
        public string $path,
    ) {}
}
