<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Modules\Login\Routes;

use Nc\CoreApp\Attributes\Method;
use Nc\CoreApp\Modules\Login\Contents\LoggedInContent;
use Nc\CoreApp\Modules\Login\Properties\UserProperty;
use Nc\CoreApp\Modules\Login\Requests\LoginRequest;
use Nc\CoreApp\Modules\Login\Responses\LoginStatusResponse;
use Nc\CoreApp\Route;
use Nc\CoreApp\Utilities\StringArray;

class MainRoute extends Route {

    #[Method('GET')]
    public function login_status(): LoginStatusResponse {
        $content = new LoggedInContent();
        $content->loggedIn = true;

        $user = new UserProperty();
        $user->roles = new StringArray(['admin', 'superadmin']);
        $user->username = 'root';

        $content->user = $user;

        return new LoginStatusResponse($content);
    }

    #[Method('POST')]
    public function login(LoginRequest $request): LoginStatusResponse {
        $content = new LoggedInContent();
        $content->loggedIn = $request->username === "root";

        $user = new UserProperty();
        $user->username = $request->username;

        $content->user = $user;

        return new LoginStatusResponse($content);
    }
}
