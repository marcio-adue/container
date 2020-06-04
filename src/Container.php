<?php


namespace Adue\Container;


use ReflectionClass;
use ReflectionException as ReflectionException;

class Container
{

    protected $bindings = [];
    protected $shared = [];

    private static $instance;

    public static function getInstance()
    {
        if(static::$instance == null)
            static::$instance = new Container();

        return static::$instance;
    }

    public static function setInstance(Container $container)
    {
        static::$instance = $container;
    }

    public function bind($name, $resolver, $shared = false)
    {
        $this->bindings[$name] = [
            'resolver' => $resolver,
            'shared'   => $shared
        ];
    }

    public function instance($name, $object)
    {
        $this->shared[$name] = $object;
    }

    public function singleton($name, $resolver)
    {
        $this->bind($name, $resolver, true);
    }

    public function make($name, array $arguments = [])
    {
        if(isset($this->shared[$name]))
            return $this->shared[$name];

        if(isset($this->bindings[$name])) {
            $resolver = $this->bindings[$name]['resolver'];
            $shared = $this->bindings[$name]['shared'];
        } else {
            $resolver = $name;
            $shared = false;
        }

        if($resolver instanceof \Closure) {
            $object = $resolver($this);
        } else {
            $object = $this->build($resolver, $arguments);
        }

        if($shared) {
            $this->shared[$name] = $object;
        }

        return $object;
    }

    public function build($name, array $arguments = [])
    {
        try {
            $reflection = new ReflectionClass($name);
        } catch (ReflectionException $e) {
            throw new ContainerException("Class [$name] does not exists: " . $e->getMessage(), null, $e);
        }

        if(!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException("$name is not instantiable");
        }

        //ReflectionMethod
        $constructor = $reflection->getConstructor();

        if(is_null($constructor)) {
            return new $name;
        }

        //[ReflectionParameters]
        $constructorParameters = $constructor->getParameters();

        $dependencies = [];

        foreach ($constructorParameters as $constructorParameter) {

            $parameterName = $constructorParameter->getName();

            if(isset($arguments[$parameterName])) {
                $dependencies[] = $arguments[$parameterName];
                continue;
            }

            if($constructorParameter->isDefaultValueAvailable()) {
                $dependencies[] = $constructorParameter->getDefaultValue();
                continue;
            }

            try {
                $paramenterClass = $constructorParameter->getClass();
            } catch (ReflectionException $e) {
                throw new ContainerException("Unable to build [$name]: " . $e->getMessage(), null, $e);
            }

            if($paramenterClass != null) {
                $paramenterClassName = $paramenterClass->getName();
                $dependencies[] = $this->build($paramenterClassName);
            } else {

                throw new ContainerException("Please provide the value of the parameter [$parameterName]");
            }

        }

        return $reflection->newInstanceArgs($dependencies);
    }

}