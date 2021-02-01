<?php

use Dapr\Deserialization\Deserializer;
use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
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
use function DI\get;

return [
    // logging
    'dapr.log.level'       => LogLevel::WARNING,
    'dapr.log.handler'     => [
        create(ErrorLogHandler::class)->constructor(
            level: get('dapr.log.level')
        ),
    ],
    'dapr.log.processor'   => [create(PsrLogMessageProcessor::class)],

    // interface to implementation
    LoggerInterface::class => create(Logger::class)->constructor(
        'DAPR',
        get('dapr.log.handler'),
        get('dapr.log.processor')
    ),
    IDeserializer::class   => autowire(Deserializer::class),
    ISerializer::class     => autowire(Serializer::class),
    IManageState::class    => autowire(StateManager::class),

    // application settings
];
