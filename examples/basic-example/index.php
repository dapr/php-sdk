<?php

function configure_dapr(\DI\ContainerBuilder $builder)
{
}

require_once __DIR__.'/vendor/autoload.php';

\Dapr\Runtime::start();
