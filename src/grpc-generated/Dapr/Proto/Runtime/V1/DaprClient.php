<?php
// GENERATED CODE -- DO NOT EDIT!

// Original file comments:
// ------------------------------------------------------------
// Copyright (c) Microsoft Corporation and Dapr Contributors.
// Licensed under the MIT License.
// ------------------------------------------------------------
//
namespace Dapr\Proto\Runtime\V1;

/**
 * Dapr service provides APIs to user application to access Dapr building blocks.
 */
class DaprClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Invokes a method on a remote Dapr app.
     * @param \Dapr\Proto\Runtime\V1\InvokeServiceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function InvokeService(\Dapr\Proto\Runtime\V1\InvokeServiceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/InvokeService',
        $argument,
        ['\Dapr\Proto\Common\V1\InvokeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Gets the state for a specific key.
     * @param \Dapr\Proto\Runtime\V1\GetStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetState(\Dapr\Proto\Runtime\V1\GetStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/GetState',
        $argument,
        ['\Dapr\Proto\Runtime\V1\GetStateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Gets a bulk of state items for a list of keys
     * @param \Dapr\Proto\Runtime\V1\GetBulkStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetBulkState(\Dapr\Proto\Runtime\V1\GetBulkStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/GetBulkState',
        $argument,
        ['\Dapr\Proto\Runtime\V1\GetBulkStateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Saves the state for a specific key.
     * @param \Dapr\Proto\Runtime\V1\SaveStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function SaveState(\Dapr\Proto\Runtime\V1\SaveStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/SaveState',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Deletes the state for a specific key.
     * @param \Dapr\Proto\Runtime\V1\DeleteStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteState(\Dapr\Proto\Runtime\V1\DeleteStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/DeleteState',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Deletes a bulk of state items for a list of keys
     * @param \Dapr\Proto\Runtime\V1\DeleteBulkStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteBulkState(\Dapr\Proto\Runtime\V1\DeleteBulkStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/DeleteBulkState',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Executes transactions for a specified store
     * @param \Dapr\Proto\Runtime\V1\ExecuteStateTransactionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExecuteStateTransaction(\Dapr\Proto\Runtime\V1\ExecuteStateTransactionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/ExecuteStateTransaction',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Publishes events to the specific topic.
     * @param \Dapr\Proto\Runtime\V1\PublishEventRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function PublishEvent(\Dapr\Proto\Runtime\V1\PublishEventRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/PublishEvent',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Invokes binding data to specific output bindings
     * @param \Dapr\Proto\Runtime\V1\InvokeBindingRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function InvokeBinding(\Dapr\Proto\Runtime\V1\InvokeBindingRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/InvokeBinding',
        $argument,
        ['\Dapr\Proto\Runtime\V1\InvokeBindingResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Gets secrets from secret stores.
     * @param \Dapr\Proto\Runtime\V1\GetSecretRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetSecret(\Dapr\Proto\Runtime\V1\GetSecretRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/GetSecret',
        $argument,
        ['\Dapr\Proto\Runtime\V1\GetSecretResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Gets a bulk of secrets
     * @param \Dapr\Proto\Runtime\V1\GetBulkSecretRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetBulkSecret(\Dapr\Proto\Runtime\V1\GetBulkSecretRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/GetBulkSecret',
        $argument,
        ['\Dapr\Proto\Runtime\V1\GetBulkSecretResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Register an actor timer.
     * @param \Dapr\Proto\Runtime\V1\RegisterActorTimerRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RegisterActorTimer(\Dapr\Proto\Runtime\V1\RegisterActorTimerRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/RegisterActorTimer',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Unregister an actor timer.
     * @param \Dapr\Proto\Runtime\V1\UnregisterActorTimerRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UnregisterActorTimer(\Dapr\Proto\Runtime\V1\UnregisterActorTimerRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/UnregisterActorTimer',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Register an actor reminder.
     * @param \Dapr\Proto\Runtime\V1\RegisterActorReminderRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RegisterActorReminder(\Dapr\Proto\Runtime\V1\RegisterActorReminderRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/RegisterActorReminder',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Unregister an actor reminder.
     * @param \Dapr\Proto\Runtime\V1\UnregisterActorReminderRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UnregisterActorReminder(\Dapr\Proto\Runtime\V1\UnregisterActorReminderRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/UnregisterActorReminder',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Gets the state for a specific actor.
     * @param \Dapr\Proto\Runtime\V1\GetActorStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetActorState(\Dapr\Proto\Runtime\V1\GetActorStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/GetActorState',
        $argument,
        ['\Dapr\Proto\Runtime\V1\GetActorStateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Executes state transactions for a specified actor
     * @param \Dapr\Proto\Runtime\V1\ExecuteActorStateTransactionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExecuteActorStateTransaction(\Dapr\Proto\Runtime\V1\ExecuteActorStateTransactionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/ExecuteActorStateTransaction',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * InvokeActor calls a method on an actor.
     * @param \Dapr\Proto\Runtime\V1\InvokeActorRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function InvokeActor(\Dapr\Proto\Runtime\V1\InvokeActorRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/InvokeActor',
        $argument,
        ['\Dapr\Proto\Runtime\V1\InvokeActorResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Gets metadata of the sidecar
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetMetadata(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/GetMetadata',
        $argument,
        ['\Dapr\Proto\Runtime\V1\GetMetadataResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Sets value in extended metadata of the sidecar
     * @param \Dapr\Proto\Runtime\V1\SetMetadataRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function SetMetadata(\Dapr\Proto\Runtime\V1\SetMetadataRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/dapr.proto.runtime.v1.Dapr/SetMetadata',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

}
