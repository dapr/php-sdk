<?php

/**
 * This file is automatically generated by `composer build-grpc`
 */

namespace Dapr\Proto\Runtime\V1;

/**
 * SaveStateRequest is the message to save multiple states into state store.
 *
 * Generated from protobuf message <code>dapr.proto.runtime.v1.SaveStateRequest</code>
 */
class SaveStateRequest extends \Google\Protobuf\Internal\Message
{
	/**
	 * The name of state store.
	 *
	 * Generated from protobuf field <code>string store_name = 1;</code>
	 */
	protected $store_name = '';

	/**
	 * The array of the state key values.
	 *
	 * Generated from protobuf field <code>repeated .dapr.proto.common.v1.StateItem states = 2;</code>
	 */
	private $states;


	/**
	 * Constructor.
	 *
	 * @param array $data {
	 *     Optional. Data for populating the Message object.
	 *
	 *     @type string $store_name
	 *           The name of state store.
	 *     @type \Dapr\Proto\Common\V1\StateItem[]|\Google\Protobuf\Internal\RepeatedField $states
	 *           The array of the state key values.
	 * }
	 */
	public function __construct(array|null $data = null)
	{
		\GPBMetadata\Dapr\Proto\Runtime\V1\Dapr::initOnce();
		parent::__construct($data);
	}


	/**
	 * The name of state store.
	 *
	 * Generated from protobuf field <code>string store_name = 1;</code>
	 * @return string
	 */
	public function getStoreName(): string
	{
		return $this->store_name;
	}


	/**
	 * The name of state store.
	 *
	 * Generated from protobuf field <code>string store_name = 1;</code>
	 * @param string $var
	 * @return $this
	 */
	public function setStoreName(string $var): SaveStateRequest
	{
		\Google\Protobuf\Internal\GPBUtil::checkString($var, True);
		$this->store_name = $var;

		return $this;
	}


	/**
	 * The array of the state key values.
	 *
	 * Generated from protobuf field <code>repeated .dapr.proto.common.v1.StateItem states = 2;</code>
	 * @return \Google\Protobuf\Internal\RepeatedField
	 */
	public function getStates(): \Google\Protobuf\Internal\RepeatedField
	{
		return $this->states;
	}


	/**
	 * The array of the state key values.
	 *
	 * Generated from protobuf field <code>repeated .dapr.proto.common.v1.StateItem states = 2;</code>
	 * @param \Dapr\Proto\Common\V1\StateItem[]|\Google\Protobuf\Internal\RepeatedField $var
	 * @return $this
	 */
	public function setStates(array|\Google\Protobuf\Internal\RepeatedField $var): SaveStateRequest
	{
		$arr = \Google\Protobuf\Internal\GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \Dapr\Proto\Common\V1\StateItem::class);
		$this->states = $arr;

		return $this;
	}
}
