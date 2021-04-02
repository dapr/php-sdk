<?php

class RestClient implements \Dapr\Client\Interfaces\V1\DaprClientInterface
{
    public function InvokeService(
        \Dapr\Client\Interfaces\V1\InvokeServiceRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement InvokeService() method.
    }

    public function GetState(
        \Dapr\Client\Interfaces\V1\GetStateRequestInterface $argument,
        $metadata = [],
        $options = []
    ): \Dapr\Client\Interfaces\V1\GetStateResponseInterface {
        // TODO: Implement GetState() method.
    }

    public function GetBulkState(
        \Dapr\Client\Interfaces\V1\GetBulkStateRequestInterface $argument,
        $metadata = [],
        $options = []
    ): \Dapr\Client\Interfaces\V1\GetBulkStateResponseInterface {
        // TODO: Implement GetBulkState() method.
    }

    public function SaveState(
        \Dapr\Client\Interfaces\V1\SaveStateRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement SaveState() method.
    }

    public function DeleteState(
        \Dapr\Client\Interfaces\V1\DeleteStateRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement DeleteState() method.
    }

    public function DeleteBulkState(
        \Dapr\Client\Interfaces\V1\DeleteBulkStateRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement DeleteBulkState() method.
    }

    public function ExecuteStateTransaction(
        \Dapr\Client\Interfaces\V1\ExecuteStateTransactionRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement ExecuteStateTransaction() method.
    }

    public function PublishEvent(
        \Dapr\Client\Interfaces\V1\PublishEventRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement PublishEvent() method.
    }

    public function InvokeBinding(
        \Dapr\Client\Interfaces\V1\InvokeBindingRequestInterface $argument,
        $metadata = [],
        $options = []
    ): \Dapr\Client\Interfaces\V1\InvokeBindingResponseInterface {
        // TODO: Implement InvokeBinding() method.
    }

    public function GetSecret(
        \Dapr\Client\Interfaces\V1\GetSecretRequestInterface $argument,
        $metadata = [],
        $options = []
    ): \Dapr\Client\Interfaces\V1\GetSecretResponseInterface {
        // TODO: Implement GetSecret() method.
    }

    public function GetBulkSecret(
        \Dapr\Client\Interfaces\V1\GetBulkSecretRequestInterface $argument,
        $metadata = [],
        $options = []
    ): \Dapr\Client\Interfaces\V1\GetBulkSecretResponseInterface {
        // TODO: Implement GetBulkSecret() method.
    }

    public function RegisterActorTimer(
        \Dapr\Client\Interfaces\V1\RegisterActorTimerRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement RegisterActorTimer() method.
    }

    public function UnregisterActorTimer(
        \Dapr\Client\Interfaces\V1\UnregisterActorTimerRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement UnregisterActorTimer() method.
    }

    public function RegisterActorReminder(
        \Dapr\Client\Interfaces\V1\RegisterActorReminderRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement RegisterActorReminder() method.
    }

    public function UnregisterActorReminder(
        \Dapr\Client\Interfaces\V1\UnregisterActorReminderRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement UnregisterActorReminder() method.
    }

    public function GetActorState(
        \Dapr\Client\Interfaces\V1\GetActorStateRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement GetActorState() method.
    }

    public function ExecuteActorStateTransaction(
        \Dapr\Client\Interfaces\V1\ExecuteActorStateTransactionRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement ExecuteActorStateTransaction() method.
    }

    public function InvokeActor(
        \Dapr\Client\Interfaces\V1\InvokeActorRequestInterface $argument,
        $metadata = [],
        $options = []
    ): \Dapr\Client\Interfaces\V1\InvokeActorResponseInterface {
        // TODO: Implement InvokeActor() method.
    }

    public function GetMetadata(\Dapr\Client\Interfaces\V1\GPBEmptyInterface $argument, $metadata = [], $options = [])
    {
        // TODO: Implement GetMetadata() method.
    }

    public function SetMetadata(
        \Dapr\Client\Interfaces\V1\SetMetadataRequestInterface $argument,
        $metadata = [],
        $options = []
    ) {
        // TODO: Implement SetMetadata() method.
    }

    public function Shutdown(\Dapr\Client\Interfaces\V1\GPBEmptyInterface $argument, $metadata = [], $options = [])
    {
        // TODO: Implement Shutdown() method.
    }
}
