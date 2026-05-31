<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__).'/.env');
}

// Ensure the test database exists and all migrations are applied before any test runs.
// Both commands are idempotent: safe to run on every phpunit invocation.
$console = dirname(__DIR__).'/bin/console';
passthru("php $console doctrine:database:create --env=test --if-not-exists --no-interaction -q");
passthru("php $console doctrine:migrations:migrate --env=test --no-interaction -q");
