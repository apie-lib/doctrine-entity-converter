<?php
namespace Apie\Tests\DoctrineEntityConverter;

use Apie\DoctrineEntityConverter\EntityBuilder;
use Apie\DoctrineEntityConverter\PropertyGenerators\AutoincrementIntegerGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\MixedPropertyGenerator;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EntityBuilderTest extends TestCase
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

    /**
     * @test
     */
    public function it_can_generate_a_doctrine_entity_class()
    {
        $testItem = $this->givenAEntityBuilder('Test\RenderOnly');
        $code = $testItem->createCodeFor(new ReflectionClass(UserWithAddress::class));
        $fixtureFile = __DIR__ . '/../fixtures/UserWithAddress.phpinc';
         file_put_contents($fixtureFile, $code);
        $this->assertEquals(file_get_contents($fixtureFile), $code);
    }

    /**
     * @test
     */
    public function it_can_generate_a_doctrine_entity_class_with_autoincrement()
    {
        $testItem = $this->givenAEntityBuilder('Test\RenderOnly');
        $code = $testItem->createCodeFor(new ReflectionClass(UserWithAutoincrementKey::class));
        $fixtureFile = __DIR__ . '/../fixtures/UserWithAutoincrementKey.phpinc';
         file_put_contents($fixtureFile, $code);
        $this->assertEquals(file_get_contents($fixtureFile), $code);
    }
}
