<?php

use Dapr\App;
use FastRoute\RouteCollector;
use Fixtures\ActorClass;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;

/**
 * Class AppTest
 */
class AppTest extends DaprTests
{
    public function testRouter()
    {
        $mock_router = $this->createMock(RouteCollector::class);
        $this->createBuilder([RouteCollector::class => $mock_router]);
        $app        = $this->container->get(App::class);
        $callback   = fn() => null;
        $with_param = fn($param) => [$param, '/', $callback];
        $mock_router->expects($this->exactly(7))->method('addRoute')->withConsecutive(
            $with_param('GET'),
            $with_param('POST'),
            $with_param('OPTIONS'),
            $with_param('PATCH'),
            $with_param(['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH']),
            $with_param('DELETE'),
            $with_param('PUT')
        );
        $app->get('/', $callback);
        $app->post('/', $callback);
        $app->options('/', $callback);
        $app->patch('/', $callback);
        $app->any('/', $callback);
        $app->delete('/', $callback);
        $app->put('/', $callback);
    }

    public function testHealth()
    {
        $emitter = $this->prepare_app();
        $app     = $this->container->get(App::class);
        $this->set_request('GET', '/healthz');
        $emitter->expects($this->once())->method('emit')->with(
            new Response(headers: ['Content-Type' => ['application/json']])
        );
        $app->start();
    }

    protected function prepare_app(): MockObject|SapiEmitter
    {
        $emitter = $this->createMock(SapiEmitter::class);
        $this->createBuilder(
            [
                SapiEmitter::class => $emitter,
                'dapr.actors'      => ['Test' => ActorClass::class],
            ]
        );

        return $emitter;
    }

    /**
     * @param $method
     * @param $uri
     */
    protected function set_request($method, $uri, $body = null): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        if ($body) {
            $o = fopen('php://input', 'w');
            fwrite($o, $body);
            fclose($o);
        }
    }

    public function testDeleteActor()
    {
        $emitter = $this->prepare_app();
        $app     = $this->container->get(App::class);
        $emitter->expects($spy = $this->any())->method('emit');
        $this->set_request('DELETE', '/actors/Test/123');
        $app->start();
        $this->assertBody($spy, new Response(headers: ['Content-Type' => ['application/json']]));
    }

    private function assertBody($spy, ResponseInterface $response)
    {
        $invocations = $this->get_invocations($spy);
        $factory     = $this->container->get(Psr17Factory::class);
        $new_body    = $factory->createStream('');
        /**
         * @var ResponseInterface $parameter
         */
        $parameter = $invocations[0]->getParameters()[0];
        $this->assertSame($response->getBody()->getContents(), $parameter->getBody()->getContents());
        $this->assertEquals($response->withBody($new_body), $parameter->withBody($new_body));
    }

    protected function get_invocations($spy)
    {
        $reflection_class = new ReflectionClass(get_class($spy));
        $parent           = $reflection_class->getParentClass();

        $parent_props = [];

        foreach ($parent->getProperties() as $p) {
            $p->setAccessible(true);
            $parent_props[$p->getName()] = $p->getValue($spy);
        }

        return $parent_props['invocations'];
    }
}
