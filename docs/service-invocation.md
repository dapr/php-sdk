# Service Invocation

#### Overview of service invocation

## Introduction

Using service
invocation, [you can securely and reliably communicate with other services running in your cluster.](https://docs.dapr.io/developing-applications/building-blocks/service-invocation/service-invocation-overview/)

With the PHP SDK, you need to use the `DaprClient` to call the method. For example, to call `neworder` in the `nodeapp`
app id, in the `orders` namespace, we'd do something like:

```php
$app->post('/order/{id}', function(\Dapr\DaprClient $client, #[\Dapr\Attributes\FromBody] Order $order, string $id) {
    $client->post('/invoke/nodeapp.orders/method/neworder/' . $id, $order);
});
```

On the other hand, if we want to be able to receive invocations from other services, we need to write a controller for
it.

```php
$app->post('/my-method', function() {/* yes, we've been writing methods this whole time */});
```

### Dapr\Attributes\FromBody

When this attribute is read, the value is deserialized into the expected type for you. If no type is specified, you'll
receive a json decoded value, as an associative array.
