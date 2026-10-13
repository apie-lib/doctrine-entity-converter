<?php
namespace Apie\Tests\DoctrineEntityConverter\Fixtures;

use Apie\Core\Lists\ItemList;

class ManyToManyTestEntityList extends ItemList
{
    public function offsetGet(mixed $offset): ManyToManyTestEntity
    {
        return parent::offsetGet($offset);
    }
}
