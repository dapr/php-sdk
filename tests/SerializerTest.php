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

        $other_sdk                                  = new WeatherForecastWithPOPOs();
        $other_sdk->Date                            = new DateTime('2019-08-01T00:00:00-07:00');
        $other_sdk->DatesAvailable                  = [
            new DateTime('2019-08-01T00:00:00-07:00'),
            new DateTime('2019-08-02T00:00:00-07:00'),
        ];
        $other_sdk->Summary                         = 'Hot';
        $other_sdk->TemperatureCelsius              = 25;
        $other_sdk->TemperatureRanges               = [
            'Cold' => new HighLowTemps(),
            'Hot'  => new HighLowTemps(),
        ];
        $other_sdk->TemperatureRanges['Cold']->High = 20;
        $other_sdk->TemperatureRanges['Cold']->Low  = -10;
        $other_sdk->TemperatureRanges['Hot']->High  = 60;
        $other_sdk->TemperatureRanges['Hot']->Low   = 20;
        $other_sdk->SummaryWords                    = [
            "Cool",
            "Windy",
            "Humid",
        ];

        $empty = new class {
            public $emptyArray = [];
            #[AlwaysObject]
            public $emptyObj = [];
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
        ];
    }

    /**
     * @dataProvider generate_serializer
     */
    public function testSerializer($value, $expected)
    {
        $serialized = \Dapr\Serialization\Serializer::as_json($value, JSON_PRETTY_PRINT);
        \Dapr\Serialization\Serializer::register('serialize_ASpecialType', [ASpecialType::class]);
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
                'line'      => 131,
                'inner'     => null,
            ],
            $serialized
        );
    }
}
