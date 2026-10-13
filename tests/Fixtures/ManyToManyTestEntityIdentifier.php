<?php
namespace Apie\Tests\DoctrineEntityConverter\Fixtures;

use Apie\Core\Identifiers\IdentifierInterface;
use Apie\Core\Identifiers\UuidV4;
use ReflectionClass;

class ManyToManyTestEntityIdentifier extends UuidV4 implements IdentifierInterface
{
    public static function getReferenceFor(): ReflectionClass
    {
        return new ReflectionClass(ManyToManyTestEntity::class);
    }
}
