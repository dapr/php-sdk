<?php

namespace Dapr\Client\V1;

use Dapr\Client\Interfaces\V1\DaprClientInterface;
use Dapr\Proto\Runtime\V1\DaprClient;

/**
 * Class GrpcClient
 * @package Dapr\Client\V1
 */
class GrpcClient implements DaprClientInterface
{
    public function __construct(private DaprClient $daprClient)
    {
    }

    /**
     * @inheritDoc
     */
    public function InvokeService(
        \Dapr\Proto\Runtime\V1\InvokeServiceRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->InvokeService($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function GetState(
        \Dapr\Proto\Runtime\V1\GetStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->GetState($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function GetBulkState(
        \Dapr\Proto\Runtime\V1\GetBulkStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->GetBulkState($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function SaveState(
        \Dapr\Proto\Runtime\V1\SaveStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->SaveState($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function DeleteState(
        \Dapr\Proto\Runtime\V1\DeleteStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->DeleteState($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function DeleteBulkState(
        \Dapr\Proto\Runtime\V1\DeleteBulkStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->DeleteBulkState($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function ExecuteStateTransaction(
        \Dapr\Proto\Runtime\V1\ExecuteStateTransactionRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->ExecuteStateTransaction($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function PublishEvent(
        \Dapr\Proto\Runtime\V1\PublishEventRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->PublishEvent($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function InvokeBinding(
        \Dapr\Proto\Runtime\V1\InvokeBindingRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->InvokeBinding($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function GetSecret(
        \Dapr\Proto\Runtime\V1\GetSecretRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->GetSecret($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function GetBulkSecret(
        \Dapr\Proto\Runtime\V1\GetBulkSecretRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->GetBulkSecret($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function RegisterActorTimer(
        \Dapr\Proto\Runtime\V1\RegisterActorTimerRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->RegisterActorTimer($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function UnregisterActorTimer(
        \Dapr\Proto\Runtime\V1\UnregisterActorTimerRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->UnregisterActorTimer($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function RegisterActorReminder(
        \Dapr\Proto\Runtime\V1\RegisterActorReminderRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->RegisterActorReminder($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function UnregisterActorReminder(
        \Dapr\Proto\Runtime\V1\UnregisterActorReminderRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->UnregisterActorReminder($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function GetActorState(
        \Dapr\Proto\Runtime\V1\GetActorStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->GetActorState($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function ExecuteActorStateTransaction(
        \Dapr\Proto\Runtime\V1\ExecuteActorStateTransactionRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->ExecuteActorStateTransaction($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function InvokeActor(
        \Dapr\Proto\Runtime\V1\InvokeActorRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->InvokeActor($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function GetMetadata(
        \Google\Protobuf\GPBEmpty $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->GetMetadata($argument, $metadata, $options);
    }

    /**
     * @inheritDoc
     */
    public function SetMetadata(
        \Dapr\Proto\Runtime\V1\SetMetadataRequest $argument,
        array $metadata = [],
        array $options = []
    ): \Grpc\UnaryCall {
        return $this->daprClient->SetMetadata($argument, $metadata, $options);
    }
}
