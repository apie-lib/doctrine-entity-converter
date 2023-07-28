<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Identifiers\AutoIncrementInteger;
use Apie\Core\Persistence\Fields\PropertySimpleValueObject;
use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\Core\Utils\ConverterUtils;
use Apie\Core\ValueObjects\Utils;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class ValueObjectPropertyGenerator extends AbstractPropertyGenerator
{
    protected function supportsProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): bool {
        return $field instanceof PropertySimpleValueObject;
    }

    protected function generateFromCodeConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        if ($field->isAllowsNull()) {
            return '$raw ? $raw->toNative() : null';
        }
        return '$raw->toNative()';
    }

    protected function getTypeForProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string {
        return ($field->isAllowsNull() ? '?' : '') . $field->getPersistenceType()->toType();
    }


    protected function getDoctrineAttribute(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string
    {
        return Column::class;
    }

    protected function getDoctrineAttributeValue(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): array
    {
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
        $class = ConverterUtils::toReflectionClass($field->getProperty()->getType());
        if ($class === null) {
            throw new LogicException('Field ' . $field->getName() . ' could not be cast to a class, type is ' . $field->getProperty()->getType());
        }
        if ($field->isAllowsNull() && $field->getProperty()->getType()->allowsNull()) {
            return '$tmp === null ? null : \\' . $class->name . '::fromNative($tmp)';
        }
        return '\\' . $class->name . '::fromNative($tmp)';
    }
}
