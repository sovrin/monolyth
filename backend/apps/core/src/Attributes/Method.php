<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Method {

    public function __construct(
        public string $method,
    ) {}
}
