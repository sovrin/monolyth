<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Modules\Login\Requests;

class LoginRequest {
    public string $username;
    public string $password;
}
