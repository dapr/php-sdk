<?php

namespace Dapr;

use Dapr\Actors\ActorConfig;
use Dapr\Actors\ActorRuntime;
use Dapr\Actors\HealthCheck;
use Dapr\Actors\IActor;
use Dapr\exceptions\Http\NotFound;
use Dapr\PubSub\Subscriptions;
use Dapr\Serialization\ISerializer;
use DI\Container;
use DI\ContainerBuilder;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\ResolverChain;
use Invoker\ParameterResolver\TypeHintResolver;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class App
{
    protected ServerRequestCreator $creator;

    public function __construct(
        protected Container $container,
        protected ?RouteCollector $routeCollector = null,
        protected ISerializer $serializer,
        protected Psr17Factory $psr17Factory
    ) {
        if ($this->routeCollector === null) {
            $this->routeCollector = new RouteCollector(
                new Std,
                new GroupCountBased
            );
        }

        $this->creator = new ServerRequestCreator(
            $this->psr17Factory, // ServerRequestFactory
            $this->psr17Factory, // UriFactory
            $this->psr17Factory, // UploadedFileFactory
            $this->psr17Factory  // StreamFactory
        );
    }

    public static function create(ContainerInterface $container = null): App
    {
        if ($container === null) {
            $builder = new ContainerBuilder();
            $builder->addDefinitions(__DIR__.'/../config.php');
            if (function_exists('configure_dapr')) {
                configure_dapr($builder);
            }
            $container = $builder->build();
        }
        $app = $container->make(App::class);
        $container->set(App::class, $app);

        return $app;
    }

    public function post(string $route, callable $callback)
    {
        $this->routeCollector->addRoute('POST', $route, $callback);
    }

    public function options(string $route, callable $callback)
    {
        $this->routeCollector->addRoute('OPTIONS', $route, $callback);
    }

    public function patch(string $route, callable $callback)
    {
        $this->routeCollector->addRoute('PATCH', $route, $callback);
    }

    public function any(string $route, callable $callback)
    {
        $this->routeCollector->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'], $route, $callback);
    }

    public function binding(string $binding_name, callable $callback) {
        $this->options("/$binding_name", fn());
    }

    public function start()
    {
        $this->add_dapr_routes($this);
        try {
            $response = $this->handleRequest();
        } catch (NotFound $exception) {
            $response = $this->psr17Factory->createResponse(404)->withBody(
                $this->psr17Factory->createStream($this->serializer->as_json($exception))
            );
        } catch (\Exception $exception) {
            $response = $this->psr17Factory->createResponse(500)->withBody(
                $this->psr17Factory->createStream($this->serializer->as_json($exception))
            );
        }
        $emitter = new SapiEmitter();
        $emitter->emit($response);
    }

    /**
     * @param App $app
     */
    public function add_dapr_routes($app)
    {
        /**
         * Actors
         */
        // deactivate an actor
        $app->delete(
            '/actors/{actor_type}/{actor_id}',
            function (
                string $actor_type,
                string $actor_id,
                ActorRuntime $runtime
            ) {
                $runtime->resolve_actor(
                    $actor_type,
                    $actor_id,
                    fn(IActor $actor) => $runtime->deactivate_actor($actor, $actor_type, $actor_id)
                );
            }
        );
        // allow calling an actor
        $app->put(
            '/actors/{actor_type}/{actor_id}/method/{method_name}[/{reminder_name}]',
            function (
                RequestInterface $request,
                ResponseInterface $response,
                string $actor_type,
                string $actor_id,
                string $method_name,
                ?string $reminder_name,
                ActorRuntime $runtime
            ) {
                $arg = json_decode($request->getBody()->getContents(), true);
                if ($method_name === 'remind') {
                    $runtime->resolve_actor(
                        $actor_type,
                        $actor_id,
                        fn(IActor $actor) => $actor->remind($reminder_name, $arg)
                    );
                } elseif ($method_name === 'timer') {
                    return $runtime->resolve_actor(
                        $actor_type,
                        $actor_id,
                        fn(IActor $actor) => $response->withBody(
                            $this->serialize_as_stream($runtime->do_method($actor, $reminder_name, $arg))
                        )
                    );
                } else {
                    return $runtime->resolve_actor(
                        $actor_type,
                        $actor_id,
                        fn(IActor $actor) => $response->withBody(
                            $this->serialize_as_stream($runtime->do_method($actor, $method_name, $arg))
                        )
                    );
                }

                return $response;
            }
        );
        // handle configuration
        $app->get('/dapr/config', fn(ActorConfig $config) => $config);
        $app->get(
            '/healthz',
            fn(HealthCheck $check, ResponseInterface $response) => $response->withStatus(
                $check->do_health_check() ? 200 : 500
            )
        );
        /**
         * Publish/Subscribe
         */
        $app->get('/dapr/subscribe', fn(Subscriptions $subscriptions) => $subscriptions);
    }

    public function delete(string $route, callable $callback)
    {
        $this->routeCollector->addRoute('DELETE', $route, $callback);
    }

    public function put(string $route, callable $callback)
    {
        $this->routeCollector->addRoute('PUT', $route, $callback);
    }

    private function serialize_as_stream(mixed $data)
    {
        return $this->psr17Factory->createStream($this->serializer->as_json($data));
    }

    public function get(string $route, callable $callback)
    {
        $this->routeCollector->addRoute('GET', $route, $callback);
    }

    private function handleRequest(): ResponseInterface
    {
        $request  = $this->creator->fromGlobals();
        $response = $this->psr17Factory->createResponse()->withHeader('Content-Type', 'application/json');

        $this->container->set(ServerRequestInterface::class, $request);
        $this->container->set(ResponseInterface::class, $response);

        $parameters = ['request' => $request, 'response' => $response];

        $dispatcher = new Dispatcher\GroupCountBased($this->routeCollector->getData());

        $route_info = $dispatcher->dispatch($request->getMethod(), $request->getUri());
        switch ($route_info[0]) {
            case Dispatcher::NOT_FOUND:
            default:
                return $response->withStatus(404);
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowed_methods = $route_info[1];

                return $response->withStatus(405)->withAddedHeader('Allow', implode(',', $allowed_methods));
            case Dispatcher::FOUND:
                $parameters += $route_info[2];
                $callback   = $route_info[1];

                $resolvers = [
                    new AssociativeArrayResolver(),
                    new TypeHintResolver(),
                    new DefaultValueResolver(),
                ];

                $invoker         = new Invoker(new ResolverChain($resolvers), $this->container);
                $actual_response = $response;
                $response        = $invoker->call($callback, $parameters);

                if ($response instanceof ResponseInterface) {
                    return $response;
                }

                return $actual_response->withBody($this->serialize_as_stream($response));
        }
    }
}
