# Getting Started

## Step 1: tools

### Prerequisites

- [Composer](https://getcomposer.org/)
- [PHP 8](https://www.php.net/)
- [Docker](https://www.docker.com/)

### Optional Prerequisites

- [xdebug](http://xdebug.org/) -- for debugging

## Step 2: initialize your project

In a directory where you want to create your service, run `composer init` and answer the questions.
Install `dapr/php-sdk` and any other dependencies you may wish to use.

## Step 4: configure your service

Create a config.php, copying the contents below:

```php
<?php

use Dapr\Actors\Generators\ProxyFactory;
use function DI\env;

return [
    // Generate a new proxy on each request - recommended for development
    'dapr.actors.proxy.generation' => ProxyFactory::GENERATED,
    
    // put any subscriptions here
    'dapr.subscriptions'           => [],
    
    // if this service will be hosting any actors, add them here
    'dapr.actors'                  => [],
    
    // if this service will be hosting any actors, configure how long until dapr should consider an actor idle
    'dapr.actors.idle_timeout'     => null,
    
    // if this service will be hosting any actors, configure how often dapr will check for idle actors 
    'dapr.actors.scan_interval'    => null,
    
    // if this service will be hosting any actors, configure how long dapr will wait for an actor to finish during drains
    'dapr.actors.drain_timeout'    => null,
    
    // if this service will be hosting any actors, configure if dapr should wait for an actor to finish
    'dapr.actors.drain_enabled'    => null,
    
    // you shouldn't have to change this, but the setting is here if you need to
    'dapr.port'                    => env('DAPR_HTTP_PORT', '3500'),
    
    // add any custom serialization routines here
    'dapr.serializers.custom'      => [],
    
    // add any custom deserialization routines here
    'dapr.deserializers.custom'    => [],
];
```

## Step 5: create your service

Create `index.php` and put the following contents: 

```php
<?php

require_once __DIR__.'/vendor/autoload.php';

use Dapr\App;

$app = App::create(configure: fn(\DI\ContainerBuilder $builder) => $builder->addDefinitions(__DIR__ . '/config.php'));
$app->get('/hello/{name}', function(string $name) {
    return ['hello' => $name];
});
$app->start();
```

## step 6: profit

Initialize dapr with `dapr init --runtime-version 1.0.0-rc.3` and then start the project
with `dapr run -a dev -p 3000 -- php -S 0.0.0.0:3000`.

You can now open a web browser and point it to [http://localhost:3000/hello/world](http://localhost:3000/hello/world)
replacing `world` with your name or whatever you want.

Congratulations, you've created your first Dapr service! I'm excited to see what you'll do with it!
