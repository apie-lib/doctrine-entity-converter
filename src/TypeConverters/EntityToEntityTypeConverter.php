<?php
namespace Apie\DoctrineEntityConverter\TypeConverters;

use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\TypeConverter\ConverterInterface;
use Apie\TypeConverter\TypeConverter;
use ReflectionClass;
use ReflectionType;

/**
 * @implements ConverterInterface<GeneratedDoctrineEntityInterface, EntityInterface>
 */
class EntityToEntityTypeConverter implements ConverterInterface
{
    public function convert(GeneratedDoctrineEntityInterface $entity, ReflectionType $wantedType, TypeConverter $typeConverter): EntityInterface
    {
        $class = $typeConverter->convertTo($wantedType, ReflectionClass::class);
        $object = $class->newInstanceWithoutConstructor();
        $entity->inject($object);
        return $object;
    }
}
