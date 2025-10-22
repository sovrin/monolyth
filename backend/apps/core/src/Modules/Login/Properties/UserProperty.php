<?php

declare(strict_types = 1);

namespace Nc\CoreApp\Modules\Login\Properties;

use Nc\CoreApp\Property;
use Nc\CoreApp\Utilities\IntArray;
use Nc\CoreApp\Utilities\StringArray;

class UserProperty extends Property {

    public string $username;

    public StringArray $roles;

    public ?IntArray $permissions;
}
