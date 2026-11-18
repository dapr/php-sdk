---
type: docs
title: "Workflows with PHP"
linkTitle: "Workflows"
weight: 1000
description: Manage Dapr workflow instances from PHP
no_list: true
---

The PHP client exposes the Dapr workflow termination and metadata HTTP APIs. Use these methods to stop a running workflow and inspect the reason after it has been terminated.

## Terminate a workflow

Pass a reason to record why an instance was terminated. The reason is optional and is stored as the workflow termination input, exposed through the existing custom-status property.

```php
$client->terminateWorkflow(
    workflowComponent: 'dapr',
    instanceId: 'order-123',
    reason: 'cancelled by operator'
);
```

To terminate a workflow owned by another application in the same namespace, pass its application ID:

```php
$client->terminateWorkflow(
    workflowComponent: 'dapr',
    instanceId: 'order-123',
    reason: 'cancelled by operator',
    appId: 'orders'
);
```

A null reason is omitted. Passing an empty string explicitly sends an empty reason.

## Read the termination reason

Retrieve the workflow metadata and read the reason from the `properties` map under `dapr.workflow.custom_status`:

```php
$workflow = $client->getWorkflow(
    workflowComponent: 'dapr',
    instanceId: 'order-123'
);

$terminationReason = $workflow['properties']['dapr.workflow.custom_status'] ?? null;
```

When no reason is supplied, the existing workflow custom status remains unchanged.

The asynchronous variants are `terminateWorkflowAsync()` and `getWorkflowAsync()`.

## API compatibility

The PHP SDK currently uses Dapr's HTTP workflow API at `/v1.0/workflows`. Dapr marks this HTTP API as deprecated in favor of workflow SDK clients that use gRPC. The PHP SDK does not currently provide a gRPC workflow client.

**Runtime compatibility:** the termination-reason runtime change is still in draft. The Dapr runtime maps the optional `reason` query parameter to its workflow termination request and exposes a supplied reason through `dapr.workflow.custom_status`.
