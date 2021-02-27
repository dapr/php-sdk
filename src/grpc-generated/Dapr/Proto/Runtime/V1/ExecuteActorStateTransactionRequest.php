<?php

/**
 * This file is automatically generated by `composer build-grpc`
 */

namespace Dapr\Proto\Runtime\V1;

/**
 * ExecuteActorStateTransactionRequest is the message to execute multiple operations on a specified actor.
 *
 * Generated from protobuf message <code>dapr.proto.runtime.v1.ExecuteActorStateTransactionRequest</code>
 */
class ExecuteActorStateTransactionRequest extends \Google\Protobuf\Internal\Message
{
	/** Generated from protobuf field <code>string actor_type = 1;</code> */
	protected $actor_type = '';

	/** Generated from protobuf field <code>string actor_id = 2;</code> */
	protected $actor_id = '';

	/** Generated from protobuf field <code>repeated .dapr.proto.runtime.v1.TransactionalActorStateOperation operations = 3;</code> */
	private $operations;


	/**
	 * Constructor.
	 *
	 * @param array $data {
	 *     Optional. Data for populating the Message object.
	 *
	 *     @type string $actor_type
	 *     @type string $actor_id
	 *     @type \Dapr\Proto\Runtime\V1\TransactionalActorStateOperation[]|\Google\Protobuf\Internal\RepeatedField $operations
	 * }
	 */
	public function __construct(array|null $data = null)
	{
		\GPBMetadata\Dapr\Proto\Runtime\V1\Dapr::initOnce();
		parent::__construct($data);
	}


	/**
	 * Generated from protobuf field <code>string actor_type = 1;</code>
	 * @return string
	 */
	public function getActorType(): string
	{
		return $this->actor_type;
	}


	/**
	 * Generated from protobuf field <code>string actor_type = 1;</code>
	 * @param string $var
	 * @return $this
	 */
	public function setActorType(string $var): ExecuteActorStateTransactionRequest
	{
		\Google\Protobuf\Internal\GPBUtil::checkString($var, True);
		$this->actor_type = $var;

		return $this;
	}


	/**
	 * Generated from protobuf field <code>string actor_id = 2;</code>
	 * @return string
	 */
	public function getActorId(): string
	{
		return $this->actor_id;
	}


	/**
	 * Generated from protobuf field <code>string actor_id = 2;</code>
	 * @param string $var
	 * @return $this
	 */
	public function setActorId(string $var): ExecuteActorStateTransactionRequest
	{
		\Google\Protobuf\Internal\GPBUtil::checkString($var, True);
		$this->actor_id = $var;

		return $this;
	}


	/**
	 * Generated from protobuf field <code>repeated .dapr.proto.runtime.v1.TransactionalActorStateOperation operations = 3;</code>
	 * @return \Google\Protobuf\Internal\RepeatedField
	 */
	public function getOperations(): \Google\Protobuf\Internal\RepeatedField
	{
		return $this->operations;
	}


	/**
	 * Generated from protobuf field <code>repeated .dapr.proto.runtime.v1.TransactionalActorStateOperation operations = 3;</code>
	 * @param \Dapr\Proto\Runtime\V1\TransactionalActorStateOperation[]|\Google\Protobuf\Internal\RepeatedField $var
	 * @return $this
	 */
	public function setOperations(array|\Google\Protobuf\Internal\RepeatedField $var): ExecuteActorStateTransactionRequest
	{
		$arr = \Google\Protobuf\Internal\GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \Dapr\Proto\Runtime\V1\TransactionalActorStateOperation::class);
		$this->operations = $arr;

		return $this;
	}
}
