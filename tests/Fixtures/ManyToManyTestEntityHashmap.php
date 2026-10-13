<?php
namespace Apie\Tests\DoctrineEntityConverter\Fixtures;

use Apie\Core\Lists\ItemHashmap;

class ManyToManyTestEntityHashmap extends ItemHashmap
{
    public function offsetGet(mixed $offset): ManyToManyTestEntity
    {
        return parent::offsetGet($offset);
    }
}
