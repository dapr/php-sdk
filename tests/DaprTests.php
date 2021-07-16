<?php

require_once __DIR__ . '/Mocks/DaprClient.php';
require_once __DIR__ . '/Mocks/MockedHttpClientContainer.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dapr\Client\DaprClient as NewClient;
use Dapr\DaprClient;
use Dapr\Mocks\MockedHttpClientContainer;
use Dapr\Mocks\TestClient;
use DI\Container;
use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

use function DI\autowire;

/**
 * Class DaprTests
 */
abstract class DaprTests extends TestCase
{
    protected Container $container;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        $this->createBuilder();
    }

    /**
     * @param array $config
     */
    protected function createBuilder(array $config = [])
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions(__DIR__ . '/../src/config.php');
        $builder->addDefinitions(
            ['dapr.log.level' => LogLevel::EMERGENCY, DaprClient::class => autowire(TestClient::class)]
        );
        $builder->addDefinitions($config);
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

    public function assertRequestMethod(string $expectedMethod, \GuzzleHttp\Psr7\Request $request): void
    {
        $this->assertSame($expectedMethod, $request->getMethod());
    }

    public function assertRequestUri(string $expectedUri, \GuzzleHttp\Psr7\Request $request): void
    {
        $this->assertSame($expectedUri, $request->getUri()->getPath());
    }

    public function assertRequestQueryString(string $expectedQuery, \GuzzleHttp\Psr7\Request $request): void
    {
        $this->assertSame($expectedQuery, $request->getUri()->getQuery());
    }

    public function assertRequestHasHeaders(array $expectedHeaders, \GuzzleHttp\Psr7\Request $request): void
    {
        foreach ($expectedHeaders as $name => $header) {
            $this->assertTrue($request->hasHeader($name), 'Request does not have ' . $name);
            $actual = $request->getHeader($name);
            $this->assertTrue(in_array($header, $actual), "Request missing '$name: $header', found '$name: {$actual[0]}'");
        }
    }

    public function assertRequestBody(string $body, \GuzzleHttp\Psr7\Request $request): void
    {
        $this->assertSame($body, $request->getBody()->getContents());
    }

    protected function get_new_client(): NewClient|MockObject
    {
        $client = $this->createMock(NewClient::class);
        $client->logger = new \Psr\Log\NullLogger();
        $client->deserializer = $this->container->get(\Dapr\Deserialization\IDeserializer::class);
        $client->serializer = $this->container->get(\Dapr\Serialization\ISerializer::class);

        return $client;
    }

    protected function get_new_client_with_http(\GuzzleHttp\Client|MockObject $mock): NewClient
    {
        $reflection = new ReflectionClass(\Dapr\Client\DaprHttpClient::class);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);

        $client = \Dapr\Client\DaprClient::clientBuilder()->build();
        $property->setValue($client, $mock);
        return $client;
    }

    protected function get_http_client_stack(
        array $responseQueue = [],
        array|null &$history = null
    ): MockedHttpClientContainer {
        $container = new MockedHttpClientContainer();
        $container->mock = new MockHandler($responseQueue);
        $history = Middleware::history($container->history);
        $container->handlerStack = HandlerStack::create($container->mock);
        $container->handlerStack->push($history);
        $container->client = new \GuzzleHttp\Client(['handler' => $container->handlerStack]);
        $history = &$container->history;
        return $container;
    }

    protected function deserialize(string $json)
    {
        return json_decode($json, true);
    }
}
