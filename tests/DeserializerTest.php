<?php

require_once __DIR__.'/Fixtures/TestObj.php';

use Dapr\exceptions\DaprException;
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

    public function testDaprException()
    {
        $obj       = [
            'errorCode' => 'ERR_ACTOR_INSTANCE_MISSING',
            'message'   => 'Error getting an actor instance. This means that actor is now hosted in some other service replica.',
        ];
        $exception = \Dapr\Deserializer::maybe_deserialize($obj);
        $this->assertSame($obj['errorCode'], $exception->get_dapr_error_code());
        $this->assertSame($obj['message'], $exception->getMessage());
    }

    public function testPHPException()
    {
        $obj       = [
            'errorCode' => LogicException::class,
            'message'   => 'should not happen',
            'file'      => __FILE__,
            'line'      => 123,
        ];
        $exception = \Dapr\Deserializer::maybe_deserialize($obj);
        $this->assertSame($obj['errorCode'], $exception->get_dapr_error_code());
        $this->assertSame($obj['message'], $exception->getMessage());
        $this->assertSame($obj['file'], $exception->getFile());
        $this->assertSame($obj['line'], $exception->getLine());
        $this->assertSame(null, $exception->getPrevious());
    }

    public function testExceptionChain()
    {
        $obj       = [
            'errorCode' => LogicException::class,
            'message'   => 'test message',
            'file'      => __FILE__,
            'line'      => 123,
            'inner'     => [
                'errorCode' => DaprException::class,
                'message'   => 'ok',
                'file'      => __FILE__,
                'line'      => 123,
            ],
        ];
        $exception = \Dapr\Deserializer::maybe_deserialize($obj);
        $this->assertInstanceOf(DaprException::class, $exception->getPrevious());
    }
}
