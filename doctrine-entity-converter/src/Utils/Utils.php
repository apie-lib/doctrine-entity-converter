<?php
namespace Apie\DoctrineEntityConverter\Utils;

use Apie\Core\Identifiers\IdentifierInterface;
use Doctrine\ORM\Mapping\Id;
use Nette\PhpGenerator\Property;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

final class Utils
{
    private function __construct()
    {
    }

    public static function setProperty(mixed $instance, ReflectionProperty $property, mixed $value): void
    {
        $property->setAccessible(true);
        $property->setValue($instance, $value);
    }

    public static function getProperty(mixed $instance, ReflectionProperty $property): mixed
    {
        $property->setAccessible(true);
        return $property->getValue($instance);
    }

    public static function addIdAttributeIfApplicable(string $className, ReflectionType $type, Property $prop): void
    {
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if (is_a($typeName, IdentifierInterface::class, true) && $typeName::getReferenceFor()->name === $className) {
                $prop->addAttribute(Id::class);
            }
        }
    }
}
