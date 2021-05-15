<?php

namespace Dapr\Client\V1;

/**
 * Interface IDaprClient
 * @package Dapr\Client\V1
 */
interface IDaprClient
{
    public function invoke_service(
        InvokeServiceRequest $argument,
        array $metadata = [],
        array $options = []
    ): InvokeResponse;

    public function get_state(GetStateRequest $argument, array $metadata = [], array $options = []): GetStateResponse;

    public function get_bulk_state(
        GetBulkStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): GetBulkStateResponse;

    public function save_state(SaveStateRequest $argument, array $metadata = [], array $options = []): void;

    public function delete_state(DeleteStateRequest $argument, array $metadata = [], array $options = []): void;

    public function delete_bulk_state(
        DeleteBulkStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): void;

    public function execute_state_transaction(
        ExecuteStateTransactionRequest $argument,
        array $metadata = [],
        array $options = []
    ): void;

    public function publish_event(PublishEventRequest $argument, array $metadata = [], array $options = []): void;

    public function invoke_binding(
        InvokeBindingRequest $argument,
        array $metadata = [],
        array $options = []
    ): InvokeBindingResponse;

    public function get_secret(
        GetSecretRequest $argument,
        array $metadata = [],
        array $options = []
    ): GetSecretResponse;

    public function get_bulk_secret(
        GetBulkSecretRequest $argument,
        array $metadata = [],
        array $options = []
    ): GetBulkSecretResponse;

    public function register_actor_timer(
        RegisterActorTimerRequest $argument,
        array $metadata = [],
        array $options = []
    ): void;

    public function unregister_actor_timer(
        UnregisterActorTimerRequest $argument,
        array $metadata = [],
        array $options = []
    ): void;

    public function register_actor_reminder(
        RegisterActorReminderRequest $argument,
        array $metadata = [],
        array $options = []
    ): void;

    public function unregister_actor_reminder(
        UnregisterActorReminderRequest $argument,
        array $metadata = [],
        array $options = []
    ): void;

    public function get_actor_state(
        GetActorStateRequest $argument,
        array $metadata = [],
        array $options = []
    ): GetActorStateResponse;

    public function execute_actor_transaction(
        ExecuteActorStateTransactionRequest $argument,
        array $metadata = [],
        array $options = []
    ): void;

    public function invoke_actor(
        InvokeActorRequest $argument,
        array $metadata = [],
        array $options = []
    ): InvokeActorResponse;

    public function get_metadata(array $metadata = [], array $options = []): GetMetadataResponse;

    public function set_metadata(SetMetadataRequest $argument, array $metadata = [], array $options = []): void;

    public function shutdown(array $metadata = [], array $options = []): void;
}
