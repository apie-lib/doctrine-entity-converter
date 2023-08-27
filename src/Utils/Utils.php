<?php
namespace Apie\DoctrineEntityConverter\Utils;

use Apie\Core\Utils\ConverterUtils;
use ReflectionProperty;

final class Utils
{
    private function __construct()
    {
    }

    public static function setProperty(mixed $instance, ReflectionProperty $property, mixed $value, bool $castType = true): void
    {
        $property->setAccessible(true);
        $type = $property->getType();
        if ($castType && $type) {
            $value = ConverterUtils::dynamicCast($value, $type);
        }
        $property->setValue($instance, $value);
    }

    public static function getProperty(mixed $instance, ReflectionProperty $property): mixed
    {
        $property->setAccessible(true);
        return $property->getValue($instance);
    }
}
