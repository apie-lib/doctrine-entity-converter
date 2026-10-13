<?php
namespace Apie\Tests\DoctrineEntityConverter\Fixtures;

use Apie\Core\Entities\RootAggregate;

class ManyToManyTestOwner implements RootAggregate
{
    public function __construct(
        private ManyToManyTestOwnerIdentifier $id,
        private ManyToManyTestEntityList $entities,
        private ManyToManyTestEntityHashmap $entitiesByKey
    ) {
    }

    public function getId(): ManyToManyTestOwnerIdentifier
    {
        return $this->id;
    }

    public function getEntities(): ManyToManyTestEntityList
    {
        return $this->entities;
    }

    public function getEntitiesByKey(): ManyToManyTestEntityHashmap
    {
        return $this->entitiesByKey;
    }
}
