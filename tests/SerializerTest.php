<?php

require_once __DIR__.'/Fixtures/TestObj.php';

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
 * @covers \Dapr\Serializer
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

        return [
            'Value'        => ['a', 'a'],
            'Array'        => [['a', 'b'], ['a', 'b']],
            'Type'         => [$obj, ['$type' => TestObj::class, '$obj' => ['foo' => 'bar', 'bar' => 'baz']]],
            'Custom'       => [new ASpecialType(), ['$type' => ASpecialType::class, '$obj' => 'world']],
            'DateInterval' => [new DateInterval('PT5M'), ['$type' => DateInterval::class, '$obj' => 'PT5M']],
            'Nested'       => [
                $nested,
                [
                    '$type' => TestObj::class,
                    '$obj'  => ['foo' => 'bar', 'a' => ['$type' => DateInterval::class, '$obj' => 'PT10M']],
                ],
            ],
        ];
    }

    /**
     * @dataProvider generate_serializer
     */
    public function testSerializer($value, $expected)
    {
        $serialized = \Dapr\Serializer::as_json($value);
        \Dapr\Serializer::register('serialize_ASpecialType', ['ASpecialType']);
        $serialized = json_decode(json_encode($serialized), true);
        $expected   = json_decode(json_encode($expected), true);
        $this->assertSame($expected, $serialized);
    }
}
