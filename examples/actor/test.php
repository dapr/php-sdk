<?php

$id = uniqid();

$urls = [
    "http://localhost:8080/method/$id/get_count" => 0,
    "http://localhost:8080/method/$id/increment_and_get" => 1
];

$failed = false;

while(true) {
    $logs = `GIT_SHA=t docker-compose logs actor-daprd`;
    if(str_contains($logs, 'placement tables updated, version: 1')) {
        echo "Running Tests!\n";
        break;
    }
    echo "Waiting for actors to be registered...\n";
    sleep(2);
}

foreach ($urls as $url => $expected) {
    echo "Calling $url: ";
    $result = json_decode(`curl -s $url`, true);
    if ($result === $expected) {
        echo "PASS\n";
    } else {
        echo "FAILED\n";
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $failed = true;
    }
}

exit($failed ? 1 : 0);
