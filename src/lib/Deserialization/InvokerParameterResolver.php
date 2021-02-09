<?php

namespace Dapr\Deserialization;

use Dapr\Attributes\FromBody;
use Invoker\ParameterResolver\ParameterResolver;
use Psr\Http\Message\RequestInterface;
use ReflectionFunctionAbstract;

/**
 * Class InvokerParameterResolver
 *
 * Injects FromBody attributes into parameters
 *
 * @package Dapr\Deserialization
 * @codeCoverageIgnore Via integration tests
 */
class InvokerParameterResolver implements ParameterResolver
{
    public function __construct(private IDeserializer $deserializer, private RequestInterface $request)
    {
    }

    public function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $provided_parameters,
        array $resolved_parameters
    ): array {
        $parameters = $reflection->getParameters();

        if ( ! empty($resolved_parameters)) {
            $parameters = array_diff_key($parameters, $resolved_parameters);
        }

        foreach ($parameters as $index => $parameter) {
            if ( ! empty($parameter->getAttributes(FromBody::class))) {
                $body = json_decode($this->request->getBody()->getContents(), true);

                $resolved_parameters[$index] = $this->deserializer->detect_from_parameter($parameter, $body);
            }
        }

        return $resolved_parameters;
    }
}
