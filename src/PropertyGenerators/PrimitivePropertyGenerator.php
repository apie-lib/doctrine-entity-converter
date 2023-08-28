<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\Fields\PropertyEnum;
use Apie\Core\Persistence\Fields\PropertyPrimitive;
use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Doctrine\ORM\Mapping\Column;
use ReflectionProperty;

class PrimitivePropertyGenerator extends AbstractPropertyGenerator
{
    protected function supportsProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): bool {
        return $field instanceof PropertyPrimitive;
    }

    protected function generateFromCodeConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        return '$raw';
    }

    protected function getTypeForProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string {
        assert($field instanceof PropertyPrimitive);
        return (string) $field->getType();
    }


    protected function getDoctrineAttribute(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string {
        return Column::class;
    }

    protected function getDoctrineAttributeValue(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): array {
        assert($field instanceof PropertyEnum);
        return [
            'type' => $field->getPersistenceType()->toDoctrineType(),
            'nullable' => $field->isAllowsNull(),
        ];
    }

    protected function generateInjectConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        return '$tmp';
    }
}
