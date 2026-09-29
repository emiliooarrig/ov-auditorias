<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Credenciales de MySQL para las pruebas de integración (DB_HOST, DB_USER, DB_PASS...).
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
