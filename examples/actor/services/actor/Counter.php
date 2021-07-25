<?php

use Dapr\Actors\Actor;
use Dapr\Actors\Attributes\Get;
use Dapr\Actors\Attributes\Post;

/**
 * Class Counter
 */
#[\Dapr\Actors\Attributes\DaprType('Counter')]
class Counter extends Actor implements ICounter
{
	public function __construct(string $id, private State $state)
	{
		parent::__construct($id);
	}
	
	#[Get]
	function get_count(): int
	{
		return $this->state->count;
	}
	
	#[Post]
	function increment_and_get(): int
	{
		return $this->state->count += 1;
	}
}
