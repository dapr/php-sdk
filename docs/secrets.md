# Secrets

## Retrieving Secrets

```php
$secret_key = \Dapr\Secret::retrieve('secretstore', 'signing_key');
echo $secret_key['signing_key'];
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
