<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\Fields\IndexTableReference;
use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\OneToMany;

class IndexTableReferencePropertyGenerator implements PropertyGeneratorInterface
{
    public function isSupported(PersistenceTableInterface $table, PersistenceFieldInterface $field): bool
    {
        return $field instanceof IndexTableReference;
    }
    public function apply(GeneratedCode $code, PersistenceTableInterface $table, PersistenceFieldInterface $field): void
    {
        assert($field instanceof IndexTableReference);
        $property = $code->addProperty(Collection::class, $field->getName());
        $property->addAttribute(
            OneToMany::class,
            [
                'cascade' => ['all'],
                'targetEntity' => $field->getTargetEntity(),
                'mappedBy' => 'entity',
            ]
        );
    }
}
