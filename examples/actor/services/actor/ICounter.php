<?php

use Dapr\Actors\Attributes\Get;
use Dapr\Actors\Attributes\Post;

/**
 * Interface ICounter
 */
#[\Dapr\Actors\Attributes\DaprType('Counter')]
interface ICounter
{
	/**
	 * @return int The current count
	 */
	#[Get]
	function get_count(): int;

	/**
	 * @return int The current count after incrementing
	 */
	#[Post]
	function increment_and_get(): int;
}
