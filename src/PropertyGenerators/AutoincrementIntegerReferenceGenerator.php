<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\Fields\AutoincrementIntegerReference;
use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Doctrine\ORM\Mapping\ManyToOne;
use ReflectionProperty;

class AutoincrementIntegerReferenceGenerator extends AbstractPropertyGenerator
{
    protected function supportsProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): bool {
        return $field instanceof AutoincrementIntegerReference;
    }

    protected function generateFromCodeConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        assert($field instanceof AutoincrementIntegerReference);
        return 'new ' . $field->getTableReference() . '();$converted->id = $raw->toNative()';
    }

    protected function getTypeForProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string {
        assert($field instanceof AutoincrementIntegerReference);
        return $field->getTableReference();
    }


    protected function getDoctrineAttribute(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string
    {
        return ManyToOne::class;
    }

    protected function getDoctrineAttributeValue(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): array {
        assert($field instanceof AutoincrementIntegerReference);
        return [
            'targetEntity' => $field->getTableReference(),
            'fetch' => 'EAGER',
            'cascade' => ['all'],
        ];
    }

    protected function generateInjectConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        assert($field instanceof AutoincrementIntegerReference);
        if ($field->isAllowsNull()) {
            return '$tmp === null ? null : $tmp->id';
        }
        return '$tmp->id';
    }

}