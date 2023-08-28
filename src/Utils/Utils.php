<?php
namespace Apie\DoctrineEntityConverter\Utils;

use Apie\Core\Utils\ConverterUtils;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
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

    public static function getProperty(mixed $instance, ReflectionProperty $property, bool $strictCheck = true): mixed
    {
        $property->setAccessible(true);
        if ($property->isInitialized($instance) || $strictCheck) {
            return $property->getValue($instance);
        }
        if ($property->hasDefaultValue()) {
            return $property->getDefaultValue();
        }

        $type = $property->getType();
        $class = ConverterUtils::toReflectionClass($type);
        if ($class) {
            return $class->newInstanceWithoutConstructor();
        }

        return null;
    }

    public static function injectEmbeddedObject(
        GeneratedDoctrineEntityInterface $entity,
        ReflectionProperty $entityProperty,
        mixed $domainObject,
        ReflectionProperty $domainProperty
    ): void {
        $entityPropertyValue = self::getProperty($entity, $entityProperty, false);
        $domainPropertyValue = self::getProperty($domainObject, $domainProperty, false);
        if ($entityPropertyValue instanceof GeneratedDoctrineEntityInterface) {
            if (is_object($domainPropertyValue)) {
                $entityPropertyValue->inject($domainPropertyValue);
            } else {
                self::setProperty($entity, $entityProperty, $domainPropertyValue);
            }
        } else {
            if (is_object($domainPropertyValue)) {
                $class = ConverterUtils::toReflectionClass($entityProperty->getType())?->name;
                assert($class !== null);
                /** @var class-string<GeneratedDoctrineEntityInterface> $class */
                self::setProperty($entity, $entityProperty, $class::createFrom($domainPropertyValue));
            } else {
                self::setProperty($entity, $entityProperty, $domainPropertyValue);
            }
        }
    }
}
