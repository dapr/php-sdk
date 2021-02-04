# Secrets

## Retrieving Secrets

Secrets are read via the SecretManager:

```php
$app->get('/secret', function(\Dapr\SecretManager $secretManager) {
    $value = $secretManager->retrieve('secret-store', 'my-secret');
});
```

## Secret::retrieve()

```
public static function retrieve(string $secret_store, string $name, array $parameters = []): array
```

Parameters:

- secret_store: The secret store to retrieve secrets from
- name: The name of the secret
- parameters: optional parameters that are passed to the secret store component

Returns:

An array.

## Secret::all()

```
public static function all(string $secret_store)
```

Returns all secrets defined in a secret store

Parameters:

- secret_store: The secret store to retrieve secrets from

Returns:

An array of secrets.
