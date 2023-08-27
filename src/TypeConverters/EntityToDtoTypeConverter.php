<?php
namespace Apie\DoctrineEntityConverter\TypeConverters;

use Apie\Core\Dto\DtoInterface;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\TypeConverter\ConverterInterface;
use Apie\TypeConverter\TypeConverter;
use ReflectionClass;
use ReflectionType;

/**
 * @implements ConverterInterface<GeneratedDoctrineEntityInterface, DtoInterface>
 */
class EntityToDtoTypeConverter implements ConverterInterface
{
    public function convert(GeneratedDoctrineEntityInterface $entity, ReflectionType $wantedType, TypeConverter $typeConverter): DtoInterface
    {
        $class = $typeConverter->convertTo($wantedType, ReflectionClass::class);
        $object = $class->newInstanceWithoutConstructor();
        $entity->inject($object);
        return $object;
    }
}
