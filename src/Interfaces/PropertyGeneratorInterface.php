<?php
namespace Apie\DoctrineEntityConverter\Interfaces;

use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use ReflectionClass;
use ReflectionProperty;

/**
 * @template T of object
 */
interface PropertyGeneratorInterface
{
    /**
     * @param ReflectionClass<object> $class
     */
    public function isSupported(ReflectionClass $class, ReflectionProperty $property): bool;
    /**
     * @param ReflectionClass<T> $class
     */
    public function apply(GeneratedCode $code, ReflectionClass $class, ReflectionProperty $property): void;
}
