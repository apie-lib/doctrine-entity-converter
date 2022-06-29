<?php
namespace Apie\DoctrineEntityConverter\Interfaces;

use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use ReflectionClass;
use ReflectionProperty;

interface PropertyGeneratorInterface
{
    public function isSupported(ReflectionClass $class, ReflectionProperty $property): bool;
    public function apply(GeneratedCode $code, ReflectionClass $class, ReflectionProperty $property): void;
}
