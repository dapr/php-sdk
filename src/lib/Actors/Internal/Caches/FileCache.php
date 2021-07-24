<?php

namespace Dapr\Actors\Internal\Caches;

use Dapr\Actors\ActorReference;
use Dapr\State\FileWriter;
use phpDocumentor\Reflection\File;

/**
 * Class FileCache
 * @package Dapr\Actors\Internal\Caches
 */
class FileCache extends MemoryCache implements CacheInterface
{
    private string $cache_file;

    /**
     * @inheritDoc
     */
    public function __construct(private ActorReference $reference, private string $state_name)
    {
        parent::__construct($this->reference, $this->state_name);
        $base_dir = self::get_base_path($this->reference->get_actor_type(), $this->reference->get_actor_id());
        if ( ! file_exists($base_dir)) {
            mkdir($base_dir, recursive: true);
        }
        $this->state_name = mb_ereg_replace('([^\w\s\d\-_~,;\[\]\(\).])', '', $this->state_name) . '';
        $this->state_name = mb_ereg_replace('([\.]{2,})', '', $this->state_name) . '';
        $this->cache_file = $base_dir.DIRECTORY_SEPARATOR.$this->state_name.'.actor';
        $this->unserialize_cache();
    }

    private static function get_base_path(string $dapr_type, string $actor_id): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.
               'actor-cache'.DIRECTORY_SEPARATOR.$dapr_type.DIRECTORY_SEPARATOR.$actor_id;
    }

    /**
     * @inheritDoc
     */
    private function unserialize_cache()
    {
        if (file_exists($this->cache_file)) {
            $this->data = unserialize(file_get_contents($this->cache_file));
        }
    }

    public static function clear_actor(string $dapr_type, string $actor_id): void
    {
        $path = self::get_base_path($dapr_type, $actor_id);
        if ( ! file_exists($path)) {
            return;
        }
        foreach (scandir($path) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            unlink($path.DIRECTORY_SEPARATOR.$file);
        }
        rmdir($path);
    }

    /**
     * @inheritDoc
     */
    public function flush_cache(): void
    {
        $this->serialize_cache();
    }

    /**
     * @inheritDoc
     */
    private function serialize_cache()
    {
        if ($this->data === []) {
            if (file_exists($this->cache_file)) {
                unlink($this->cache_file);
            }

            return;
        }

        FileWriter::write($this->cache_file, serialize($this->data));
    }
}
