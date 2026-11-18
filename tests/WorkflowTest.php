<?php

use GuzzleHttp\Psr7\Response;

require_once __DIR__ . '/DaprTests.php';

class WorkflowTest extends DaprTests
{
    public function testTerminateWorkflowSendsReasonAndTargetAppId(): void
    {
        $stack = $this->get_http_client_stack([new Response(202)]);
        $client = $this->get_new_client_with_http($stack->client);

        $client->terminateWorkflow(
            workflowComponent: 'dapr',
            instanceId: 'order-123',
            reason: 'cancelled by operator',
            appId: 'orders'
        );

        $request = $stack->history[0]['request'];
        $this->assertRequestMethod('POST', $request);
        $this->assertRequestUri('/v1.0/workflows/dapr/order-123/terminate', $request);
        $this->assertRequestBody('', $request);

        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(
            ['reason' => 'cancelled by operator', 'appID' => 'orders'],
            $query
        );
    }

    public function testTerminateWorkflowOmitsAnUnspecifiedReason(): void
    {
        $stack = $this->get_http_client_stack([new Response(202)]);
        $client = $this->get_new_client_with_http($stack->client);

        $client->terminateWorkflow('dapr', 'order-123');

        $request = $stack->history[0]['request'];
        $this->assertRequestMethod('POST', $request);
        $this->assertRequestUri('/v1.0/workflows/dapr/order-123/terminate', $request);
        $this->assertSame('', $request->getUri()->getQuery());
    }

    public function testTerminateWorkflowPreservesAnExplicitlyEmptyReason(): void
    {
        $stack = $this->get_http_client_stack([new Response(202)]);
        $client = $this->get_new_client_with_http($stack->client);

        $client->terminateWorkflow('dapr', 'order-123', '');

        $request = $stack->history[0]['request'];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertArrayHasKey('reason', $query);
        $this->assertSame('', $query['reason']);
    }

    public function testTerminateWorkflowAsyncResolvesAfterTheRequestSucceeds(): void
    {
        $stack = $this->get_http_client_stack([new Response(202)]);
        $client = $this->get_new_client_with_http($stack->client);

        $result = $client->terminateWorkflowAsync('dapr', 'order-123', 'operator request')->wait();

        $this->assertNull($result);
        $this->assertRequestMethod('POST', $stack->history[0]['request']);
    }

    public function testGetWorkflowReturnsMetadataWithTerminationReason(): void
    {
        $body = json_encode([
            'createdAt' => '2026-11-16T00:00:00Z',
            'instanceID' => 'order-123',
            'lastUpdatedAt' => '2026-11-16T00:01:00Z',
            'runtimeStatus' => 'TERMINATED',
            'properties' => [
                'dapr.workflow.custom_status' => 'cancelled by operator',
            ],
        ], JSON_THROW_ON_ERROR);

        $stack = $this->get_http_client_stack([new Response(200, body: $body)]);
        $client = $this->get_new_client_with_http($stack->client);

        $workflow = $client->getWorkflow('dapr', 'order-123', 'orders');

        $this->assertSame('TERMINATED', $workflow['runtimeStatus']);
        $this->assertSame(
            'cancelled by operator',
            $workflow['properties']['dapr.workflow.custom_status']
        );

        $request = $stack->history[0]['request'];
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestUri('/v1.0/workflows/dapr/order-123', $request);
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['appID' => 'orders'], $query);
    }

    public function testGetWorkflowAsyncReturnsDecodedMetadata(): void
    {
        $body = json_encode([
            'instanceID' => 'order-123',
            'runtimeStatus' => 'TERMINATED',
            'properties' => [
                'dapr.workflow.custom_status' => 'operator request',
            ],
        ], JSON_THROW_ON_ERROR);

        $stack = $this->get_http_client_stack([new Response(200, body: $body)]);
        $client = $this->get_new_client_with_http($stack->client);

        $workflow = $client->getWorkflowAsync('dapr', 'order-123')->wait();

        $this->assertSame('operator request', $workflow['properties']['dapr.workflow.custom_status']);
        $this->assertRequestMethod('GET', $stack->history[0]['request']);
        $this->assertSame('', $stack->history[0]['request']->getUri()->getQuery());
    }
}
