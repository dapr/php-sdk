---
type: docs
title: "Publish and Subscribe with PHP"
linkTitle: "Publish and Subscribe"
weight: 1000
description: How to use
no_list: true
---

With Dapr, you can publish anything, including cloud events. The SDK contains a simple cloud event implementation, but
you can also just pass an array that conforms to the cloud event spec or use another library.

```php
<?php
$app->post('/publish', function(\Dapr\Client\DaprClient $daprClient) {
    $daprClient->publishEvent(pubsubName: 'pubsub', topicName: 'my-topic', data: ['something' => 'happened']);
});
```

For more information about publish/subscribe, check out [the howto]({{< ref howto-publish-subscribe.md >}}).

## Data content type

The PHP SDK allows setting the data content type either when constructing a custom cloud event, or when publishing raw data.

{{< tabs CloudEvent "Raw" >}}

To create a custom CloudEvent please take a look at the official documentation of [`cloudevents/sdk-php`](https://github.com/cloudevents/sdk-php/blob/602cd26557e5522060531b3103450b34b678be1c/README.md).

To publish a CloudEvent:

{{% codetab %}}

```php
<?php
/**
 * @var \Dapr\Client\DaprClient $daprClient
 * @var \Dapr\PubSub\Topic $daprTopic
 * @var \CloudEvents\V1\CloudEventInterface $cloudEvent
 */
$daprTopic = new Topic(pubsub: 'pubsub', topic: 'my-topic', $daprClient);
$daprTopic->publish($cloudEvent);
```

{{% alert title="Binary data" color="warning" %}}

Only `application/octet-steam` is supported for binary data.

{{% /alert %}}

{{% /codetab %}}

{{< /tabs >}}

## Receiving cloud events

In your subscription handler, you can have the DI Container inject either a `Dapr\PubSub\CloudEvent` or an `array` into
your controller. The former does some validation to ensure you have a proper event. If you need direct access to the 
data, or the events do not conform to the spec, use an `array`.
