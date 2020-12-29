<?php

$mode = getenv('MODE');

switch ($mode) {
    case 'client':
        include __DIR__.'/client.php';
        break;
    case 'service':
        include __DIR__.'/service.php';
        break;
}
