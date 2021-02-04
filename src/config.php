<?php

use Dapr\Actors\ActorConfig;
use Dapr\Actors\ActorProxy;
use Dapr\Actors\ActorRuntime;
use Dapr\Actors\Generators\ProxyFactory;
use Dapr\DaprClient;
use Dapr\Deserialization\DeserializationConfig;
use Dapr\Deserialization\Deserializer;
use Dapr\Deserialization\IDeserializer;
use Dapr\PubSub\Publish;
use Dapr\PubSub\Subscriptions;
use Dapr\Serialization\ISerializer;
use Dapr\Serialization\SerializationConfig;
use Dapr\Serialization\Serializer;
use Dapr\State\IManageState;
use Dapr\State\StateManager;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function DI\autowire;
use function DI\create;
use function DI\env;
use function DI\get;

return [
    // logging
    'dapr.log.level'               => LogLevel::WARNING,
    'dapr.log.handler'             => [
        create(ErrorLogHandler::class)->constructor(
            level: get('dapr.log.level')
        ),
    ],
    'dapr.log.processor'           => [create(PsrLogMessageProcessor::class)],

    // interface to implementation
    LoggerInterface::class         => create(Logger::class)->constructor(
        'DAPR',
        get('dapr.log.handler'),
        get('dapr.log.processor')
    ),
    IDeserializer::class           => autowire(Deserializer::class),
    ISerializer::class             => autowire(Serializer::class),
    IManageState::class            => autowire(StateManager::class),
    ProxyFactory::class            => autowire()->constructorParameter(
        'mode',
        get('dapr.actors.proxy.generation')
    ),
    Subscriptions::class           => autowire()->constructorParameter(
        'subscriptions',
        get('dapr.subscriptions')
    ),
    ActorConfig::class             => autowire()
        ->constructorParameter('actor_name_to_type', get('dapr.actors'))
        ->constructorParameter('idle_timeout', get('dapr.actors.idle_timeout'))
        ->constructorParameter('scan_interval', get('dapr.actors.scan_interval'))
        ->constructorParameter('drain_timeout', get('dapr.actors.drain_timeout'))
        ->constructorParameter('drain_enabled', get('dapr.actors.drain_enabled')),
    DaprClient::class              => autowire()->constructorParameter('port', get('dapr.port')),
    SerializationConfig::class     => autowire()->constructorParameter('serializers', get('dapr.serializers.custom')),
    DeserializationConfig::class   => autowire()->constructorParameter(
        'deserializers',
        get('dapr.deserializers.custom')
    ),
    ActorProxy::class              => autowire(),
    Publish::class                 => autowire()->constructorParameter('pubsub', get('dapr.pubsub.default')),
    ActorRuntime::class            => autowire(),

    // default application settings
    'dapr.pubsub.default'          => 'pubsub',
    'dapr.actors.proxy.generation' => ProxyFactory::GENERATED,
    'dapr.subscriptions'           => [],
    'dapr.actors'                  => [],
    'dapr.actors.idle_timeout'     => null,
    'dapr.actors.scan_interval'    => null,
    'dapr.actors.drain_timeout'    => null,
    'dapr.actors.drain_enabled'    => null,
    'dapr.port'                    => env('DAPR_HTTP_PORT', 3500),
    'dapr.serializers.custom'      => [],
    'dapr.deserializers.custom'    => [],
];
