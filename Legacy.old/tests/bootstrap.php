<?php

use DGLab\Core\Application;

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

// Set testing environment variables
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

// Initialize the application once
$app = new Application(dirname(__DIR__));

// Ensure the tests/storage directory exists
$testStorage = __DIR__ . '/storage';
if (!is_dir($testStorage)) {
    mkdir($testStorage, 0777, true);
}
