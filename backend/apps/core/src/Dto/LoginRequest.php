<?php
declare(strict_types=1);

namespace Thomann\Core\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class LoginRequest {
    #[Assert\NotBlank, Groups(['login:write'])]
    public string $username;

    #[Assert\NotBlank, Groups(['login:write'])]
    public string $password;
}