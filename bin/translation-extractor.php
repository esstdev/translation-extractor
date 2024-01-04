<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Esst\TranslationExtractor\Commands\ExtractCommand;
use Symfony\Component\Console\Application;

$dotEnv = Dotenv\Dotenv::createUnsafeImmutable(
    dirname(__DIR__),
    ['.env', '.env.dist']
);
$dotEnv->load();

$application = new Application();
$application->add(new ExtractCommand());
$application->run();

