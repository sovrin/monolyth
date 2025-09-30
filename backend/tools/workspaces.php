#!/usr/bin/env php
<?php

/**
 * Usage: php tools/workspaces.php [install|update]
 * Runs composer in each app under backend/apps/*
 */

$cmd = $argv[1] ?? 'install';
if (!in_array($cmd, ['install', 'update'], true)) {
    fwrite(STDERR, "Usage: workspaces.php [install|update]\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..');
$appsDir = $root . '/apps';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appsDir));
$apps = [];
foreach ($rii as $file) {
    if ($file->getFilename() === 'composer.json') {
        $apps[] = dirname($file->getPathname());
    }
}

sort($apps);
if (!$apps) {
    echo "No apps found under $appsDir\n";
    exit(0);
}

$exit = 0;
foreach ($apps as $dir) {
    echo "▶ $cmd in $dir\n";
    $proc = proc_open(
        ['composer', $cmd, '--no-interaction', '--ansi'],
        [1 => STDOUT, 2 => STDERR],
        $pipes,
        $dir
    );
    $code = is_resource($proc) ? proc_close($proc) : 1;
    $exit = $exit || $code;
    echo "\n";
}

exit($exit);
