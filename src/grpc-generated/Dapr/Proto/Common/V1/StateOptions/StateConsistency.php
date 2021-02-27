<?php

/**
 * This file is automatically generated by `composer build-grpc`
 */

namespace Dapr\Proto\Common\V1\StateOptions;

/**
 * Enum describing the supported consistency for state.
 *
 * Protobuf type <code>dapr.proto.common.v1.StateOptions.StateConsistency</code>
 */
class StateConsistency
{
	/** Generated from protobuf enum <code>CONSISTENCY_UNSPECIFIED = 0;</code> */
	public const CONSISTENCY_UNSPECIFIED = 0;

	/** Generated from protobuf enum <code>CONSISTENCY_EVENTUAL = 1;</code> */
	public const CONSISTENCY_EVENTUAL = 1;

	/** Generated from protobuf enum <code>CONSISTENCY_STRONG = 2;</code> */
	public const CONSISTENCY_STRONG = 2;

	private static $valueToName = ['CONSISTENCY_UNSPECIFIED', 'CONSISTENCY_EVENTUAL', 'CONSISTENCY_STRONG'];


	public static function name($value)
	{
		if (!isset(self::$valueToName[$value])) {
		    throw new \UnexpectedValueException(sprintf(
		            'Enum %s has no name defined for value %s', __CLASS__, $value));
		}
		return self::$valueToName[$value];
	}


	public static function value($name)
	{
		$const = __CLASS__ . '::' . strtoupper($name);
		if (!defined($const)) {
		    throw new \UnexpectedValueException(sprintf(
		            'Enum %s has no value defined for name %s', __CLASS__, $name));
		}
		return constant($const);
	}
}
