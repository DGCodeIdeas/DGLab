<?php
/**
 * DGLab Database Migration CLI
 */

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;
use DGLab\Database\Migration;
use DGLab\Database\Connection;

$app = new Application(dirname(__DIR__));

$migration = new Migration($app->get(Connection::class));
$migration->run();
