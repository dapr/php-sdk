# Publish and Subscribe

#### Overview of Dapr Pub/Sub

## Introduction

The [publish/subscribe API in Dapr](https://docs.dapr.io/developing-applications/building-blocks/pubsub/pubsub-overview/)
provides an at-least-once guarantee and integrates with various message brokers and queuing systems. The SDK allows an
easy way to publish and subscribe in Dapr.

## Publishing

With Dapr, you can publish anything, including cloud events. The SDK contains a simple cloud event implementation, or
you can use any other library.

```php
$topic = new \Dapr\PubSub\Topic('pubsub', 'my-topic');
$topic->publish(['something' => 'happened']);
```

You can also use the `Publish` method:

```php
$pubsub = new \Dapr\PubSub\Publish('pubsub');
$pubsub->topic('my-topic')->publish(['something' => 'happened']);
```

### Publish::__construct()

```
public function __construct(private string $pubsub)
```

Arguments:

- pubsub: The name of the pubsub component to publish to

Returns:

A `Publish` object

### Publish::topic()

```
public function topic(string $topic): Topic
```

Arguments:

- topic: The name of the topic to publish to

Returns:

A `Topic` object.

### Topic::__construct()

```
public function __construct(private string $pubsub, private string $topic)
```

Arguments:

- pubsub: The name of the pubsub component
- topic: The name of the topic to publish to

Returns:

A new `Topic` object

### Topic::publish()

```
public function publish(mixed $event): bool
```

Arguments:

- event: The event data to publish, will be json encoded.

Returns:

`true` when published, otherwise `false`

## Subscribing

Subscribing to a topic is fairly straightforward:

```php
\Dapr\PubSub\Subscribe::to_topic('pubsub', 'my-topic', fn(\Dapr\PubSub\CloudEvent $event) => handle($event));
```

Your callback will receive a `CloudEvent` which matches the one you published, or one that Dapr wrapped your message in.

### Subscribe::to_topic()

```
public static function to_topic(string $pubsub, string $topic, callable $handler): void
```

Arguments:

- pubsub: The name of the pubsub component
- topic: The name of the topic
- handler: The callback that accepts a cloud event as it's only argument
