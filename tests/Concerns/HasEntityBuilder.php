<?php
namespace Apie\Tests\DoctrineEntityConverter\Concerns;

use Apie\DoctrineEntityConverter\EntityBuilder;

trait HasEntityBuilder
{
    protected function givenAEntityBuilder(?string $namespace = null): EntityBuilder
    {
        $namespace ??= 'Test\Example\E' . uniqid();
        return EntityBuilder::create($namespace);
    }
}
