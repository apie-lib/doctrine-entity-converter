<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\DoctrineEntityConverter\Embeddables\MixedType;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Doctrine\ORM\Mapping\Embedded;
use ReflectionProperty;

class MixedPropertyGenerator extends AbstractPropertyGenerator
{

    protected function supportsProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): bool {
        return true;
    }

    protected function generateFromCodeConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        return 'MixedType::createFrom($raw)';
    }

    protected function generateInjectConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string {
        return sprintf(
            '$this->%s->toDomainObject()',
            $field->getName()
        );
    }

    protected function getTypeForProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string {
        return ($field->isAllowsNull() ? '?' : '') . MixedType::class;
    }

    protected function getDoctrineAttribute(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string {
        return Embedded::class;
    }

    protected function getDoctrineAttributeValue(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): array {
        return ['class' => MixedType::class];
    }

    public function apply(GeneratedCode $code, PersistenceTableInterface $table, PersistenceFieldInterface $field): void
    {
        $code->addUse(MixedType::class);
        parent::apply($code, $table, $field);
    }
}
