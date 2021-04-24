<?php

namespace App\Providers;

use Dapr\DaprClient;
use Dapr\Deserialization\DeserializationConfig;
use Dapr\Deserialization\Deserializer;
use Dapr\Deserialization\IDeserializer;
use Dapr\Middleware\Defaults\Tracing;
use Dapr\Serialization\ISerializer;
use Dapr\Serialization\SerializationConfig;
use Dapr\Serialization\Serializer;
use Illuminate\Support\ServiceProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class DaprServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            ISerializer::class,
            fn(ContainerInterface $container) => new Serializer(
                $container->get(SerializationConfig::class),
                $container->get(LoggerInterface::class)
            )
        );
        $this->app->singleton(
            IDeserializer::class,
            fn(ContainerInterface $container) => new Deserializer(
                $container->get(DeserializationConfig::class),
                $container->get(LoggerInterface::class)
            )
        );
        $this->app->singleton(
            DaprClient::class,
            fn(ContainerInterface $container) => new DaprClient(
                $container->get(LoggerInterface::class),
                $container->get(IDeserializer::class),
                $container->get(Tracing::class),
                config('dapr.port', 3500)
            )
        );
    }
}
