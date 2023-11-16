<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\Fields\ManyToEntityReference;
use Apie\Core\Persistence\Metadata\EntityIndexMetadata;
use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToOne;

class ManyToEntityReferencePropertyGenerator implements PropertyGeneratorInterface
{
    public function isSupported(PersistenceTableInterface $table, PersistenceFieldInterface $field): bool
    {
        return $field instanceof ManyToEntityReference;
    }
    public function apply(GeneratedCode $code, PersistenceTableInterface $table, PersistenceFieldInterface $field): void
    {
        assert($field instanceof ManyToEntityReference);
        $property = $code->addProperty($field->getEntityReference(), $field->getName());
        $property->addAttribute(
            ManyToOne::class,
            [
                'targetEntity' => $field->getEntityReference(),
                'inversedBy' => '_indexTable',
            ]
        );
        // index tables have hardcoded properties
        if ($table instanceof EntityIndexMetadata) {
            $property = $code->addProperty('string', 'priority');
            $property->addAttribute(Column::class, ['type' => 'decimal', 'precision' => 2, 'scale' => 2]);
            $property = $code->addProperty('string', 'text');
            $property->addAttribute(Column::class, ['type' => 'text']);
            $property = $code->addProperty('float', 'tf')->setValue(0.0);
            $property->addAttribute(Column::class, ['type' => 'float', 'options' => ['default' => 0]]);
            $property = $code->addProperty('float', 'idf')->setValue(0.0);
            $property->addAttribute(Column::class, ['type' => 'float', 'options' => ['default' => 0]]);
        }
    }
}
