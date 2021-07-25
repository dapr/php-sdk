<?php

require_once __DIR__.'/vendor/autoload.php';

define('SERVICE', getenv('SERVICE'));
define('SERVICE_ROOT', __DIR__.'/services/'.SERVICE);

$app = \Dapr\App::create(
    configure: fn(\DI\ContainerBuilder $builder) => $builder->addDefinitions(
    __DIR__.'/global-config.php',
    SERVICE_ROOT.'/config.php'
)->enableCompilation(sys_get_temp_dir())->enableDefinitionCache()
);

include SERVICE_ROOT.'/index.php';

$app->start();
