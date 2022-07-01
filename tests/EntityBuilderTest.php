<?php
namespace Apie\Tests\DoctrineEntityConverter;

use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\Tests\DoctrineEntityConverter\Concerns\HasEntityBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EntityBuilderTest extends TestCase
{
    use HasEntityBuilder;

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
