<?php

namespace App\Providers;

use Dapr\Client\DaprClient;
use Dapr\Deserialization\DeserializationConfig;
use Dapr\Serialization\SerializationConfig;
use Illuminate\Support\ServiceProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class DaprServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            DaprClient::class,
            fn(ContainerInterface $container) => DaprClient::clientBuilder()->useHttpClient(
                "http://localhost:".config('dapr.port', '3500')
            )->withLogger($container->get(LoggerInterface::class))->withSerializationConfig(
                $container->get(SerializationConfig::class)
            )->withDeserializationConfig($container->get(DeserializationConfig::class))->build()
        );
    }
}
