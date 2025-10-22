<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Modules\Login\Contents;

use Nc\CoreApp\JsonContent;
use Nc\CoreApp\Modules\Login\Properties\UserProperty;

class LoggedInContent extends JsonContent {

    public bool $loggedIn = false;

    public UserProperty $user;
}
