<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Modules\Login\Responses;

use Nc\CoreApp\Modules\Login\Contents\LoggedInContent;
use Nc\CoreApp\Response;

class LoginStatusResponse extends Response {

    public const int STATUS = 200;
    public const string DESCRIPTION = 'Returns login status and (if logged in) basic user info';
    public const string CONTENT = LoggedInContent::class;

    protected int $status = self::STATUS;

    protected string $description = self::DESCRIPTION;

    public function __construct(LoggedInContent $content) {
        $this->content = $content;
    }

    public function getContent(): LoggedInContent {
        return $this->content;
    }
}
