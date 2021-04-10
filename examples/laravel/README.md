# Laravel Example and Dapr

This shows how to use Laravel as a client and as a service. If you take a peek into
the [docker-compose](./docker-compose.yml) file, you'll see the Laravel Sail app is repeated twice. This is because
Laravel Sail is a single-threaded server, so it's not possible to invoke itself. So the `laravel.test` app runs as a
front-end client and the `laravel.api` runs as an API server; but they're both the same code and configuration.

## Running the example

1. Install PHP 8.0+
1. Run `composer install`
1. Run `./vendor/bin/sail up`
2. Go to [http://localhost](http://localhost)
3. Go to [http://localhost/welcome/name](http://localhost/welcome/name) changing the `name` part of the uri to a
   different string
4. Visit [http://localhost](http://localhost) to see the name

# What's Included

1. A [state store component](./components/statestore.yaml) with prefixes turned off to share state between the two apps
2. A [simple dapr provider](./app/Providers/DaprServiceProvider.php) which configures the client and serializers
3. A [slightly modified view](./resources/views/welcome.blade.php) which displays the current state
4. An [api route](./routes/api.php) to show the API implementation that updates the name state
4. A [front-end route](./routes/web.php) to show how to update state and invoke other services
5. A [docker-compose.yml](./docker-compose.yml) file which shows how to configure `daprd` for testing
6. A [.env](./.env) file which works with Laravel Sail

# Note

It's not recommended to use Laravel as an Actor host.
