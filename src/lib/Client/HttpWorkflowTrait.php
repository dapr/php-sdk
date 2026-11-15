<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP workflow-management operations.
 */
trait HttpWorkflowTrait
{
    use PromiseHandlingTrait;

    public IDeserializer $deserializer;
    protected Client $httpClient;

    /**
     * Terminates a workflow instance.
     *
     * The reason is optional and is persisted separately from the workflow
     * output by runtimes that support workflow termination reasons.
     *
     * @throws \Dapr\exceptions\DaprException
     */
    public function terminateWorkflow(
        string $workflowComponent,
        string $instanceId,
        ?string $reason = null,
        ?string $appId = null
    ): void {
        $this->terminateWorkflowAsync($workflowComponent, $instanceId, $reason, $appId)->wait(true);
    }

    /**
     * Terminates a workflow instance asynchronously.
     *
     * A null reason is omitted from the request. An explicitly empty reason is
     * sent as reason=, preserving protobuf field presence semantics.
     *
     * @return PromiseInterface<null>
     */
    public function terminateWorkflowAsync(
        string $workflowComponent,
        string $instanceId,
        ?string $reason = null,
        ?string $appId = null
    ): PromiseInterface {
        $query = [];
        if ($reason !== null) {
            $query['reason'] = $reason;
        }
        if ($appId !== null) {
            $query['appID'] = $appId;
        }

        $options = $query === [] ? [] : ['query' => $query];
        $uri = sprintf(
            '/v1.0/workflows/%s/%s/terminate',
            rawurlencode($workflowComponent),
            rawurlencode($instanceId)
        );

        return $this->handlePromise(
            $this->httpClient->postAsync($uri, $options),
            static fn(ResponseInterface $response): null => null
        );
    }

    /**
     * Gets workflow instance metadata.
     *
     * The termination reason, when present, is exposed at
     * properties['dapr.workflow.termination_reason'].
     *
     * @return array<array-key, mixed>
     * @throws \Dapr\exceptions\DaprException
     */
    public function getWorkflow(
        string $workflowComponent,
        string $instanceId,
        ?string $appId = null
    ): array {
        return $this->getWorkflowAsync($workflowComponent, $instanceId, $appId)->wait();
    }

    /**
     * Gets workflow instance metadata asynchronously.
     *
     * @return PromiseInterface<array<array-key, mixed>>
     */
    public function getWorkflowAsync(
        string $workflowComponent,
        string $instanceId,
        ?string $appId = null
    ): PromiseInterface {
        $options = $appId === null ? [] : ['query' => ['appID' => $appId]];
        $uri = sprintf(
            '/v1.0/workflows/%s/%s',
            rawurlencode($workflowComponent),
            rawurlencode($instanceId)
        );

        return $this->handlePromise(
            $this->httpClient->getAsync($uri, $options),
            fn(ResponseInterface $response): array => $this->deserializer->from_json(
                'array',
                $response->getBody()->getContents()
            )
        );
    }
}
