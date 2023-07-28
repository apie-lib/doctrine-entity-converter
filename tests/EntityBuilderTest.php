<?php
namespace Apie\Tests\DoctrineEntityConverter;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Persistence\Fields\EntityGetIdValue;
use Apie\Core\Persistence\Fields\FieldReference;
use Apie\Core\Persistence\Fields\PropertySimpleValueObject;
use Apie\Core\Persistence\Lists\PersistenceFieldList;
use Apie\Core\Persistence\Metadata\EntityMetadata;
use Apie\Core\Persistence\PersistenceMetadataFactory;
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
        $metadata = new EntityMetadata(
            new BoundedContextId('example'),
            UserWithAddress::class,
            new PersistenceFieldList(
                [
                    new EntityGetIdValue(UserWithAddress::class),
                    new FieldReference(
                        UserWithAddress::class,
                        'address',
                        new PropertySimpleValueObject(UserWithAddress::class, 'address'),
                        'apie_other_table'
                    ),
                    new PropertySimpleValueObject(UserWithAddress::class, 'id'),
                ]
            )
        );
        $code = $testItem->createCodeFor($metadata);
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
        $metadata = new EntityMetadata(
            new BoundedContextId('example'),
            UserWithAddress::class,
            new PersistenceFieldList(
                [
                    new EntityGetIdValue(UserWithAutoincrementKey::class),
                    new FieldReference(
                        UserWithAutoincrementKey::class,
                        'address',
                        new PropertySimpleValueObject(UserWithAutoincrementKey::class, 'address'),
                        'apie_other_table'
                    )
                ]
            )
        );
        $code = $testItem->createCodeFor($metadata);
        $fixtureFile = __DIR__ . '/../fixtures/UserWithAutoincrementKey.phpinc';
        file_put_contents($fixtureFile, $code);
        $this->assertEquals(file_get_contents($fixtureFile), $code);
    }
}
