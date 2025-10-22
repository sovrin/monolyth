<?php

declare(strict_types = 1);

namespace Nc\CoreApp;

class Response {

    public const STATUS = 0;
    public const DESCRIPTION = '';
    public const CONTENT = Content::class;

    protected int $status = self::STATUS;

    protected string $description = self::DESCRIPTION;

    protected Content $content;

    public function getStatus(): int {
        return $this->status;
    }

    public function getContent(): Content {
        return $this->content;
    }
}
