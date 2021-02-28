<?php

namespace Dapr\Actors\Internal\Caches;

/**
 * Class FileCache
 * @package Dapr\Actors\Internal\Caches
 */
class FileCache implements CacheInterface
{
    private array $data = [];

    public function __construct(private string $cache_name)
    {
        if ( ! file_exists(sys_get_temp_dir().DIRECTORY_SEPARATOR.'actor-cache')) {
            mkdir(sys_get_temp_dir().DIRECTORY_SEPARATOR.'actor-cache');
        }
        $this->unserialize_cache();
    }

    private function unserialize_cache()
    {
        $filename = sys_get_temp_dir().DIRECTORY_SEPARATOR.'actor-cache'.DIRECTORY_SEPARATOR.$this->cache_name;
        if (file_exists($filename)) {
            $this->data = unserialize(file_get_contents($filename));
        }
    }

    public function get_key(string $key): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        throw new KeyNotFound();
    }

    public function set_key(string $key, mixed $data): void
    {
        $this->data[$key] = $data;
    }

    public function evict(string $key): void
    {
        unset($this->data[$key]);
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function __destruct()
    {
        $this->serialize_cache();
    }

    private function serialize_cache()
    {
        $filename = sys_get_temp_dir().DIRECTORY_SEPARATOR.'actor-cache'.DIRECTORY_SEPARATOR.$this->cache_name;
        if ($this->data === []) {
            if (file_exists($filename)) {
                unlink($filename);
            }

            return;
        }

        file_put_contents($filename, serialize($this->data));
    }
}
