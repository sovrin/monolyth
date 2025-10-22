<?php

use Nc\CoreApp\App;

require_once __DIR__ . '/../vendor/autoload.php';


echo "Hello from core\n";

var_dump([
    new \Nc\Auth\SomeAuth()->doAuth(),
    \Nc\Utils\SomeUtil::doSomething(),
    \Nc\Auth\SomeAuth::hasAPCU()
]);

$app = new App();
$app->handle();
