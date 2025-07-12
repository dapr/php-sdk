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

    #[\Override]
    public function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $providedParameters,
        array $resolvedParameters
    ): array {
        $parameters = $reflection->getParameters();

        if ( ! empty($resolvedParameters)) {
            $parameters = array_diff_key($parameters, $resolvedParameters);
        }

        foreach ($parameters as $index => $parameter) {
            if ( ! empty($parameter->getAttributes(FromBody::class))) {
                $body = json_decode($this->request->getBody()->getContents(), true);

                $resolvedParameters[$index] = $this->deserializer->detect_from_parameter($parameter, $body);
            }
        }

        return $resolvedParameters;
    }
}
