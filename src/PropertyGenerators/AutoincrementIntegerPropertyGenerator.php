<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\Fields\AutoincrementInteger;
use Apie\Core\Persistence\Fields\EntityGetIdValue;
use Apie\Core\Persistence\Fields\IsPropertyField;
use Apie\Core\Persistence\Metadata\EntityAutoincrementMetadata;
use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\Core\Utils\ConverterUtils;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use ReflectionProperty;

/**
 * Generates the id column of a autoincrement table (a table with just an id to have a autoincrement integer)
 */
final class AutoincrementIntegerPropertyGenerator implements PropertyGeneratorInterface
{
    private const DUMMY = '// this code is never used, just generated' . PHP_EOL;

    public function isSupported(PersistenceTableInterface $table, PersistenceFieldInterface $field): bool
    {
        return $field instanceof AutoincrementInteger && $table instanceof EntityAutoincrementMetadata;
    }

    public function apply(GeneratedCode $code, PersistenceTableInterface $table, PersistenceFieldInterface $field): void
    {
        $code->addCreateFromCode(self::DUMMY);
        $code->addInjectCode(self::DUMMY);
        $prop = $code->addProperty(($field->isAllowsNull() ? '?' : '') . $field->getPersistenceType()->toType(), $field->getName());
        $prop->addAttribute(
            Column::class, 
            [
                'type' => $field->getPersistenceType()->toDoctrineType(),
                'nullable' => $field->isAllowsNull(),
            ]
        );
        $prop->setValue(null);
        $prop->addAttribute(Id::class);
        $prop->addAttribute(GeneratedValue::class, ['strategy' => 'IDENTITY']);
    }
}