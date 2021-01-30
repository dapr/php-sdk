<?php

require_once __DIR__.'/Mocks/DaprClient.php';

use Dapr\Actors\ActorRuntime;
use Dapr\Serialization\Serializer;
use PHPUnit\Framework\TestCase;

use function DI\create;
use function DI\get;

abstract class DaprTests extends TestCase
{
    protected \DI\Container $container;

    public function setUp(): void
    {
        $builder = new \DI\ContainerBuilder();
        $builder->addDefinitions(
            [
                'dapr.log.level'        => \Psr\Log\LogLevel::CRITICAL,
                'dapr.log.handler'      => [
                    create(\Monolog\Handler\ErrorLogHandler::class)->constructor(
                        level: get('dapr.log.level')
                    ),
                ],
                'dapr.log.processor'    => [create(\Monolog\Processor\PsrLogMessageProcessor::class)],
                \Dapr\DaprLogger::class => create(\Dapr\DaprLogger::class)->constructor(
                    'DAPRPHP',
                    get('dapr.log.handler'),
                    get('dapr.log.processor')
                ),
            ]
        );
        $this->container = $builder->build();

        \Dapr\Runtime::set_logger(new \Psr\Log\NullLogger());
        \Dapr\DaprClient::$responses = [];
        // reset other static objects
        $class = new ReflectionClass(\Dapr\PubSub\Subscribe::class);
        $class->setStaticPropertyValue('subscribed_topics', []);
        $class->setStaticPropertyValue('handlers', []);

        $class = new ReflectionClass(ActorRuntime::class);
        $class->setStaticPropertyValue('actors', []);
        $class->setStaticPropertyValue('config', []);

        $class = new ReflectionClass(\Dapr\Binding::class);
        $class->setStaticPropertyValue('bindings', []);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        foreach (\Dapr\DaprClient::$responses as $method => $response) {
            if ( ! empty($response)) {
                throw new LogicException('Never handled: '.$method.' '.json_encode($response));
            }
        }
    }

    protected function deserialize(string $json)
    {
        return json_decode($json, true);
    }

    protected function set_body($data)
    {
        ActorRuntime::$input = tempnam(sys_get_temp_dir(), uniqid());
        file_put_contents(ActorRuntime::$input, Serializer::as_json($data));
    }
}
