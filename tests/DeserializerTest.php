<?php

require_once __DIR__.'/Fixtures/TestObj.php';

use Fixtures\TestObj;
use PHPUnit\Framework\TestCase;

function deserialize_special_type($obj)
{
    return $obj['hello'];
}

/**
 * Class DeserializerTest
 * @covers \Dapr\Deserializer
 */
final class DeserializerTest extends TestCase
{
    public function generate_deserializers()
    {
        $obj      = new TestObj();
        $obj->foo = 'bar';
        $obj->bar = 'baz';

        $nested      = new TestObj();
        $nested->foo = 'bar';
        $nested->a   = new DateInterval('PT5M');

        return [
            'Value'        => ['a', 'a'],
            'Array'        => [['a', 'b'], ['a', 'b']],
            'Type'         => [['$type' => TestObj::class, '$obj' => ['foo' => 'bar', 'bar' => 'baz']], $obj],
            'Custom'       => [['$type' => 'special\type', '$obj' => ['hello' => 'world']], 'world'],
            'DateInterval' => [['$type' => 'DateInterval', '$obj' => 'PT5M'], new DateInterval('PT5M')],
            'Nested'       => [
                [
                    '$type' => TestObj::class,
                    '$obj'  => ['foo' => 'bar', 'a' => ['$type' => 'DateInterval', '$obj' => 'PT5M']],
                ],
                $nested,
            ],
        ];
    }

    /**
     * @dataProvider generate_deserializers
     */
    public function testDeserializeValues($value, $expected)
    {
        \Dapr\Deserializer::register('deserialize_special_type', ['special\type']);
        $obj      = \Dapr\Deserializer::maybe_deserialize($value);
        $obj      = json_decode(json_encode($obj), true);
        $expected = json_decode(json_encode($expected), true);
        $this->assertSame($expected, $obj);
    }

    public function testNotFoundType()
    {
        $obj = [
            '$type' => uniqid('test_'),
            '$obj'  => ['unknown' => 'type'],
        ];
        $this->assertFalse(class_exists($obj['$type']));
        $this->expectException(LogicException::class);
        \Dapr\Deserializer::maybe_deserialize($obj);
    }
}
