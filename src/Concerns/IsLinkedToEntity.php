<?php
namespace Apie\DoctrineEntityConverter\Concerns;

use Apie\Core\Entities\EntityInterface;
use ReflectionClass;

trait IsLinkedToEntity
{
    public function newDomainClassInstance(): EntityInterface
    {
        $refl = new ReflectionClass($this->getOriginalClassName());
        return $refl->newInstanceWithoutConstructor();
    }
}
