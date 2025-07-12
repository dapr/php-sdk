<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\IActor;
use Dapr\Client\DaprClient;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;

/**
 * Class GenerateProxy
 *
 * Base class that provides some basic functionality.
 *
 * @package Dapr\Actors\Generators
 */
abstract class GenerateProxy implements IGenerateProxy
{

	public function __construct(
		protected string $interface,
		protected string $dapr_type,
		protected DaprClient $client,
	) {}

	protected function generate_method(Method $method, string $id): Method|callable|null
	{
		return match ($method->getName()) {
			'remind', 'on_activation', 'on_deactivation' => $this->generate_failure_method($method),
			'create_reminder', 'get_reminder', 'delete_reminder', 'create_timer', 'delete_timer' => null,
			'get_id' => $this->generate_get_id($method, $id),
			default => $this->generate_proxy_method($method, $id),
		};
	}

	/**
	 * Generate a method that shouldn't be called from outside the actor.
	 *
	 * @param Method $method The method
	 *
	 * @return Method
	 */
	protected abstract function generate_failure_method(Method $method);

	/**
	 * Write a method to get the current actor id.
	 *
	 * @param Method $method
	 * @param string $id
	 *
	 * @return Method
	 */
	protected abstract function generate_get_id(Method $method, string $id);

	/**
	 * Write a method that calls the actor.
	 *
	 * @param Method $method
	 * @param string $id
	 *
	 * @return Method
	 */
	protected abstract function generate_proxy_method(Method $method, string $id);

	/**
	 * @param ClassType $interface
	 *
	 * @return Method[] available methods for the interface
	 */
	protected function get_methods(ClassType|InterfaceType $interface): array
	{
		return [...$interface->getMethods(), ...ClassType::from(IActor::class)->getMethods()];
	}

	protected function get_full_class_name(): string
	{
		return "\\" . $this->get_namespace() . "\\" . $this->get_short_class_name();
	}

	protected function get_namespace(): string
	{
		return "Dapr\\Proxies";
	}

	protected function get_short_class_name(): string
	{
		$internal_type = preg_replace('/[^a-zA-Z0-9_]*/', '', $this->dapr_type);

		return 'dapr_proxy_' . $internal_type;
	}
}
