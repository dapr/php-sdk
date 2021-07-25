<?php

$examples = [
    'actor'
];

$branch = getenv('GIT_BRANCH') ?: trim(`git rev-parse --abbrev-ref HEAD`);
$sha = getenv('GIT_SHA') ?: '';
$home = __DIR__;

foreach ($examples as $example) {
    $composer = json_decode(file_get_contents("examples/$example/composer.json"));
    $php_sdk = $composer?->require?->{'dapr/php-sdk'};
    if ($php_sdk === null) {
        continue;
    }
    if (!empty($sha)) {
        $sha = "#$sha";
    }
    $composer->require->{'dapr/php-sdk'} = "dev-$branch$sha";
    file_put_contents(
        "examples/$example/composer.json",
        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS) . "\n"
    );
    chdir("examples/$example");
    echo `composer update`;
}
