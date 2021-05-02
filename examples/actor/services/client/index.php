<?php

require_once __DIR__.'/ICounter.php';

use Dapr\Actors\ActorProxy;

$app->get(
    '/',
    fn() => [
        'links' => [
            'Method Invoke' => 'GET /method/<actor-id>/<method>',
            'Methods'       => [
                'get_count'         => 'http://localhost:8080/method/hello_world/get_count',
                'increment_and_get' => 'http://localhost:8080/method/hello_world/increment_and_get',
            ],
        ],
    ]
);

$app->get(
    '/method/{actor_id}/{method}',
    fn(string $actor_id, string $method, ActorProxy $actorProxy) => $actorProxy
        ->get(ICounter::class, $actor_id)->$method()
);
