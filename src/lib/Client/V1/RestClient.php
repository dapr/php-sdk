<?php

namespace Dapr\Client\V1;

use Dapr\Client\Interfaces\V1\DaprClientInterface;

/**
 * Class RestClient
 * @package Dapr\Client\V1
 */
class RestClient implements DaprClientInterface {
    public function InvokeService(
        \Dapr\Proto\Runtime\V1\InvokeServiceRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function GetState(
        \Dapr\Proto\Runtime\V1\GetStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function GetBulkState(
        \Dapr\Proto\Runtime\V1\GetBulkStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function SaveState(
        \Dapr\Proto\Runtime\V1\SaveStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function DeleteState(
        \Dapr\Proto\Runtime\V1\DeleteStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function DeleteBulkState(
        \Dapr\Proto\Runtime\V1\DeleteBulkStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function ExecuteStateTransaction(
        \Dapr\Proto\Runtime\V1\ExecuteStateTransactionRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function PublishEvent(
        \Dapr\Proto\Runtime\V1\PublishEventRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function InvokeBinding(
        \Dapr\Proto\Runtime\V1\InvokeBindingRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function GetSecret(
        \Dapr\Proto\Runtime\V1\GetSecretRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function GetBulkSecret(
        \Dapr\Proto\Runtime\V1\GetBulkSecretRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function RegisterActorTimer(
        \Dapr\Proto\Runtime\V1\RegisterActorTimerRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function UnregisterActorTimer(
        \Dapr\Proto\Runtime\V1\UnregisterActorTimerRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function RegisterActorReminder(
        \Dapr\Proto\Runtime\V1\RegisterActorReminderRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function UnregisterActorReminder(
        \Dapr\Proto\Runtime\V1\UnregisterActorReminderRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function GetActorState(
        \Dapr\Proto\Runtime\V1\GetActorStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function ExecuteActorStateTransaction(
        \Dapr\Proto\Runtime\V1\ExecuteActorStateTransactionRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function InvokeActor(
        \Dapr\Proto\Runtime\V1\InvokeActorRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function GetMetadata(
        \Google\Protobuf\GPBEmpty $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }

    public function SetMetadata(
        \Dapr\Proto\Runtime\V1\SetMetadataRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        throw new \LogicException('not implemented');
    }
}
