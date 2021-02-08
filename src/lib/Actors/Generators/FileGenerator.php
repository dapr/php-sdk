<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\ActorTrait;
use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\IActor;
use Dapr\DaprClient;
use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
use JetBrains\PhpStorm\Pure;
use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Type;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;

class FileGenerator extends GenerateProxy
{
    #[Pure] public function __construct(
        protected string $interface,
        protected string $dapr_type,
        FactoryInterface $factory,
        ContainerInterface $container,
        private array $usings = []
    ) {
        parent::__construct($interface, $dapr_type, $factory, $container);
    }

    /**
     * Returns a string that can be saved as a file
     *
     * @param string $interface The interface to generate
     * @param string|null $override_type Allows overriding the dapr type
     *
     * @return PhpFile
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public static function generate(
        string $interface,
        FactoryInterface $factory,
        string|null $override_type = null
    ): PhpFile {
        $reflected_interface = new ReflectionClass($interface);
        $type                = $override_type ?? ($reflected_interface->getAttributes(
                    DaprType::class
                )[0] ?? null)?->newInstance()->type;

        if (empty($type)) {
            throw new LogicException("$interface must have a DaprType attribute");
        }

        $generator = $factory->make(FileGenerator::class, ['interface' => $interface, 'dapr_type' => $type]);

        return $generator->generate_file();
    }

    /**
     * @inheritDoc
     */
    public function get_proxy(string $id): IActor
    {
        if ( ! class_exists($this->get_full_class_name())) {
            foreach ($this->generate_file()->getNamespaces() as $namespace) {
                eval($namespace);
            }
        }
        $proxy = $this->factory->make($this->get_full_class_name());
        $proxy->id = $id;

        return $proxy;
    }

    public function generate_file(): PhpFile
    {
        // configure class
        $interface = ClassType::from($this->interface);
        $interface->addImplement($this->interface);
        $interface->addProperty('id')->setPublic()->setType(Type::STRING);
        $interface->setClass();
        $interface->setName($this->get_short_class_name());
        $interface->addTrait(ActorTrait::class);
        $interface->addProperty('DAPR_TYPE', $this->dapr_type);

        // maybe implement IActor
        $reflected_interface = new ReflectionClass($interface);
        if ( ! $reflected_interface->isSubclassOf(IActor::class)) {
            $interface->addImplement(IActor::class);
        }

        $methods = $this->get_methods($interface);
        foreach ($methods as $method) {
            if ($interface->hasMethod($method->getName())) {
                $interface->removeMethod($method);
            }
            $method = $this->generate_method($method, '');
            if ($method) {
                $interface->addMember($method);
            }
        }
        $interface->addMember($this->generate_constructor());

        // configure file
        $file = new PhpFile();
        $file->addComment('This file was automatically generated.');
        $namespace = $file->addNamespace($this->get_namespace());

        // configure namespace
        $namespace->add($interface);
        $namespace->addUse('\Dapr\Actors\IActor');
        $namespace->addUse('\Dapr\Actors\Attributes\DaprType');
        $namespace->addUse('\Dapr\Actors\ActorTrait');
        foreach ($this->usings as $using) {
            if (class_exists($using)) {
                $namespace->addUse($using);
            }
        }

        return $file;
    }

    protected function generate_constructor(): Method
    {
        $method = new Method('__construct');
        $method->addPromotedParameter('client')->setType(DaprClient::class)->setPrivate();
        $method->addPromotedParameter('serializer')->setType(ISerializer::class)->setPrivate();
        $method->addPromotedParameter('deserializer')->setType(IDeserializer::class)->setPrivate();
        $method->setPublic();
        $this->usings[] = DaprClient::class;
        $this->usings[] = ISerializer::class;
        $this->usings[] = IDeserializer::class;
        $method->setBody('');

        return $method;
    }

    /**
     * @inheritDoc
     */
    protected function generate_proxy_method(Method $method, string $id): Method
    {
        $params = array_values($method->getParameters());
        $method->setPublic();
        if ( ! empty($params)) {
            // @codeCoverageIgnoreStart
            if (isset($params[1])) {
                throw new LogicException(
                    "Cannot have more than one parameter on a method.\nMethod: {$method->getName()}"
                );
            }
            if ($params[0]->isReference()) {
                throw new LogicException(
                    "Cannot pass references between actors/methods.\nMethod: {$method->getName()}"
                );
            }
            // @codeCoverageIgnoreEnd
            $this->usings = array_merge($this->usings, self::get_types($params[0]->getType()));
            $method->addBody('$data = $?;', [array_values($method->getParameters())[0]->getName()]);
        }
        $method->addBody('$type = ?;', [$this->dapr_type]);
        $method->addBody('$id = $this->get_id();');
        $method->addBody('$current_method = ?;', [$method->getName()]);
        $method->addBody('$result = $this->client->post(');
        $method->addBody('  "/actors/$type/$id/method/$current_method",');
        if (empty($params)) {
            $method->addBody('  null);');
        } else {
            $method->addBody('  $this->serializer->as_array($data)');
            $method->addBody(');');
        }
        $return_type = $method->getReturnType() ?? Type::MIXED;
        if ($return_type !== Type::VOID) {
            $this->usings = array_merge($this->usings, self::get_types($return_type));
            $method->addBody(
                '$result->data = $this->deserializer->detect_from_method((new \ReflectionClass($this))->getMethod(?), $result->data);',
                [$method->getName()]
            );
            $method->addBody('return $result->data;');
        }

        return $method;
    }

    /**
     * Converts a type into a list of well-known types.
     *
     * @param string|null $type The type string
     *
     * @return array An array of types
     */
    #[Pure] private static function get_types(string|null $type): array
    {
        if ($type === null) {
            return [Type::VOID];
        }

        return explode('|', $type);
    }

    /**
     * @inheritDoc
     */
    protected function generate_failure_method(Method $method): Method
    {
        $method->addBody('throw new \LogicException("Cannot call ? outside the actor");', [$method->getName()]);
        $method->setPublic();

        return $method;
    }

    /**
     * @inheritDoc
     */
    protected function generate_get_id(Method $method, string $id): Method
    {
        $get_id = new Method('get_id');
        $get_id->setReturnType(Type::STRING);
        $get_id->setPublic();
        $get_id->addBody('return $this->id;');

        return $get_id;
    }
}
