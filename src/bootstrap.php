<?php

// configures DI
$di_builder = new DI\ContainerBuilder();
$di_builder->addDefinitions(__DIR__.'/config.php');
$dapr_container = $di_builder->build();
unset($di_builder);
