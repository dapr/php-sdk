<?php

namespace Dapr\Client;

use Dapr\Actors\IActorReference;
use Dapr\Actors\Reminder;
use Dapr\Actors\Timer;
use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
use Dapr\State\Internal\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait HttpActorTrait
 * @package Dapr\Client
 */
trait HttpActorTrait
{
    use PromiseHandlingTrait;

    public ISerializer $serializer;
    public IDeserializer $deserializer;
    protected Client $httpClient;

    public function invokeActorMethod(
        string $httpMethod,
        IActorReference $actor,
        string $method,
        mixed $parameter = null,
        string $as = 'array'
    ): mixed {
        return $this->invokeActorMethodAsync($httpMethod, $actor, $method, $as)->wait(true);
    }

    public function invokeActorMethodAsync(
        string $httpMethod,
        IActorReference $actor,
        string $method,
        mixed $parameter = null,
        string $as = 'array'
    ): PromiseInterface {
        $uri = "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/method/$method";
        $options = ['body' => $this->serializer->as_json($parameter)];
        $request = match ($httpMethod) {
            'GET' => $this->httpClient->getAsync($uri),
            'POST' => $this->httpClient->postAsync($uri, $options),
            'PUT' => $this->httpClient->putAsync($uri, $options),
            'DELETE' => $this->httpClient->deleteAsync($uri),
            default => throw new \InvalidArgumentException(
                "$httpMethod is not a supported actor invocation method. Must be GET/POST/PUT/DELETE"
            )
        };

        return $this->handlePromise(
            $request,
            fn(ResponseInterface $response) => $this->deserializer->from_json($as, $response->getBody()->getContents())
        );
    }

    public function saveActorState(IActorReference $actor, Transaction $transaction): bool
    {
        return $this->saveActorStateAsync($actor, $transaction)->wait(true);
    }

    public function saveActorStateAsync(IActorReference $actor, Transaction $transaction): PromiseInterface
    {
        $options = ['body' => $this->serializer->as_json($transaction->get_transaction())];
        return $this->handlePromise(
            $this->httpClient->postAsync(
                "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/state",
                $options
            ),
            fn(ResponseInterface $response) => $response->getStatusCode() === 204
        );
    }

    public function getActorState(IActorReference $actor, string $key, string $as = 'array'): mixed
    {
        return $this->getActorStateAsync($actor, $key, $as)->wait(true);
    }

    public function getActorStateAsync(IActorReference $actor, string $key, string $as = 'array'): PromiseInterface
    {
        return $this->handlePromise(
            $this->httpClient->getAsync("/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/state/$key"),
            fn(ResponseInterface $response) => $this->deserializer->from_json($as, $response->getBody()->getContents())
        );
    }

    public function createActorReminder(
        IActorReference $actor,
        Reminder $reminder
    ): bool {
        return $this->createActorReminderAsync($actor, $reminder)->wait(true);
    }

    public function createActorReminderAsync(
        IActorReference $actor,
        Reminder $reminder
    ): PromiseInterface {
        return $this->handlePromise(
            $this->httpClient->postAsync(
                "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/reminders/{$reminder->name}",
                [
                    'body' => $this->serializer->as_json($reminder)
                ]
            ),
            fn(ResponseInterface $response) => $response->getStatusCode() === 204
        );
    }

    public function getActorReminder(IActorReference $actor, string $name): Reminder
    {
        return $this->getActorReminderAsync($actor, $name)->wait(true);
    }

    public function getActorReminderAsync(IActorReference $actor, string $name): PromiseInterface
    {
        return $this->handlePromise(
            $this->httpClient->getAsync(
                "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/reminders/$name"
            ),
            fn(ResponseInterface $response) => $this->deserializer->from_json(
                Reminder::class,
                $response->getBody()->getContents()
            )
        );
    }

    public function deleteActorReminder(IActorReference $actor, string $name): bool
    {
        return $this->deleteActorReminderAsync($actor, $name)->wait(true);
    }

    public function deleteActorReminderAsync(IActorReference $actor, string $name): PromiseInterface
    {
        return $this->handlePromise(
            $this->httpClient->deleteAsync(
                "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/reminders/$name"
            ),
            fn(ResponseInterface $response) => $response->getStatusCode() === 204
        );
    }

    public function createActorTimer(
        IActorReference $actor,
        Timer $timer
    ): bool {
        return $this->createActorTimerAsync($actor, $timer)->wait(true);
    }

    public function createActorTimerAsync(
        IActorReference $actor,
        Timer $timer
    ): PromiseInterface {
        return $this->handlePromise(
            $this->httpClient->postAsync(
                "/v1.0/actors/{$actor->get_actor_type()}/{$actor->get_actor_id()}/timers/{$timer->name}",
                [
                    'body' => $this->serializer->as_json($timer)
                ]
            ),
            fn(ResponseInterface $response) => $response->getStatusCode() === 204
        );
    }

    public function deleteActorTimer(IActorReference $actor, string $name): bool
    {
        return $this->deleteActorTimerAsync($actor, $name)->wait(true);
    }

    public function deleteActorTimerAsync(IActorReference $actor, string $name): PromiseInterface
    {
        return $this->handlePromise(
            $this->httpClient->deleteAsync("/v1.0/{$actor->get_actor_type()}/{$actor->get_actor_id()}/timers/$name"),
            fn(ResponseInterface $response) => $response->getStatusCode() === 204
        );
    }
}
