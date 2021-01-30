<?php

require_once __DIR__.'/Mocks/DaprClient.php';
require_once __DIR__.'/../vendor/autoload.php';

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
        $builder->addDefinitions(__DIR__.'/../src/config.php');
        $builder->addDefinitions(
            [
                'dapr.log.level'                => \Psr\Log\LogLevel::CRITICAL,
                DaprClient::class         => autowire(TestClient::class),
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
