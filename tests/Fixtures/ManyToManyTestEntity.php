<?php
namespace Apie\Tests\DoctrineEntityConverter\Fixtures;

use Apie\Core\Entities\EntityInterface;

class ManyToManyTestEntity implements EntityInterface
{
    public function __construct(
        private ManyToManyTestEntityIdentifier $id,
        private string $name
    ) {
    }

    public function getId(): ManyToManyTestEntityIdentifier
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
