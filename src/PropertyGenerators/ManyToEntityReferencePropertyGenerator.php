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
                'cascade' => ['all'],
                'targetEntity' => $field->getEntityReference(),
                'inversedBy' => '_indexTable',
            ]
        );
        if ($table instanceof EntityIndexMetadata) {
            $property = $code->addProperty('float', 'priority');
            $property->addAttribute(Column::class, ['type' => 'decimal', 'precision' => 2, 'scale' => 2]);
            $property = $code->addProperty('string', 'text');
            $property->addAttribute(Column::class, ['type' => 'text']);
        }
    }
}
