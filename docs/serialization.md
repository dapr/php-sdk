# Serialization

Serialization and deserialization is handled by `Dapr\Serialization\Serializer` and `Dapr\Deserialization\Deserializer`,
respectively. The (de)serializer allows for overriding its behavior.

Types are automatically (de)serialized by specifying a type in a method and/or state, but you can use attributes to
override the behavior as well.

Not all builtin types are serializable due to how PHP works, if you find one, please open an issue or PR to add it.

# Manual Serialization

You can manually serialize any value by requesting an `ISerializer` type and serializing as json/array. It's important
to note that if you return a serialized string from a controller, it will be double-serialized, return an array instead.

```php
$app->get('/serializer', function(
    \Dapr\Serialization\ISerializer $serializer, 
    \Dapr\Deserialization\IDeserializer $deserializer,
    \Psr\Http\Message\RequestInterface $request) {
        $obj = $deserializer->from_json(MyObj::class, $request->getBody()->getContents());
        return $serializer->as_array($obj);
});
```

## Attributes

PHP has a tendency to turn an empty array into `"[]"` when json encoded, if you need it to always be an object, even
when empty, use the `AlwaysObject` attribute.

```php
#[\Dapr\Serialization\Attributes\AlwaysObject]
class MyEmptyObject {}
```

The attribute can be on properties and classes. If on functions or methods, it applies to the return value.

## Registering custom serializers

The SDK allows overriding the default behavior as well as just overriding how specific types are serialized.

### Overriding a type

You can either register the type in the config, or you can implement the `\Dapr\Serialization\Serializers\ISerialize`
interface on the type. The serializer is passed an instance of the serializer and the object to be serialized which is
also `$this`.

The custom serializer should return an array that can be json encoded.

### Overriding all serialization

If you need to override all serialization, you can implement the `ISerializer` interface and register it in the
configuration.

# Manual Deserialization

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

This attribute is required if you have union types and want to store various types in the same variable depending on its
value.

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

To override how a type is deserialized, just register it in the config, or
implement `\Dapr\Deserialization\Deserializers\IDeserialize` on the type you want to custom deserialize.

### Overriding all deserialization

If you need to completely override how deserialization is done, just call `register()` without any types specified.
