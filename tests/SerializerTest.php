<?php

require_once __DIR__.'/Fixtures/TestObj.php';
require_once __DIR__.'/Fixtures/Serialization.php';

use Dapr\Serialization\Attributes\AlwaysObject;
use Dapr\Serialization\ISerializer;
use Dapr\Serialization\SerializationConfig;
use Dapr\Serialization\Serializer;
use Dapr\Serialization\Serializers\ISerialize;
use Fixtures\TestObj;

class ASpecialType
{
    public $hello = 'world';
}

function serialize_ASpecialType(ASpecialType $item)
{
    return $item->hello;
}

/**
 * Class SerializerTest
 * @covers Dapr\Serialization\Serializer
 */
final class SerializerTest extends DaprTests
{
    public function generate_serializer()
    {
        $obj      = new TestObj();
        $obj->foo = 'bar';
        $obj->bar = 'baz';

        $nested      = new TestObj();
        $nested->foo = 'bar';
        $nested->a   = new DateInterval('PT10M');

        $other_sdk = get_example_object();

        $empty = new class {
            public $emptyArray = [];
            #[AlwaysObject]
            public $emptyObj = [];
        };

        $falsy_obj = new class {
            public $emptyString = '';
            public $false = false;
        };

        $empty_class_as_array = new class {
        };
        $empty_class_as_obj   = new #[AlwaysObject] class {
        };

        return [
            'Value'                 => ['a', '"a"'],
            'Array'                 => [
                ['a', 'b'],
                <<<JSON
[
    "a",
    "b"
]
JSON
                ,
            ],
            'Type'                  => [
                $obj,
                <<<JSON
{
    "foo": "bar",
    "bar": "baz"
}
JSON
                ,
            ],
            'Custom'                => [new ASpecialType(), '"world"'],
            'DateInterval'          => [new DateInterval('PT5M'), '"PT5M"'],
            'Nested'                => [
                $nested,
                <<<JSON
{
    "foo": "bar",
    "a": "PT10M"
}
JSON
                ,
            ],
            'ComplexType'           => [$other_sdk, get_example_json()],
            'Empty'                 => [
                $empty,
                <<<JSON
{
    "emptyArray": [],
    "emptyObj": {}
}
JSON
                ,
            ],
            'Empty Class as Array'  => [$empty_class_as_array, '[]'],
            'Empty Class as Object' => [$empty_class_as_obj, '{}'],
            'Falsy values'          => [
                $falsy_obj,
                <<<JSON
{
    "emptyString": "",
    "false": false
}
JSON
                ,
            ],
        ];
    }

    /**
     * @dataProvider generate_serializer
     */
    public function testSerializer($value, $expected)
    {
        $this->container->set(
            SerializationConfig::class,
            new SerializationConfig(
                [
                    ASpecialType::class => new class implements ISerialize {

                        public function serialize(mixed $value, ISerializer $serializer): mixed
                        {
                            return serialize_ASpecialType($value);
                        }
                    },
                ]
            )
        );
        $serializer = $this->container->get(Serializer::class);
        $serialized = $serializer->as_json($value, JSON_PRETTY_PRINT);
        $this->assertSame($expected, $serialized);
    }

    public function testException()
    {
        $serializer = $this->container->get(Serializer::class);
        $serialized = $serializer->as_array(new Exception('testing'));
        $this->assertSame(
            [
                'message'   => 'testing',
                'errorCode' => 'Exception',
                'file'      => __FILE__,
                'line'      => 144,
                'inner'     => null,
            ],
            $serialized
        );
    }
}
