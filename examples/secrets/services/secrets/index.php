<?php

use Dapr\SecretManager;
use Psr\Log\LoggerInterface;

$app->get(
    '/list-secrets',
    function (LoggerInterface $logger, SecretManager $secretManager) {
        $logger->critical('Fetching Secrets.');
        try {
            $file_secrets = [
                'simple-secret' => $secretManager->retrieve('file-secrets', 'simple-secret')['simple-secret'],
                'nested-secret' => $secretManager->retrieve('file-secrets', 'some-secret.nested')['some-secret.nested'],
            ];
        } catch(\Dapr\exceptions\DaprException) {
            $k8s_secrets = [
                'simple-secret' => $secretManager->retrieve('kubernetes', 'simple-secret')['data'],
                'nested-secret' => $secretManager->retrieve('kubernetes', 'some-secret')['nested'],
            ];
        }
        $secrets = $file_secrets ?? $k8s_secrets ?? [];

        return [
            'simple_secret' => $secrets['simple-secret'] ?? null,
            'nested_secret' => $secrets['nested-secret'] ?? null,
        ];
    }
);
