<?php

require_once __DIR__.'/Mocks/DaprClient.php';
require_once __DIR__.'/../vendor/autoload.php';

use Dapr\DaprClient;
use Dapr\Mocks\TestClient;
use DI\Container;
use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use PHPUnit\Framework\TestCase;

use Psr\Log\LogLevel;

use function DI\autowire;

abstract class DaprTests extends TestCase
{
    protected Container $container;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions(__DIR__.'/../src/config.php');
        $builder->addDefinitions(
            [
                'dapr.log.level'  => LogLevel::CRITICAL,
                DaprClient::class => autowire(TestClient::class),
            ]
        );
        $this->container = $builder->build();
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function tearDown(): void
    {
        foreach ($this->get_client()->responses as $url => $response) {
            $this->assertEmpty($response, "never called $url");
        }
        $this->get_client()->responses = [];
        parent::tearDown();
    }

    /**
     * @return TestClient
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function get_client(): TestClient
    {
        return $this->container->get(DaprClient::class);
    }

    protected function deserialize(string $json)
    {
        return json_decode($json, true);
    }
}
