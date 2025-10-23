<?php
declare(strict_types=1);

namespace Thomann\Core\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class LoginStatusView {
    #[Groups(['login:read'])]
    public bool $loggedIn = false;

    #[Groups(['login:read'])]
    public ?UserView $user = null;
}
