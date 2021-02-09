<?php

require_once __DIR__.'/Fixtures/Serialization.php';

use Dapr\Deserialization\Attributes\ArrayOf;
use Dapr\Deserialization\Attributes\AsClass;
use Dapr\Deserialization\Attributes\Union;
use Dapr\Deserialization\Deserializer;
use Dapr\Deserialization\Deserializers\IDeserialize;
use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use DI\DependencyException;
use DI\NotFoundException;
use JetBrains\PhpStorm\ArrayShape;

/**
 * @param $obj
 *
 * @return mixed
 */
function deserialize_special_type($obj)
{
    return $obj['hello'];
}

/**
 * Class DeserializerTest
 */
final class DeserializerTest extends DaprTests
{
    #[ArrayShape([
        'Type'    => "array",
        'Nested'  => "array",
        'Complex' => "array",
        'Null'    => "array",
    ])] public function generate_deserializers(): array
    {
        $obj = new class {
            public string $foo = 'bar';
            public string $bar = 'baz';
        };

        $nested    = new class {
            #[AsClass(DateInterval::class)]
            public DateInterval $a;
        };
        $nested->a = new DateInterval('PT22M');

        $deserialized = json_decode(get_example_json(), true);
        $other_sdk    = get_example_object();
        $nullable     = new class {
            public string $test;
        };

        return [
            'Type'    => [[$obj::class, ['foo' => 'bar', 'bar' => 'baz']], $obj],
            'Nested'  => [[$nested::class, ['a' => 'PT22M']], $nested],
            'Complex' => [[WeatherForecastWithPOPOs::class, $deserialized], $other_sdk],
            'Null'    => [[$nullable::class, null], null],
        ];
    }

    /**
     * @dataProvider generate_deserializers
     *
     * @param $value
     * @param $expected
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testDeserializeValues($value, $expected)
    {
        $deserializer = $this->container->get(Deserializer::class);
        $result       = $deserializer->from_value($value[0], $value[1]);
        $this->assertEquals($expected, $result);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testDaprException()
    {
        $deserializer = $this->container->get(Deserializer::class);
        $obj          = [
            'errorCode' => 'ERR_ACTOR_INSTANCE_MISSING',
            'message'   => 'Error getting an actor instance. This means that actor is now hosted in some other service replica.',
        ];
        $this->assertTrue($deserializer->is_exception($obj));
        $exception = $deserializer->get_exception($obj);
        $this->assertSame($obj['errorCode'], $exception->get_dapr_error_code());
        $this->assertSame($obj['message'], $exception->getMessage());
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testPHPException()
    {
        $deserializer = $this->container->get(Deserializer::class);
        $obj          = [
            'errorCode' => LogicException::class,
            'message'   => 'should not happen',
            'file'      => __FILE__,
            'line'      => 123,
        ];
        $this->assertTrue($deserializer->is_exception($obj));
        $exception = $deserializer->get_exception($obj);
        $this->assertSame($obj['errorCode'], $exception->get_dapr_error_code());
        $this->assertSame($obj['message'], $exception->getMessage());
        $this->assertSame($obj['file'], $exception->getFile());
        $this->assertSame($obj['line'], $exception->getLine());
        $this->assertSame(null, $exception->getPrevious());
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testExceptionChain()
    {
        $deserializer = $this->container->get(Deserializer::class);
        $obj          = [
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
        $this->assertTrue($deserializer->is_exception($obj));
        $exception = $deserializer->get_exception($obj);
        $this->assertInstanceOf(DaprException::class, $exception->getPrevious());
    }

    function testDetectFromParameter()
    {
        $is_array_of   = fn(#[ArrayOf(DateTime::class)] $a = []) => $a;
        $is_class      = fn(#[AsClass(DateTime::class)] $a) => $a;
        $get_parameter = fn(callable $callback, int $idx = 0) => (new ReflectionFunction($callback))
            ->getParameters()[$idx];
        $check_date    = '2020-01-01';
        $expected      = new DateTime($check_date);
        $deserializer  = $this->container->get(Deserializer::class);
        $value         = $deserializer->detect_from_parameter($get_parameter($is_array_of), [$check_date]);
        $this->assertEquals([$expected], $value);
        $value = $deserializer->detect_from_parameter($get_parameter($is_class), $check_date);
        $this->assertEquals($expected, $value);
    }

    function testImplementIDeserialize()
    {
        $deserializer = $this->container->get(Deserializer::class);
        $type         = new class implements IDeserialize {
            public static function deserialize(mixed $value, IDeserializer $deserializer): mixed
            {
                return new class($value) {
                    public function __construct(public string $value)
                    {
                    }
                };
            }
        };

        $value = $deserializer->from_value($type::class, 'test');
        $this->assertSame('test', $value->value);
    }

    function testSimpleClass() {
        $deserializer = $this->container->get(Deserializer::class);
        $type = new class {
            public string $value;
        };
        $value = $deserializer->from_value($type::class, ['value' => 'test', 'unexpected' => 'hi']);
        $this->assertSame('test', $value->value);
        $this->assertSame('hi', $value->unexpected);
    }

    function testMethod() {
        $deserializer = $this->container->get(Deserializer::class);
        $test_class = new class {
            #[ArrayOf(DateTime::class)]
            public function test_array($value): array {
                return $value;
            }
            #[AsClass(DateTime::class)]
            public function test_value($value) {
                return $value;
            }
            public function test_reg($value): DateTime {
                return $value;
            }
        };
        $get_method = fn($method) => (new ReflectionClass($test_class))->getMethod($method);
        $date = '2020-01-01';
        $expected = new DateTime($date);
        $this->assertEquals([$expected], $deserializer->detect_from_method($get_method('test_array'), [$date]));
        $this->assertEquals($expected, $deserializer->detect_from_method($get_method('test_value'), $date));
        $this->assertEquals($expected, $deserializer->detect_from_method($get_method('test_reg'), $date));
    }
}
