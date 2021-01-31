<?php

namespace Dapr\Deserialization;

use Dapr\Attributes\FromBody;
use Invoker\ParameterResolver\ParameterResolver;
use Psr\Http\Message\RequestInterface;
use ReflectionFunctionAbstract;

class InvokerParameterResolver implements ParameterResolver
{
    public function __construct(private IDeserializer $deserializer, private RequestInterface $request)
    {
    }

    public function getParameters(
        ReflectionFunctionAbstract $reflection,
        array $providedParameters,
        array $resolvedParameters
    ) {
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
