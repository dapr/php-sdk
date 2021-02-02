# Publish and Subscribe

#### Overview of Dapr Pub/Sub

## Introduction

The [publish/subscribe API in Dapr](https://docs.dapr.io/developing-applications/building-blocks/pubsub/pubsub-overview/)
provides an at-least-once guarantee and integrates with various message brokers and queuing systems. The SDK allows an
easy way to publish and subscribe in Dapr.

## Publishing

With Dapr, you can publish anything, including cloud events. The SDK contains a simple cloud event implementation.

```php
$app->post('/publish', function(\DI\FactoryInterface $factory) {
    // create a new publisher that publishes to my-pub-sub component
    $publisher = $factory->make(\Dapr\PubSub\Publish::class, ['pubsub' => 'my-pubsub']);
    
    // publish that something happened to my-topic
    $publisher->topic('my-topic')->publish(['something' => 'happened']);
});
```

### Publish::topic()

```
public function topic(string $topic): Topic
```

Arguments:

- topic: The name of the topic to publish to

Returns:

A `Topic` object.

### Topic::publish()

```
public function publish(mixed $event): bool
```

Arguments:

- event: The event data to publish, will be json encoded.

Returns:

`true` when published, otherwise `false`

## Subscribing

Subscribing to a topic is fairly straightforward, you just need to add it to your configuration and add a route to
handle it. The following example shows how to subscribe to the `animals` topic on the `zoo` pubsub component.

```php
<?php
// in config.php

return [
    'dapr.subscriptions'           => [new \Dapr\PubSub\Subscription('zoo', 'animals', '/animal')],
];
```

```php
$app->post('/animal', function(#[\Dapr\Attributes\FromBody] \Dapr\PubSub\CloudEvent $event) {
    // handle the event
});
```

Your callback will receive a `CloudEvent` which matches the one you published, or one that Dapr wrapped your message in.
