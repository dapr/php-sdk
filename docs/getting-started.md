# Getting Started

The PHP SDK tries not to do too much. You can use something like Laravel, or you can use it as-is with no other
dependencies. If you want to use something like Laravel, you'll need to handle all the routes that Dapr can call, which
[are documented](https://docs.dapr.io/reference/api/).

To use the SDK as-is, you'll need an `index.php` and some boilerplate code:

```php
<?php
// index.php

use Dapr\Runtime;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

/*
 * This is optional, but good if you want to catch all errors and have them bubble up as exceptions.
 */
error_reporting(E_ALL);
ini_set("display_errors", 0);
set_error_handler(
    function ($err_no, $err_str, $err_file, $err_line, $err_context = null) {
        http_response_code(500);
        echo json_encode(
            [
                'errorCode' => 'Exception',
                'message' => (E_WARNING & $err_no ? 'WARNING' : (E_NOTICE & $err_no ? 'NOTICE' : (E_ERROR & $err_no ? 'ERROR' : 'OTHER'))) . ': ' . $err_str,
                'file'    => $err_file,
                'line'    => $err_line,
            ]
        );
        die();
    },
    E_ALL
);
/*
 * End optional error handling
 */

require_once __DIR__.'/vendor/autoload.php';

// logging (using monologger)
$logger = new Logger('dapr');
$handler = new ErrorLogHandler(level: Logger::INFO);
$logger->pushHandler($handler);
$logger->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());
Runtime::set_logger($logger);

// configure your application
\Dapr\Actors\ActorRuntime::do_drain_actors(true);
\Dapr\Actors\ActorRuntime::set_drain_timeout(new DateInterval('PT10S'));
\Dapr\Actors\ActorRuntime::set_idle_timeout(new DateInterval('PT30M'));
\Dapr\Actors\ActorRuntime::set_scan_interval(new DateInterval('PT1M'));
Runtime::add_health_check(fn() => is_healthy());

// add functionality
\Dapr\Actors\ActorRuntime::register_actor(MyActor::class);
Dapr\PubSub\Subscribe::to_topic('pubsub', 'my-topic', fn(\Dapr\PubSub\CloudEvent $event) => do_work($event));
Runtime::register_method('echo', fn($data) => do_work($data), 'POST');
\Dapr\Binding::register_input_binding('my-input', fn($input) => handle_input($input));

// indicate that we're outputting json, but let code override it
header('Content-Type: application/json');

// note that this returns a function
$handler = Runtime::get_handler_for_route($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'));
$result = $handler();

http_response_code($result['code']);
if (isset($result['body'])) {
    echo $result['body'];
}
```

## Project Layout

You can choose to put each service in its own project, or work with a mono-repo. Here's how a mono-repo might be setup:

```
src/
├─ service1/
│  ├─ Actor.php
│  ├─ config.php
├─ service2/
│  ├─ config.php
│  ├─ OtherActor.php
├─ shared/
│  ├─ IActor.php
│  ├─ IOtherActor.php
├─ index.php
```

In `index.php`, you'd read a environment variable that tells you which service to load, which then dynamically loads the
appropriate `config.php` which configures the actor runtime, bindings, subscriptions, etc. Your `shared` directory
includes library code that all services need. Later, you can break the project into individual projects.

## Actor Runtime Configuration

### ActorRuntime::set_scan_interval()

A duration which specifies how often to scan for actors to deactivate idle actors. Actors that have been idle longer
than the actorIdleTimeout will be deactivated.

### ActorRuntime::set_idle_timeout()

Specifies how long to wait before deactivating an idle actor. An actor is idle if no actor method calls and no reminders
have fired on it.

### ActorRuntime::set_drain_timeout()

A duration used when in the process of draining rebalanced actors. This specifies how long to wait for the current
active actor method to finish. If there is no current actor method call, this is ignored.

### ActorRuntime::do_drain_actors()

A bool. If true, Dapr will wait for drainOngoingCallTimeout to allow a current actor call to complete before trying to
deactivate an actor. If false, do not wait.

### ActorRuntime::register_actor()

Registers an actor implementation to be called by Dapr.

### ActorRuntime::handle_config()

Returns an array for returning the configuration of the actor runtime to Dapr when Dapr calls `/dapr/config`.

### ActorRuntime::extract_parts_from_request()

Responsible for determining the actor method, type, and id from the request uri. Returns an array with the following
shape:

```php
[
        'type'          => 'string|null',
        'dapr_type'     => 'string',
        'id'            => 'string|int',
        'function'      => 'string',
        'method_name'   => 'string|null',
        'reminder_name' => 'string|null',
        'body'          => 'array',
];
```

### ActorRuntime::get_input()

Reads json data from PUT/POST requests

### ActorRuntime::handle_invoke()

Given an array from `ActorRuntime::extract_parts_from_request()`, it will invoke the appropriate actor, handling
activation, deactivation, state, etc.

## Runtime methods

### Runtime::add_health_check()

Allows adding a healthcheck callback which is called when the `/healthz` endpoint is called.

### Runtime::get_handler_for_route()

Returns a function for a given http method and uri.

### Runtime::handle_method()

Given a http method, registered method name, and parameters, invokes the registered method.

### Runtime::set_logger()

Set the logger to any PSR-3 compatible logger.
