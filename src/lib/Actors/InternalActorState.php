<?php

namespace Dapr\Actors;

use Dapr\consistency\Consistency;
use Dapr\consistency\EventualLastWrite;
use Dapr\State\Internal\Internal\State;
use Dapr\State\Internal\Internal\TransactionalState;
use LogicException;

/**
 * Wrapper around Transactional State for actors.
 */
class InternalActorState extends TransactionalState
{

    /**
     * Constructs the state wrapper
     *
     * @param State $state
     * @param string $store_name
     * @param Consistency $consistency
     * @param string $actor_type
     * @param $actor_id
     */
    protected function __construct(
        State $state,
        string $store_name,
        Consistency $consistency,
        public string $actor_type,
        public $actor_id
    ) {
        parent::__construct($state, $store_name, $consistency);
    }

    /**
     * Prevent calling begin() on actor state.
     * @ignore
     */
    public static function begin(
        string $type,
        ?string $store_name = null,
        ?Consistency $consistency = null
    ): TransactionalState {
        throw new LogicException();
    }

    /**
     * Begin with an actor transactional store
     *
     * @param string $actor_type The dapr actor type
     * @param mixed $actor_id The actor id
     * @param string $state_type The type of the state
     * @param string $store_name The name of the actor store
     * @param Consistency|null $consistency The consistency of the store
     *
     * @return InternalActorState
     */
    public static function begin_actor(
        string $actor_type,
        $actor_id,
        string $state_type,
        string $store_name,
        ?Consistency $consistency = null
    ): TransactionalState {
        $key_prepend = "$actor_type||$actor_id||";
        $state       = new $state_type($store_name, null, $key_prepend);
        $consistency = $consistency ?? new EventualLastWrite();
        $state->load();

        return new InternalActorState($state, $store_name, $consistency, $actor_type, $actor_id);
    }

    /**
     * Overrides the state endpoint.
     */
    protected function get_save_endpoint(): string
    {
        return "/actors/{$this->actor_type}/{$this->actor_id}/state";
    }

    protected function _commit(array $metadata = [], bool $full = false): bool
    {
        return parent::_commit($metadata, $full);
    }
}
