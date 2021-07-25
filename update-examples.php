<?php

$examples = [
    'actor'
];

$git_sha = getenv('GIT_SHA') ?: trim(`git rev-parse HEAD`);
$home = __DIR__;

foreach ($examples as $example) {
    $composer = json_decode(file_get_contents("examples/$example/composer.json"));
    $php_sdk = $composer?->require?->{'dapr/php-sdk'};
    if ($php_sdk === null) {
        continue;
    }
    $composer->require->{'dapr/php-sdk'} = "dev-$git_sha";
    file_put_contents(
        "examples/$example/composer.json",
        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS) . "\n"
    );
    chdir("examples/$example");
    echo `composer update`;
}
