<?php

$id = uniqid();

$urls = [
    [
        'url'        => 'http://localhost',
        'page-views' => '1',
        'name'       => 'go to /welcome/{name} to see a name here',
    ],
    [
        'url'        => 'http://localhost/welcome/test',
        'page-views' => null,
        'name'       => null,
    ],
    [
        'url'        => 'http://localhost',
        'page-views' => '2',
        'name'       => 'test',
    ],
];

$failed = false;

foreach ($urls as ['url' => $url, 'page-views' => $expected_page_views, 'name' => $expected_name]) {
    echo "Calling $url:\n";
    $result = `curl -s $url`;

    if ( !empty($expected_page_views)) {
        echo "Checking page views are $expected_page_views: ";
        $view_pos = strpos($result, 'views: ');
        $views = explode(' ', substr($result, $view_pos, strlen('views: X')))[1] ?? null;
        if ($views === $expected_page_views) {
            echo "PASS\n";
        } else {
            echo "FAILED\n";
            echo $result;
            $failed = true;
        }
    }

    if ( !empty($expected_name)) {
        echo "Checking name matches '$expected_name': ";
        $name_pos = strpos($result, 'name: ');
        $name = explode(' ', substr($result, $name_pos, strlen('name: ') + strlen($expected_name)), 2)[1] ?? null;
        if ($name === $expected_name) {
            echo "PASS\n";
        } else {
            echo "FAILED\n";
            echo $result;
            $failed = true;
        }
    }
}

exit($failed ? 1 : 0);
