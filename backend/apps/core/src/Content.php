<?php

declare(strict_types = 1);

namespace Nc\CoreApp;

abstract class Content {

    const string CONTENT_TYPE = 'text/plain';

    public function serialize(): mixed {
        return get_object_vars($this);
    }

    public function getContentType(): string {
        return self::CONTENT_TYPE;
    }
}
