<?php

namespace HuHwt\WebtreesMods\TaggingServiceManager;

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('HuHwt\\WebtreesMods\\TaggingServiceManager\\', __DIR__);
$loader->addPsr4('HuHwt\\WebtreesMods\\TaggingServiceManager\\', __DIR__ . '/resources');
$loader->addPsr4('HuHwt\\WebtreesMods\\TaggingServiceManager\\', __DIR__ . '/src');
$loader->register();
