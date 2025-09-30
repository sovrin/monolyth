<?php

declare(strict_types = 1);

namespace Nc\Utils;

use Nc\Common\SomeCommon;

class SomeUtil {

    public static function doSomething(): string {
        return 'doSomething ' . SomeCommon::doMath(3, 6);
    }

    public static function doSomethingElse() {
        return 'doSomethingElse';
    }
}
