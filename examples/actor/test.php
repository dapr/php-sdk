<?php

$id = uniqid();

$urls = [
    "http://localhost:8080/method/$id/get_count" => 0,
    "http://localhost:8080/method/$id/increment_and_get" => 1
];

$failed = false;

foreach ($urls as $url => $expected) {
    echo "Calling $url: ";
    $result = json_decode(`curl -s $url`, true);
    if ($result === $expected) {
        echo "PASS\n";
    } else {
        echo "FAILED\n";
        $failed = true;
    }
}

exit($failed ? 1 : 0);
