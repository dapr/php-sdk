---
type: docs
title: "Configuration with PHP"
linkTitle: "Configuration"
weight: 1000
description: How to read configuration items and subscribe to configuration changes
no_list: true
---

The [Configuration API]({{% ref configuration-api-overview %}}) lets you read configuration items from a
configuration store and subscribe to changes.

## Getting configuration items

Create a client and read items by key. Omit the keys to retrieve all items in the store:

```php
<?php

$client = \Dapr\Client\DaprClient::clientBuilder()->build();

$items = $client->getConfiguration(storeName: 'configstore', keys: ['orderId1', 'orderId2']);

foreach ($items as $key => $item) {
    // $item is a \Dapr\Configuration\ConfigurationItem
    echo "$key = {$item->value} (version: {$item->version})";
}
```

## Subscribing to changes

Subscribing returns a subscription id. The sidecar pushes updates to your application's
`/configuration/<store-name>` route:

```php
$id = $client->subscribeConfiguration(storeName: 'configstore', keys: ['orderId1', 'orderId2']);

// when you no longer need updates
$client->unsubscribeConfiguration(storeName: 'configstore', id: $id);
```

Receive the pushed updates in an app route, parsing the body with
`\Dapr\Configuration\ConfigurationUpdate`:

```php
<?php

$app = \Dapr\App::create();
$app->post('/configuration/configstore', function (#[\Dapr\Attributes\FromBody] string $body) {
    $update = \Dapr\Configuration\ConfigurationUpdate::parse($body);
    foreach ($update->items as $key => $item) {
        echo "config changed: $key = {$item->value}";
    }
});
$app->start();
```

Every client method also has an `*Async` variant (for example, `getConfigurationAsync()`) that returns a
Guzzle promise.
