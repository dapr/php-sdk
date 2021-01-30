<?php

use Dapr\DaprLogger;
use Dapr\Deserialization\Deserializer;
use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
use Dapr\Serialization\Serializer;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LogLevel;

use function DI\autowire;
use function DI\create;
use function DI\get;

return [
    'dapr.log.level'     => LogLevel::WARNING,
    'dapr.log.handler'   => [
        create(ErrorLogHandler::class)->constructor(
            level: get('dapr.log.level')
        ),
    ],
    'dapr.log.processor' => [create(PsrLogMessageProcessor::class)],
    DaprLogger::class    => create(DaprLogger::class)->constructor(
        'DAPRPHP',
        get('dapr.log.handler'),
        get('dapr.log.processor')
    ),
    IDeserializer::class => autowire(Deserializer::class),
    ISerializer::class   => autowire(Serializer::class),
];
