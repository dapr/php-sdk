<?php

namespace Dapr\Actors\Generators;

use DI\Container;

class CachedGenerator extends ExistingOnly
{
    public function __construct(string $interface, string $dapr_type, Container $container)
    {
        parent::__construct($interface, $dapr_type, $container);
    }

    public function get_proxy(string $id): object
    {
        if ( ! class_exists($this->get_full_class_name())) {
            $file_generator = $this->container->make(
                FileGenerator::class,
                ['interface' => $this->interface, 'dapr_type' => $this->dapr_type]
            );
            $file           = $file_generator->generate_file();
            $dir            = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dapr-proxy-cache'.DIRECTORY_SEPARATOR;
            $filename       = $dir.$this->get_short_class_name();
            file_put_contents($filename, $file);
            require_once $filename;
        }

        return parent::get_proxy($id);
    }
}
