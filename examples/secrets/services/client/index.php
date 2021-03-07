<?php

use Dapr\DaprClient;

$app->get(
    '/run',
    function (DaprClient $client) {
        return $client->get('/invoke/secrets/method/list-secrets')->data;
    }
);
