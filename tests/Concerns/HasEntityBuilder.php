<?php
namespace Apie\Tests\DoctrineEntityConverter\Concerns;

use Apie\DoctrineEntityConverter\EntityBuilder;
use Apie\DoctrineEntityConverter\PropertyGenerators\AutoincrementIntegerGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\MixedPropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\UuidGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\ValueObjectPropertyGenerator;

trait HasEntityBuilder
{
    protected function givenAEntityBuilder(?string $namespace = null): EntityBuilder
    {
        $namespace ??= 'Test\Example\E' . uniqid();
        return EntityBuilder::create($namespace);
    }
}
