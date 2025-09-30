<?php

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/plain');

echo "Hello from app2\n";

var_dump([
    'doing math ' . \Nc\Common\SomeCommon::doMath(1, 2) . '\n',
    'Nc\Auth\SomeAuth exists: ' . class_exists('Nc\Auth\SomeAuth') . '\n',
    'Nc\Common\SomeCommon exists: ' . class_exists('Nc\Common\SomeCommon') . '\n',
]);
