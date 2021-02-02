<?php

namespace Dapr\Actors\Generators;

use DI\FactoryInterface;
use JetBrains\PhpStorm\Pure;
use Psr\Container\ContainerInterface;

class CachedGenerator extends ExistingOnly
{
    #[Pure] public function __construct(
        string $interface,
        string $dapr_type,
        FactoryInterface $factory,
        ContainerInterface $container
    ) {
        parent::__construct($interface, $dapr_type, $factory, $container);
    }

    public function get_proxy(string $id): object
    {
        if ( ! class_exists($this->get_full_class_name())) {
            $file_generator = $this->factory->make(
                FileGenerator::class,
                ['interface' => $this->interface, 'dapr_type' => $this->dapr_type]
            );
            $file           = $file_generator->generate_file();
            $dir            = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dapr-proxy-cache'.DIRECTORY_SEPARATOR;
            if ( ! is_dir($dir)) {
                mkdir($dir);
            }
            $filename = $dir.$this->get_short_class_name();
            file_put_contents($filename, $file);
            require_once $filename;
        }

        return parent::get_proxy($id);
    }
}
