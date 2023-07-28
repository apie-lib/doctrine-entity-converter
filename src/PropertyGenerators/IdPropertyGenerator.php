<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\Fields\EntityGetIdValue;
use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use ReflectionProperty;

final class IdPropertyGenerator implements PropertyGeneratorInterface
{
    public function isSupported(PersistenceTableInterface $table, PersistenceFieldInterface $field): bool
    {
        return $field instanceof EntityGetIdValue;
    }

    public function apply(GeneratedCode $code, PersistenceTableInterface $table, PersistenceFieldInterface $field): void
    {
        $fromCode = $this->generateFromCode($table, $field);
        $code->addCreateFromCode($fromCode);
        $prop = $code->addProperty(($field->isAllowsNull() ? '?' : '') . $field->getPersistenceType()->toType(), $field->getName());
        $prop->addAttribute(
            Column::class, 
            [
                'type' => $field->getPersistenceType()->toDoctrineType(),
                'nullable' => $field->isAllowsNull(),
            ]
        );
        $prop->addAttribute(Id::class);
        if ($field->isAllowsNull()) {
            $prop->addAttribute(GeneratedValue::class, ['strategy' => 'IDENTITY']);
        }
    }

    protected function generateFromCode(PersistenceTableInterface $table, PersistenceFieldInterface $field): string
    {
        if ($field->isAllowsNull()) {
            return sprintf(
                '$tmp = $input->getId();'
                . PHP_EOL
                . '$instance->%s = $tmp ? $tmp->toNative() : null;',
                $field->getName()
            );
        }

        return sprintf(
            '$instance->%s = $input->getId()->toNative();',
            $field->getName()
        );
    }
}