<?php

/**
 * This file was automatically generated.
 */

namespace Dapr\Proxies;

use Dapr\Actors\ActorReference;
use Dapr\Actors\ActorTrait;
use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\IActor;
use Dapr\Client\DaprClient;

#[DaprType('TestActor')]
final class dapr_proxy_TestActor implements \Fixtures\ITestActor, IActor
{
	use ActorTrait;

	public string $id;
	public string $DAPR_TYPE = 'TestActor';
	private ActorReference $reference;


	#[\Dapr\Deserialization\Attributes\ArrayOf('string')]
	public function a_function(#[\Dapr\Deserialization\Attributes\AsClass('SimpleObject')] $value): array
	{
		$data = $value;
		$current_method = 'a_function';
		$http_method = 'POST';
		$result = $this->client->invokeActorMethod($http_method, $this->_get_actor_reference(), $current_method, $data ?? null, 'array');
		return $result;
	}


	public function empty_func()
	{
		$current_method = 'empty_func';
		$http_method = 'GET';
		$result = $this->client->invokeActorMethod($http_method, $this->_get_actor_reference(), $current_method, $data ?? null, 'array');
		return $result;
	}


	public function get_id(): string
	{
		return $this->id;
	}


	/**
	 * Handle a reminder
	 *
	 * @param string $name The name of the reminder
	 * @param Reminder $data The data from passed when the reminder was setup
	 */
	public function remind(string $name, \Dapr\Actors\Reminder $data): void
	{
		throw new \LogicException("Cannot call 'remind' outside the actor");
	}


	/**
	 * Called when the actor is activated
	 */
	public function on_activation(): void
	{
		throw new \LogicException("Cannot call 'on_activation' outside the actor");
	}


	/**
	 * Called when the actor is deactivated
	 */
	public function on_deactivation(): void
	{
		throw new \LogicException("Cannot call 'on_deactivation' outside the actor");
	}


	public function __construct(private DaprClient $client)
	{
	}


	private function _get_actor_reference(): ActorReference
	{
		return $this->reference ??= new ActorReference($this->id, $this->DAPR_TYPE);
	}
}
