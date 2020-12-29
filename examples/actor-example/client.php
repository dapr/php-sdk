<?php

if (explode('?',$_SERVER['REQUEST_URI'])[0] !== '/start') {
    die();
}

require_once __DIR__.'/vendor/autoload.php';

$id = (string) ($_GET['id'] ?? uniqid());
error_log('Creating an actor with id: '.$id);

/**
 * @var \Client\ICounter $counter
 */
$counter = \Dapr\Actors\ActorProxy::get(\Client\ICounter::class, $id);

$counter->increment($_GET['amount'] ?? 1);

error_log('Incremented by 1, and the current count is '.$counter->get_count());

echo "current count for id [$id]: ".$counter->get_count()."\n";
