# Service Invocation

#### Overview of service invocation

## Introduction

Using service
invocation, [you can securely and reliably communicate with other services running in your cluster.](https://docs.dapr.io/developing-applications/building-blocks/service-invocation/service-invocation-overview/)

With the PHP SDK, this is made easy through some helpers. For example, to call a service named `nodeapp` with the
method `neworder`, we just call:

```php
$result = \Dapr\Runtime::invoke_method(
    app_id: 'nodeapp', 
    method: 'neworder',
    param: 'order_id_123',
    http_method: 'PUT' 
);
echo $result->data;
```

On the other hand, if we want to be able to receive invocations from other services, we need to register it:

```php
\Dapr\Runtime::register_method(
    method_name: 'my_method',
    http_method: 'PUT',
    callback: fn(string $param) => do_something($param)  
);
```

## Runtime::invoke_method()

```
public static function invoke_method(
        string $app_id,
        string $method,
        mixed $param = [],
        $http_method = 'POST'
    ): DaprResponse
```

Arguments:

- app_id: The service to invoke the method on. Can also be called across namespaces by appending the namespace.
  So, `nodeapp.production` is the `nodeapp` service in the `production` namespace.
- method: The method to invoke on the remote service. One of `GET`, `POST`, `PUT`, or `DELETE`.
- param: A single value to invoke the method with. Can be an array. It will be [serialized](serialization.md)
- http_method: The http method to invoke the remote service with.

Returns:

A [`DaprResponse`](dapr-response.md) is returned, or a `DaprException` is thrown if the invocation fails.

## Runtime::register_method

```
public static function register_method(
        string $method_name,
        ?callable $callback = null,
        string $http_method = 'POST'
    ): void
```

Allows registering a method with the runtime.

Arguments:

- method_name: The name of the method to listen for
- callback: A callback to invoke when invoked. If `null`, it will call a function with the same name as the method.
- http_method: The http method to listen for.

Callback:

There are several attributes you can apply to method parameters: `Dapr\Attributes\FromBody`
and `Dapr\Attributes\FromRoute`.

### Dapr\Attributes\FromBody

This is the default if no attribute is specified. It deserializes the body into the specified type.

### Dapr\Attributes\FromRoute

This takes from the route in order, illustrated here: `/my_method/id/something` and a function signature
like `function (#[FromRoute] $id, #[FromRoute] $something)`.
