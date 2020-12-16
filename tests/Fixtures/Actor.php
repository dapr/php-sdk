<?php

namespace Fixtures;

use Dapr\Actors\Actor;
use Dapr\Actors\IActor;
use Dapr\consistency\StrongLastWrite;
use Dapr\State\State;

interface ActorInterface extends IActor
{
    public const DAPR_TYPE = 'ActorClass';

    public function a_function($value): bool;
}

class ActorState extends State
{
    public string $value = "";
}

class ActorClass implements ActorInterface
{
    use Actor;
    use \Dapr\Actors\ActorState;

    public const STATE_TYPE = [
        'store'       => 'store',
        'type'        => ActorState::class,
        'consistency' => StrongLastWrite::class,
    ];

    /**
     * ActorClass constructor.
     *
     * @param string $id
     * @param ActorState $state
     */
    public function __construct(private string $id, private $state)
    {
    }

    public function a_function($value): bool
    {
        $this->state->value = $value;

        return true;
    }

    function get_id(): mixed
    {
        // TODO: Implement get_id() method.
    }

    function remind(string $name, $data): void
    {
        // TODO: Implement remind() method.
    }

    function on_activation(): void
    {
        // TODO: Implement on_activation() method.
    }

    function on_deactivation(): void
    {
        // TODO: Implement on_deactivation() method.
    }
}
