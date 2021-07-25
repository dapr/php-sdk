<?php

use Dapr\Client\DaprClient;
use Psr\Log\LoggerInterface;

$app->get(
	'/list-secrets',
	function (LoggerInterface $logger, DaprClient $client) {
		$logger->critical('Fetching Secrets.');
		try {
			$file_secrets = [
				'simple-secret' => $client->getSecret('file-secrets', 'simple-secret')['simple-secret'],
				'nested-secret' => $client->getSecret('file-secrets', 'some-secret.nested')['some-secret.nested'],
			];
		} catch (\Dapr\exceptions\DaprException) {
			$k8s_secrets = [
				'simple-secret' => $client->getSecret('kubernetes', 'simple-secret')['data'],
				'nested-secret' => $client->getSecret('kubernetes', 'some-secret')['nested'],
			];
		}
		$secrets = $file_secrets ?? $k8s_secrets ?? [];
		
		return [
			'simple_secret' => $secrets['simple-secret'] ?? null,
			'nested_secret' => $secrets['nested-secret'] ?? null,
		];
	}
);
