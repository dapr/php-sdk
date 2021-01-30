<?php

// configures DI
$di_builder = new DI\ContainerBuilder();
$di_builder->addDefinitions(__DIR__.'/config.php');

if(function_exists('configure_di')) {
    configure_dapr($di_builder);
}

$dapr_container = $di_builder->build();
unset($di_builder);
