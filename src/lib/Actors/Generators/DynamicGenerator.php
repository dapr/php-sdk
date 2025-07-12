<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\ActorReference;
use Dapr\Actors\Attributes\Delete;
use Dapr\Actors\Attributes\Get;
use Dapr\Actors\Attributes\Post;
use Dapr\Actors\Attributes\Put;
use Dapr\Actors\Internal\InternalProxy;
use Dapr\Client\DaprClient;
use JetBrains\PhpStorm\Pure;
use LogicException;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;

/**
 * Class DynamicGenerator
 *
 * Uses some quirks of PHP magic functions to provide a proxy
 *
 * @package Dapr\Actors\Generators
 */
class DynamicGenerator extends GenerateProxy
{

	#[Pure]
	public function __construct(
		string $interface,
		string $dapr_type,
		DaprClient $client,
	) {
		parent::__construct($interface, $dapr_type, $client);
	}

	#[\Override]
	public function get_proxy(string $id): InternalProxy
	{
		$current_proxy = new InternalProxy();
		$interface = InterfaceType::from($this->interface);
		$methods = $this->get_methods($interface);
		$current_proxy->DAPR_TYPE = $this->dapr_type;

		$reflection = new \ReflectionClass($current_proxy);
		$client = $reflection->getProperty('client');
		$client->setValue($current_proxy, $this->client);
		$reference = $reflection->getProperty('reference');
		$actor_reference = new ActorReference($id, $this->dapr_type);
		$reference->setValue($current_proxy, $actor_reference);

		foreach ($methods as $method) {
			$current_proxy->{$method->getName()} = $this->generate_method($method, $id);
		}

		$current_proxy->_get_actor_reference = fn(): \Dapr\Actors\ActorReference => $actor_reference;

		return $current_proxy;
	}

	#[\Override]
	protected function generate_failure_method(Method $method): callable
	{
		return function () use ($method) {
			throw new LogicException("Cannot call {$method->getName()} from outside the actor.");
		};
	}

	#[\Override]
	protected function generate_proxy_method(Method $method, string $id): callable
	{
		$http_method = count($method->getParameters()) == 0 ? 'GET' : 'POST';
		foreach ($method->getAttributes() as $attribute) {
			$http_method = match ($attribute->getName()) {
				Get::class => 'GET',
				Post::class => 'POST',
				Put::class => 'PUT',
				Delete::class => 'DELETE',
				default => $http_method
			};
		}
		$reference = new ActorReference($id, $this->dapr_type);
		$actor_method = $method->getName();
		$return_type = $method->getReturnType();

		return function (...$params) use ($id, $http_method, $reference, $actor_method, $return_type) {
			/**
			 * @var DaprClient $client
			 */
			$result = $this->client->invokeActorMethod(
				$http_method,
				$reference,
				$actor_method,
				$params[0] ?? null,
				$return_type ?? 'array',
			);

			if ($return_type) {
				return $result;
			}

			return;
		};
	}

	#[\Override]
	protected function generate_get_id(Method $method, string $id): callable
	{
		return function () use ($id) {
			return $id;
		};
	}
}
