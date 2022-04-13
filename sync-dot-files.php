#!/usr/bin/php
<?php

require 'SyncDotFiles.php';

$homeDir = exec('echo ~');

define('CONFIG_FILE_PATH', $homeDir . '/.config/sync-dot-files.json');

$configDefaults = [
    'repositoryDirectory' => '',
    'repositoryUrl' => '',
    'debug' => false
];

###############################

if (!is_file(CONFIG_FILE_PATH)) {
    file_put_contents(CONFIG_FILE_PATH, json_encode($configDefaults, JSON_THROW_ON_ERROR));

    die('Config file not found, creating it with empty values.. ' . CONFIG_FILE_PATH . "\n");
}

$config = json_decode(file_get_contents(CONFIG_FILE_PATH), true);

if(count(array_diff_key($configDefaults, $config)) > 0) {
    die("Config file corrupt (check all keys exists)\n");
}

$action = $argv[1] ?? null;
$param1 = $argv[2] ?? null;

$sync = new SyncDotFiles($config['repositoryDirectory'], $config['repositoryUrl']);

match ($action) {
    'init-remote' => $sync->initRemote(),
    'init-local' => $sync->initLocal(),
    'pull' => $sync->pullCopy(),
    'push' => $sync->copyPush(),
    'add' => $sync->add($param1),
    'remove' => $sync->remove($param1),
    default => die("Unknown command\n"),
};

if($config['debug']) {
    echo implode("\n", $sync->getOutput());
}