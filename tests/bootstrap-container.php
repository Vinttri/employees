<?php

declare(strict_types=1);

require '/var/www/html/lib/base.php';

$loader = new Composer\Autoload\ClassLoader();
$loader->addPsr4('OCA\\Employees\\', __DIR__ . '/../lib');
$loader->addPsr4('Shuchkin\\', __DIR__ . '/../third_party/Shuchkin');
$loader->register(true);

\OC_App::loadApp(OCA\Employees\AppInfo\Application::APP_ID);
OC_Hook::clear();
