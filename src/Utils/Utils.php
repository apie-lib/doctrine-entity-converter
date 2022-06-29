<?php
namespace Apie\DoctrineEntityConverter\Utils;

use ReflectionProperty;

final class Utils
{
    private function __construct()
    {
    }

    public static function setProperty(mixed $instance, ReflectionProperty $property, mixed $value)
    {
        $property->setAccessible(true);
        $property->setValue($instance, $value);
    }

    public static function getProperty(mixed $instance, ReflectionProperty $property): mixed
    {
        $property->setAccessible(true);
        return $property->getValue($instance);
    }
}
