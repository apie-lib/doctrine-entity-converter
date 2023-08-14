<?php
namespace Apie\DoctrineEntityConverter\TypeConverters;

use Apie\Core\ValueObjects\Interfaces\ValueObjectInterface;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\TypeConverter\ConverterInterface;
use Apie\TypeConverter\TypeConverter;
use ReflectionClass;
use ReflectionType;

/**
 * @implements ConverterInterface<GeneratedDoctrineEntityInterface, ValueObjectInterface>
 */
class EntityToValueObjectTypeConverter implements ConverterInterface
{
    public function convert(GeneratedDoctrineEntityInterface $entity, ReflectionType $wantedType, TypeConverter $typeConverter): ValueObjectInterface
    {
        $class = $typeConverter->convertTo($wantedType, ReflectionClass::class);
        $object = $class->newInstanceWithoutConstructor();
        $entity->inject($object);
        return $object;
    }
}
