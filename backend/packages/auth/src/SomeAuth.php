<?php

declare(strict_types = 1);

namespace Nc\Auth;

use Nc\Common\SomeCommon;

class SomeAuth {

    public function doAuth() {
        return 'doAuth ' . SomeCommon::doMath(1, 2);
    }

    public static function hasAPCU(): bool {
        return extension_loaded('apcu');
    }
}
