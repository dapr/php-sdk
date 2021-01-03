# Actor Example

This document describes how to create an Actor and invokes
its methods on the client example.

- **The actor interface** [interface-counter.php](client/interface-counter.php): This is the interface that clients use to call the actor.
- **The actor state** [class-state.php](actor/class-state.php): The state of the actor. This class contains state.
- **The actor implementation** [class-counter.php](actor/class-counter.php): The implementation of the actor.
- **The service** [service.php](service.php): Sets up the runtime and outputs the results.
- **The client** [client.php](client.php): A simple client that calls the actor.

## Prerequisites

- [Install Dapr](https://github.com/dapr/cli#install-dapr-on-your-local-machine-self-hosted)
- PHP 8, with json, mbstring, and curl extensions
- [Install composer](https://getcomposer.org/download/)

## Install Dependencies

Run `composer install`

## Run on the local machine

1. Run in new terminal window:

```
cd examples/actor-example
# Linux/OSX
export MODE=service
# windows (powershell)
$env:MODE = "service"
dapr run --app-id actor-example --app-port 3000 -- php -S 0.0.0.0:3000
```

2. Run the client in a new terminal window:

```
cd examples/actor-example
# Linux/OSX
export MODE=client
# windows (powershell)
$env:MODE = "client"
dapr run --app-id actor-client --app-port 3001 -- php -S 0.0.0.0:3001
```

3. Call the client in another terminal window:

```
curl 'localhost:3001/start?id=test&amount=1'
```

Experiment with different ids and amounts!

## Output

In the service logs, you'll see:

```
== APP == [Sun Jan  3 10:13:31 2021] Actor woke up: test
== APP == [Sun Jan  3 10:13:31 2021] 127.0.0.1:59980 [200]: PUT /actors/Counter/test/method/increment
== APP == [Sun Jan  3 10:13:31 2021] 127.0.0.1:59994 [200]: PUT /actors/Counter/test/method/get_count
== APP == [Sun Jan  3 10:13:41 2021] Actor going to sleep: test
== APP == [Sun Jan  3 10:13:41 2021] 127.0.0.1:60032 [200]: DELETE /actors/Counter/test
```

