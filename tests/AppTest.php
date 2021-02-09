<?php

use Dapr\Actors\Reminder;
use Dapr\Actors\Timer;
use Dapr\App;
use Dapr\exceptions\Http\NotFound;
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
        $this->assertBody($spy, new Response(headers: ['Content-Type' => ['application/json']], body: 'null'));
    }

    private function assertBody($spy, ResponseInterface $response)
    {
        $invocations = $this->get_invocations($spy);
        $factory     = $this->container->get(Psr17Factory::class);
        $new_body    = $factory->createStream('');
        $new_body->rewind();
        $response->getBody()->rewind();
        /**
         * @var ResponseInterface $parameter
         */
        $parameter = $invocations[0]->getParameters()[0];
        $parameter->getBody()->rewind();
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

    public function testCallActor()
    {
        $emitter = $this->prepare_app();
        $app     = $this->container->get(App::class);
        $emitter->expects($spy = $this->any())->method('emit');
        $this->set_request('PUT', '/actors/Test/123/method/empty_func');
        $app->start();
        $this->assertBody($spy, new Response(headers: ['Content-Type' => ['application/json']], body: 'true'));
    }

    public function testRemindActor()
    {
        $emitter = $this->prepare_app();
        $app     = $this->container->get(App::class);
        $emitter->expects($spy = $this->any())->method('emit');
        $this->set_request('PUT', '/actors/Test/123/method/remind/reminder');
        $factory  = $this->container->get(Psr17Factory::class);
        $reminder = json_encode((new Reminder('reminder', new DateInterval('PT1S'), 'test'))->to_array());
        $request  = $factory->createServerRequest('PUT', '/actors/Test/123/method/remind/reminder')->withBody(
            $factory->createStream($reminder)
        );
        $request->getBody()->rewind();
        $app->start($request);
        $this->assertBody($spy, new Response(headers: ['Content-Type' => ['application/json']]));
    }

    public function testActorTimer()
    {
        $emitter = $this->prepare_app();
        $app     = $this->container->get(App::class);
        $emitter->expects($spy = $this->any())->method('emit');
        $factory  = $this->container->get(Psr17Factory::class);
        $reminder = json_encode(
            (new Timer('reminder', new DateInterval('PT1S'), new DateInterval('PT1S'), 'empty_func'))->to_array()
        );
        $request  = $factory->createServerRequest('PUT', '/actors/Test/123/method/timer/a-timer')->withBody(
            $factory->createStream($reminder)
        );
        $request->getBody()->rewind();
        $app->start($request);
        $this->assertBody($spy, new Response(headers: ['Content-Type' => ['application/json']], body: 'true'));
    }

    public function testArrayResponse()
    {
        $emitter = $this->prepare_app();
        $emitter->expects($spy = $this->any())->method('emit');
        $app = $this->container->get(App::class);
        $app->get('/test', fn() => ['code' => 203, 'body' => ['an' => 'array', 'was here']]);
        $this->set_request('GET', '/test');
        $app->start();
        $this->assertBody(
            $spy,
            new Response(
                status: 203,
                headers: ['Content-Type' => ['application/json']],
                body: '{"an":"array","0":"was here"}'
            )
        );
    }

    public function testDaprResponse()
    {
        $emitter = $this->prepare_app();
        $emitter->expects($spy = $this->any())->method('emit');
        $app = $this->container->get(App::class);
        $app->get('/test', fn() => new \Dapr\DaprResponse(204, 'some data', headers: ['custom' => 'header']));
        $this->set_request('GET', '/test');
        $app->start();
        $this->assertBody(
            $spy,
            new Response(
                status: 204,
                headers: ['Content-Type' => ['application/json'], 'custom' => ['header']],
                body: '"some data"'
            )
        );
    }

    public function testAnyOutput()
    {
        $emitter = $this->prepare_app();
        $emitter->expects($spy = $this->any())->method('emit');
        $app = $this->container->get(App::class);
        $app->get('/test', fn() => ['hello' => 'world']);
        $this->set_request('GET', '/test');
        $app->start();
        $this->assertBody(
            $spy,
            new Response(
                status: 200,
                headers: ['Content-Type' => ['application/json']],
                body: '{"hello":"world"}'
            )
        );
    }

    public function testMethodNotAllowed() {
        $emitter = $this->prepare_app();
        $emitter->expects($spy = $this->any())->method('emit');
        $app = $this->container->get(App::class);
        $app->get('/test', fn() => ['hello' => 'world']);
        $this->set_request('OPTIONS', '/test');
        $app->start();
        $this->assertBody(
            $spy,
            new Response(
                status: 405,
                headers: ['Content-Type' => ['application/json'], 'Allow' => ['GET']]
            )
        );
    }

    public function testNotFound() {
        $emitter = $this->prepare_app();
        $emitter->expects($spy = $this->any())->method('emit');
        $app = $this->container->get(App::class);
        $this->set_request('GET', '/test');
        $app->start();
        $this->assertBody(
            $spy,
            new Response(
                status: 404,
                headers: ['Content-Type' => ['application/json']]
            )
        );
    }

    public function testNotFoundException() {
        $emitter = $this->prepare_app();
        $emitter->expects($spy = $this->any())->method('emit');
        $app = $this->container->get(App::class);
        $app->get('/not-found', fn() => throw new NotFound());
        $this->set_request('GET', '/not-found');
        $app->start();
        $this->assertBody(
            $spy,
            new Response(
                status: 404,
                headers: ['Content-Type' => ['application/json']]
            )
        );
    }
}
