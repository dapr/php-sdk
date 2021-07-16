<?php

use Dapr\Actors\ActorConfig;
use Dapr\Actors\ActorProxy;
use Dapr\Actors\ActorRuntime;
use Dapr\Actors\ActorState;
use Dapr\Actors\Generators\CachedGenerator;
use Dapr\Actors\Generators\DynamicGenerator;
use Dapr\Actors\Generators\ExistingOnly;
use Dapr\Actors\Generators\FileGenerator;
use Dapr\Actors\Generators\ProxyFactory;
use Dapr\Actors\Internal\Caches\FileCache;
use Dapr\App;
use Dapr\DaprClient;
use Dapr\Deserialization\DeserializationConfig;
use Dapr\Deserialization\Deserializer;
use Dapr\Deserialization\IDeserializer;
use Dapr\Middleware\Defaults\Response\ApplicationJson;
use Dapr\Middleware\Defaults\Tracing;
use Dapr\PubSub\Publish;
use Dapr\PubSub\Subscriptions;
use Dapr\PubSub\Topic;
use Dapr\SecretManager;
use Dapr\Serialization\ISerializer;
use Dapr\Serialization\SerializationConfig;
use Dapr\Serialization\Serializer;
use Dapr\State\IManageState;
use Dapr\State\Internal\Transaction;
use Dapr\State\StateManager;
use Dapr\State\TransactionalState;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function DI\autowire;
use function DI\create;
use function DI\env;
use function DI\get;

return [
    // logging
    'dapr.log.level'                => LogLevel::WARNING,
    'dapr.log.handler'              => [
        create(ErrorLogHandler::class)->constructor(
            level: get('dapr.log.level')
        ),
    ],
    'dapr.log.processor'            => [create(PsrLogMessageProcessor::class)],
    'dapr.logger'                   => create(Logger::class)->constructor(
        'DAPR',
        get('dapr.log.handler'),
        get('dapr.log.processor')
    ),

    // default logger to prevent breaking existing code
    LoggerInterface::class          => create(Logger::class)->constructor(
        'APP',
        get('dapr.log.handler'),
        get('dapr.log.processor')
    ),

    // internal functionality
    'dapr.internal.serializer'      => autowire(Serializer::class)->constructorParameter('logger', get('dapr.logger')),
    'dapr.internal.deserializer'    => autowire(Deserializer::class)->constructorParameter(
        'logger',
        get('dapr.logger')
    ),

    // SDK wiring
    ActorConfig::class              => autowire()
        ->constructorParameter('actor_name_to_type', get('dapr.actors'))
        ->constructorParameter('idle_timeout', get('dapr.actors.idle_timeout'))
        ->constructorParameter('scan_interval', get('dapr.actors.scan_interval'))
        ->constructorParameter('drain_timeout', get('dapr.actors.drain_timeout'))
        ->constructorParameter('drain_enabled', get('dapr.actors.drain_enabled')),
    ActorRuntime::class             => autowire()
        ->constructorParameter('logger', get('dapr.logger'))
        ->constructorParameter('deserializer', get('dapr.internal.deserializer')),
    ActorState::class               => autowire()->constructorParameter('logger', get('dapr.logger')),
    ActorProxy::class               => autowire()->constructorParameter('logger', get('dapr.logger')),
    ApplicationJson::class          => autowire(),
    App::class                      => autowire()
        ->constructorParameter('logger', get('dapr.logger'))
        ->constructorParameter('serializer', get('dapr.internal.serializer')),
    CachedGenerator::class          => autowire(),
    DynamicGenerator::class         => autowire(),
    DaprClient::class               => autowire()
        ->constructorParameter('port', get('dapr.port'))
        ->constructorParameter('logger', get('dapr.logger')),
    DeserializationConfig::class    => autowire()->constructorParameter(
        'deserializers',
        get('dapr.deserializers.custom')
    ),
    ExistingOnly::class             => autowire(),
    FileGenerator::class            => autowire(),
    IDeserializer::class            => autowire(Deserializer::class)->constructorParameter(
        'logger',
        get('dapr.logger')
    ),
    IManageState::class             => autowire(StateManager::class)->constructorParameter(
        'logger',
        get('dapr.logger')
    ),
    ISerializer::class              => autowire(Serializer::class)->constructorParameter('logger', get('dapr.logger')),
    ProxyFactory::class             => autowire()->constructorParameter(
        'mode',
        get('dapr.actors.proxy.generation')
    ),
    Psr17Factory::class             => autowire(),
    Publish::class                  => autowire()->constructorParameter('pubsub', get('dapr.pubsub.default')),
    RouteCollector::class           => autowire()
        ->constructorParameter('routeParser', create(Std::class))
        ->constructorParameter('dataGenerator', create(GroupCountBased::class)),
    SecretManager::class            => autowire()->constructorParameter('logger', get('dapr.logger')),
    SerializationConfig::class      => autowire()->constructorParameter('serializers', get('dapr.serializers.custom')),
    ServerRequestCreator::class     => create()->constructor(
        get(Psr17Factory::class),
        get(Psr17Factory::class),
        get(Psr17Factory::class),
        get(Psr17Factory::class)
    ),
    StateManager::class             => autowire(),
    Subscriptions::class            => autowire()->constructorParameter(
        'subscriptions',
        get('dapr.subscriptions')
    ),
    Topic::class                    => autowire()
        ->constructorParameter('logger', get('dapr.logger'))
        ->constructorParameter('client', get(DaprClient::class)),
    Tracing::class                  => autowire(),
    Transaction::class              => autowire(),
    TransactionalState::class       => autowire()->constructorParameter('logger', get('dapr.logger')),

    // default application settings
    'dapr.pubsub.default'           => 'pubsub',
    'dapr.actors.proxy.generation'  => ProxyFactory::GENERATED,
    'dapr.subscriptions'            => [],
    'dapr.actors'                   => [],
    'dapr.actors.idle_timeout'      => null,
    'dapr.actors.scan_interval'     => null,
    'dapr.actors.drain_timeout'     => null,
    'dapr.actors.drain_enabled'     => null,
    'dapr.actors.cache'             => FileCache::class,
    'dapr.http.middleware.request'  => [get(Tracing::class)],
    'dapr.http.middleware.response' => [get(ApplicationJson::class), get(Tracing::class)],
    'dapr.port'                     => env('DAPR_HTTP_PORT', "3500"),
    'dapr.serializers.custom'       => [],
    'dapr.deserializers.custom'     => [],
];
