<?php

require_once __DIR__.'/Fixtures/TestObj.php';
require_once __DIR__.'/Fixtures/Serialization.php';

use Dapr\Serialization\Attributes\AlwaysObject;
use Fixtures\TestObj;
use PHPUnit\Framework\TestCase;

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
final class SerializerTest extends TestCase
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
        $serialized = \Dapr\Serialization\Serializer::as_json($value, JSON_PRETTY_PRINT);
        \Dapr\Serialization\Serializer::register('serialize_ASpecialType', ASpecialType::class);
        $this->assertSame($expected, $serialized);
    }

    public function testException()
    {
        $serialized = \Dapr\Serialization\Serializer::as_array(new Exception('testing'));
        $this->assertSame(
            [
                'message'   => 'testing',
                'errorCode' => 'Exception',
                'file'      => __FILE__,
                'line'      => 126,
                'inner'     => null,
            ],
            $serialized
        );
    }
}
