<?php

require_once __DIR__.'/Mocks/DaprClient.php';

use Dapr\Actors\ActorRuntime;
use Dapr\DaprClient;
use Dapr\Deserialization\Deserializer;
use Dapr\Deserialization\IDeserializer;
use Dapr\Mocks\TestClient;
use Dapr\Serialization\ISerializer;
use Dapr\Serialization\Serializer;
use Dapr\State\IManageState;
use Dapr\State\StateManager;
use PHPUnit\Framework\TestCase;

use function DI\autowire;
use function DI\create;
use function DI\get;

abstract class DaprTests extends TestCase
{
    protected \DI\Container $container;

    public function setUp(): void
    {
        global $dapr_container;
        $builder = new \DI\ContainerBuilder();
        $builder->addDefinitions(
            [
                'dapr.log.level'                => \Psr\Log\LogLevel::CRITICAL,
                'dapr.log.handler'              => [
                    create(\Monolog\Handler\ErrorLogHandler::class)->constructor(
                        level: get('dapr.log.level')
                    ),
                ],
                'dapr.log.processor'            => [create(\Monolog\Processor\PsrLogMessageProcessor::class)],
                \Psr\Log\LoggerInterface::class => create(\Monolog\Logger::class)->constructor(
                    'DAPRPHP',
                    get('dapr.log.handler'),
                    get('dapr.log.processor')
                ),
                ISerializer::class              => autowire(Serializer::class),
                IDeserializer::class            => autowire(Deserializer::class),
                DaprClient::class         => autowire(TestClient::class),
                IManageState::class => autowire(StateManager::class)
            ]
        );
        $this->container = $builder->build();
        $dapr_container  = $this->container;

        \Dapr\Runtime::set_logger(new \Psr\Log\NullLogger());
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

    protected function deserialize(string $json)
    {
        return json_decode($json, true);
    }

    protected function get_client(): TestClient {
        return $this->container->get(DaprClient::class);
    }

    protected function set_body($data)
    {
        ActorRuntime::$input = tempnam(sys_get_temp_dir(), uniqid());
        file_put_contents(ActorRuntime::$input, Serializer::as_json($data));
    }
}
