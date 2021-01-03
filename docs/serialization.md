# Serialization

Serialization and deserialization is handled by
`Dapr\Serializer` and `Dapr\Deserializer`, respectively. The (de)serializer allows for overriding its behavior.

All known types are allowed to be serialized or deserialized, as long as they have the following structure:

```json
{
  "$type": "a PHP type",
  "$obj": {
    "property_name": "value"
  }
}
```

Not all builtin types are serializable due to how PHP works, if you find one, please open an issue or PR to add it.

## Registering custom serializers

The SDK allows overriding the default behavior as well as just overriding how specific types are serialized.

### Overriding a type

For example, to override how `my_type` is serialized:

```php
\Dapr\Serializer::register(fn($item) => serialize($item), ['my_type']);
```

The custom serializer should return an array with at least a key with the name `$type`, which will allow it to be
deserialized with a matching custom deserializer. It will need to be `json_encode`-able. Here's an example array of
how `DateInterval` is serialized:

```php
[
    '$type' => '\DateInterval',
    '$obj' => 'PT5S'
];
```

### Overriding all serialization

If you need to completely override how serialization is done, just call `register()` without any types specified.

## Registering custom deserializers

The SDK allows overriding the default behavior as well as just overriding how specific types are deserialized.

### Overriding a type

For example, to override how `my_type` is deserialized:

```php
\Dapr\Deserializer::register(fn($item) => unserialize($item), ['my_type']);
```

The custom deserializer should return a type your service knows how to understand. It's keyed off of a `$type` key in
the object.

### Overriding all deserialization

If you need to completely override how deserialization is done, just call `register()` without any types specified.
