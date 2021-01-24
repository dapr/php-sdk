# Serialization

Serialization and deserialization is handled by `Dapr\Serialization\Serializer` and `Dapr\Deserialization\Deserializer`,
respectively. The (de)serializer allows for overriding its behavior.

Types are automatically (de)serialized by specifying a type in a method and/or state, but you can use attributes to
override the behavior as well.

Not all builtin types are serializable due to how PHP works, if you find one, please open an issue or PR to add it.

# Manual Serialization

You can manually serialize any value by calling

```php
// return an array to be json encoded
Dapr\Serialization\Serializer::as_array($value);
// or return the raw json string
Dapr\Serialization\Serializer::as_json($value, JSON_PRETTY_PRINT);
```

## Attributes

PHP has a tendency to turn an empty array into `"[]"` when json encoded, if you need it to always be an object, even
when empty, use the `AlwaysObject` attribute.

```php
#[\Dapr\Serialization\Attributes\AlwaysObject]
class MyEmptyObject {}
```

The attribute can be on properties, classes, functions, and methods.

## Registering custom serializers

The SDK allows overriding the default behavior as well as just overriding how specific types are serialized.

### Overriding a type

For example, to override how `my_type` is serialized:

```php
\Dapr\Serialization\Serializer::register(fn($item) => serialize($item), ['my_type']);
```

The custom serializer should return an array that can be json encoded.

### Overriding all serialization

If you need to completely override how serialization is done, just call `register()` without any types specified.

# Manual Deserialization

You can manually deserialize any value:

```php
// deserialize from an array that represents the object
\Dapr\Deserialization\Deserializer::from_array(MyObject::class, $array);
// or from a raw json value
\Dapr\Deserialization\Deserializer::from_json(MyClass::class, $json);
// or from an array of items
\Dapr\Deserialization\Deserializer::from_array_of(MyObject::class, $array_of_items);
```

## Attributes

The deserializer has a few attributes to help with deserializing values.

### Dapr\Deserialization\Attributes\ArrayOf

This attribute allows specifying the type that an array of values may hold:

```php
class MyState {
    #[\Dapr\Deserialization\Attributes\ArrayOf(MyObject::class)]
    public array $items;
}
```

It can be put on properties, parameters, and methods.

### Dapr\Deserialization\Attributes\AsClass

This attribute allows specifying the type that the value should hold.

```php
class MyState {
    #[\Dapr\Deserialization\Attributes\AsClass(MyObject::class)]
    public $item;
}
```

It can be put on properties and parameters.

### Dapr\Deserialization\Attributes\Union

This attribute is required if you have union types:

```php
class MyState {
    #[\Dapr\Deserialization\Attributes\Union([MyState::class, 'determine_item_type'])]
    public string|MyObject $item;
    
    // or
    #[\Dapr\Deserialization\Attributes\Union([MyState::class, 'determine_item_type'], 'string', MyObject::class)]
    public $item2;
    
    public static function determine_item_type($value) {
        return is_string($value) ? 'string' : MyObject::class;
    }
}
```

It can go on properties, parameters, and methods.

## Registering custom deserializers

The SDK allows overriding the default behavior as well as just overriding how specific types are deserialized.

### Overriding a type

For example, to override how `my_type` is deserialized:

```php
\Dapr\Deserialization\Deserializer::register(fn($item) => unserialize($item), ['my_type']);
```

The custom deserializer should return a type your service knows how to understand.

### Overriding all deserialization

If you need to completely override how deserialization is done, just call `register()` without any types specified.
