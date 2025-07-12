<?php

namespace Dapr\Actors\Generators;

use Dapr\Client\DaprClient;
use Dapr\State\FileWriter;
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
        DaprClient $client
    ) {
        parent::__construct($interface, $dapr_type, $client);
        $this->cache_dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dapr-proxy-cache'.DIRECTORY_SEPARATOR;
    }

    /**
     * Set the cache directory
     *
     * @param string $dir
     */
    public function set_cache_dir(string $dir): void {
        $this->cache_dir = $dir;
    }

    #[\Override]
    public function get_proxy(string $id): object
    {
        if ( ! class_exists($this->get_full_class_name())) {
            $file_generator = new FileGenerator($this->interface, $this->dapr_type, $this->client);
            $file           = $file_generator->generate_file();
            if ( ! is_dir($this->cache_dir)) {
                mkdir($this->cache_dir);
            }
            $filename = $this->cache_dir.$this->get_short_class_name();
            FileWriter::write($filename, $file);
            require_once $filename;
        }

        return parent::get_proxy($id);
    }
}
