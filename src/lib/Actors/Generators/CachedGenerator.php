<?php

namespace Dapr\Actors\Generators;

use DI\FactoryInterface;
use JetBrains\PhpStorm\Pure;
use Psr\Container\ContainerInterface;

/**
 * Class CachedGenerator
 *
 * Caches the generated file.
 *
 * @package Dapr\Actors\Generators
 */
class CachedGenerator extends ExistingOnly
{
    protected string $cache_dir;

    public function __construct(
        string $interface,
        string $dapr_type,
        FactoryInterface $factory,
        ContainerInterface $container
    ) {
        parent::__construct($interface, $dapr_type, $factory, $container);
        $this->cache_dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dapr-proxy-cache'.DIRECTORY_SEPARATOR;
    }

    /**
     * Set the cache directory
     *
     * @param string $dir
     */
    public function set_cache_dir(string $dir) {
        $this->cache_dir = $dir;
    }

    public function get_proxy(string $id): object
    {
        if ( ! class_exists($this->get_full_class_name())) {
            $file_generator = $this->factory->make(
                FileGenerator::class,
                ['interface' => $this->interface, 'dapr_type' => $this->dapr_type]
            );
            $file           = $file_generator->generate_file();
            if ( ! is_dir($this->cache_dir)) {
                mkdir($this->cache_dir);
            }
            $filename = $this->cache_dir.$this->get_short_class_name();
            file_put_contents($filename, $file);
            require_once $filename;
        }

        return parent::get_proxy($id);
    }
}
