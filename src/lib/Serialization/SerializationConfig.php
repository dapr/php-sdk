<?php

namespace Dapr\Serialization;

use Dapr\Actors\ActorConfig;
use Dapr\Serialization\Serializers\DateInterval;
use Dapr\Serialization\Serializers\DateTime;
use Dapr\Serialization\Serializers\ISerialize;
use Dapr\State\StateItem;

class SerializationConfig
{
    /**
     * SerializationConfig constructor.
     *
     * @param ISerialize[] $serializers
     */
    public function __construct(protected array $serializers = [])
    {
        if (empty($this->serializers[\DateInterval::class])) {
            $this->add(\DateInterval::class, new DateInterval());
        }
        if (empty($this->serializers[\DateTime::class])) {
            $this->add(\DateTime::class, new DateTime());
        }
        if (empty($this->serializers[StateItem::class])) {
            $this->add(StateItem::class, new Serializers\StateItem());
        }
    }

    public function add(string $type, ISerialize $serializer)
    {
        $this->serializers[$type] = $serializer;
    }
}
