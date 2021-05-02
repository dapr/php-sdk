# Secret Example

In this example we illustrate a production service that returns secrets. It consists of two services:

1. [actor-service](services/actor/index.php): A simple counter actor that allows getting the current count or
   incrementing the count.
2. [client-service](services/client/index.php): A simple API to communicate with the actor.

## Running the example

### Docker Compose

> Requirements:
> - Docker Compose
> - Docker
> - `make`
> - `jq` (optional)

#### Build the images

You'll need to build some custom images, do that using `make`.

```bash
make
```

#### Start the services

You'll also use `make` to start a docker-compose file which will spin up dapr, the actor service, and the client
service.

```bash
make start
```

#### Query an actor

You can query the current state of an actor using `curl` (and `jq` to format the JSON response). You can also increment
the actor. Here's an example using an actor with the id `hello_world`:

```bash
curl -s http://localhost:8080/method/hello_world/get_count | jq .
curl -s http://localhost:8080/method/hello_world/increment_and_get | jq .
```

#### Clean up

Delete the containers:

```bash
make clean
```
