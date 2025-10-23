<?php
declare(strict_types=1);

namespace Thomann\Core\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class UserView {
    #[Groups(['login:read'])]
    public string $username;

    /** @var string[] */
    #[Groups(['login:read'])]
    public array $roles = [];
}
