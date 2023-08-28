<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\Fields\FieldReference;
use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Doctrine\ORM\Mapping\OneToOne;
use ReflectionProperty;

class FieldReferencePropertyGenerator extends AbstractPropertyGenerator
{
    protected function supportsProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): bool {
        return $field instanceof FieldReference;
    }

    protected function generateFromCodeConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        assert($field instanceof FieldReference);
        return sprintf(
            '%s::createFrom($raw)',
            $field->getTableReference()
        );
    }

    protected function getTypeForProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string {
        assert($field instanceof FieldReference);
        return $field->getTableReference();
    }


    protected function getDoctrineAttribute(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string {
        return OneToOne::class;
    }

    protected function getDoctrineAttributeValue(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): array {
        assert($field instanceof FieldReference);
        return [
            'targetEntity' => $field->getTableReference(),
            'cascade' => ['all'],
        ];
    }

    protected function generateInjectConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        assert($field instanceof FieldReference);
        $property = $field->getProperty();
        assert($property instanceof ReflectionProperty);
        $declaredClass = $property->getDeclaringClass()->name;
        assert(null !== $declaredClass);
        $declaringClass = 'OriginalDomainObject';
        if ($table->getOriginalClass() !== $declaredClass) {
            $declaringClass = '\\' . $declaredClass;
        }
        return sprintf(
            '$tmp; Utils::injectEmbeddedObject($this, new \ReflectionProperty(__CLASS__, %s), $instance, new \ReflectionProperty(%s::class, %s)); $converted = $this->%s',
            var_export($field->getName(), true),
            $declaringClass,
            var_export($property->name, true),
            $field->getName()
        );
    }
}
