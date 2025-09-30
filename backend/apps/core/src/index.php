<?php

require_once __DIR__ . '/../vendor/autoload.php';

var_dump([
    new \Nc\Auth\SomeAuth()->doAuth(),
    \Nc\Utils\SomeUtil::doSomething(),
]);
