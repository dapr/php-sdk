<?php

require_once __DIR__.'/Mocks/DaprClient.php';
require_once __DIR__.'/../vendor/autoload.php';

use Dapr\Actors\ActorRuntime;
use Dapr\DaprClient;
use Dapr\Mocks\TestClient;
use Dapr\Serialization\ISerializer;
use PHPUnit\Framework\TestCase;

use function DI\autowire;

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
                'dapr.log.level'  => \Psr\Log\LogLevel::CRITICAL,
                DaprClient::class => autowire(TestClient::class),
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

    public function tearDown(): void
    {
        foreach ($this->get_client()->responses as $url => $response) {
            $this->assertEmpty($response, "never called $url");
        }
        $this->get_client()->responses = [];
        parent::tearDown();
    }

    protected function get_client(): TestClient
    {
        return $this->container->get(DaprClient::class);
    }

    protected function deserialize(string $json)
    {
        return json_decode($json, true);
    }

    protected function set_body($data)
    {
        ActorRuntime::$input = tempnam(sys_get_temp_dir(), uniqid());
        $serializer          = $this->container->get(ISerializer::class);
        file_put_contents(ActorRuntime::$input, $serializer->as_json($data));
    }
}
