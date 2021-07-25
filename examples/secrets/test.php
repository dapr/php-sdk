<?php

$id = uniqid();

$urls = [
    'http://localhost:8080/run' => [
        'simple_secret' => 'got the simple secret!',
        'nested_secret' => 'got the nested secret!',
    ],
];

$failed = false;

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
