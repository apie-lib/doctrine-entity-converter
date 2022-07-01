<?php
namespace Apie\Tests\DoctrineEntityConverter\Concerns;

use Apie\DoctrineEntityConverter\EntityBuilder;
use Apie\DoctrineEntityConverter\PropertyGenerators\AutoincrementIntegerGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\MixedPropertyGenerator;

trait HasEntityBuilder
{
    protected function givenAEntityBuilder(?string $namespace = null): EntityBuilder
    {
        $namespace ??= 'Test\Example\E' . uniqid();
        return new EntityBuilder(
            $namespace,
            new AutoincrementIntegerGenerator(),
            new MixedPropertyGenerator()
        );
    }
}
