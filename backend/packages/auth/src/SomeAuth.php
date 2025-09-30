<?php

declare(strict_types = 1);

namespace Nc\Auth;

use Nc\Common\SomeCommon;

class SomeAuth {
    public function doAuth() {
        return 'doAuth ' . SomeCommon::doMath(1, 2);
    }
}
