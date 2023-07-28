<?php
namespace Apie\Tests\DoctrineEntityConverter;

use Apie\Common\Wrappers\BoundedContextHashmapFactory;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\IdentifierUtils;
use Apie\Core\Persistence\PersistenceLayerFactory;
use Apie\Core\Persistence\PersistenceMetadataFactory;
use Apie\DoctrineEntityConverter\EntityBuilder;
use Apie\DoctrineEntityConverter\Exceptions\ContentsCouldNotBeDeserialized;
use Apie\DoctrineEntityConverter\OrmBuilder;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\Fixtures\ValueObjects\AddressWithZipcodeCheck;
use Apie\Tests\DoctrineEntityConverter\Concerns\HasEntityBuilder;
use Apie\TextValueObjects\DatabaseText;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class IntegrationTest extends TestCase
{
    use HasEntityBuilder;

    protected function createEntityManager(string $mockPath, ?string $path = null): EntityManagerInterface
    {
        $isDevMode = true;
        $proxyDir = null;
        $cache = null;
        $config = Setup::createAttributeMetadataConfiguration(
            [$mockPath],
            $isDevMode,
            $proxyDir,
            $cache
        );
        $conn = array(
            'driver' => 'pdo_sqlite',
            'memory' => is_null($path),
            'path'   => $path
        );

        return EntityManager::create($conn, $config);
    }

    protected function runMigrations(EntityManagerInterface $entityManager)
    {
        $tool = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $sql = $tool->getDropDatabaseSQL($classes);
        foreach ($sql as $statement) {
            $entityManager->getConnection()->exec($statement);
        }
        $sql = $tool->getUpdateSchemaSql($classes);
        foreach ($sql as $statement) {
            $entityManager->getConnection()->exec($statement);
        }
    }

    /**
     * @requires extension sqlite3
     * @dataProvider entityProvider
     */
    public function testPersistenceAndRetrieval(EntityInterface $domainObject, string $boundedContextId, ?callable $testBefore = null, ?callable $testAfter = null)
    {
        $testBefore ??= function () {
        };
        $testAfter ??= function () {
        };
        $tempFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('doctrine-');
        if (!@mkdir($tempFolder)) {
            $this->markTestSkipped('Can not create temp folder ' . $tempFolder);
        }

        try {
            $reflClass = new ReflectionClass($domainObject);
            $namespace = 'Generated\Example' . uniqid();
            $generatedClassName = 'apie_entity_' . $boundedContextId . '_' . IdentifierUtils::classNameToUnderscore($reflClass);
            $generatedEntityClassName = $namespace . '\\' . $generatedClassName;
            $entityManager = $this->givenAnEntityManagerFromGeneratedClass(
                $namespace,
                $tempFolder,
                $boundedContextId,
                $reflClass
            );

            $entity = $generatedEntityClassName::createFrom($domainObject);
            $entityManager->persist($entity);
            $entityManager->flush();
            $testBefore($domainObject);
            $entity->inject($domainObject);
            $testAfter($domainObject);
        } finally {
            system('rm -rf ' . escapeshellarg($tempFolder));
        }
    }

    public function testSerializationError()
    {
        $this->markTestIncomplete('should update address.sql');
        $tempFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('doctrine-');
        if (!@mkdir($tempFolder)) {
            $this->markTestSkipped('Can not create temp folder ' . $tempFolder);
        }

        try {
            $reflClass = new ReflectionClass(UserWithAutoincrementKey::class);
            $namespace = 'Generated\Example' . uniqid();
            $generatedClassName = 'apie_entity_other_' . IdentifierUtils::classNameToUnderscore($reflClass);
            $generatedEntityClassName = $namespace . '\\' . $generatedClassName;
            $entityManager = $this->givenAnEntityManagerFromGeneratedClass(
                $namespace,
                $tempFolder,
                'other',
                $reflClass
            );
            $entityManager->getConnection()->exec(file_get_contents(__DIR__ . '/../fixtures/address.sql'));
            $entity = $entityManager->find($generatedEntityClassName, 42);
            $this->expectException(ContentsCouldNotBeDeserialized::class);
            $entity->inject($reflClass->newInstanceWithoutConstructor());
        } finally {
            system('rm -rf ' . escapeshellarg($tempFolder));
        }
    }

    private function givenAnEntityManagerFromGeneratedClass(string $namespace, string $tempFolder, string $boundedContextId, ReflectionClass $reflClass): EntityManagerInterface
    {
        $generatedClassName = 'apie_entity_' . $boundedContextId . '_' . IdentifierUtils::classNameToUnderscore($reflClass);
        $generatedFilePath = $tempFolder . DIRECTORY_SEPARATOR . $generatedClassName . '.php';
        $generatedEntityClassName = $namespace . '\\' . $generatedClassName;

        $ormBuilder = new OrmBuilder(
            EntityBuilder::create($namespace),
            new PersistenceLayerFactory(
                PersistenceMetadataFactory::create()
            ),
            BoundedContextFactory::createHashmapWithMultipleContexts()
        );

        $ormBuilder->createOrm($tempFolder);

        $this->assertFalse(class_exists($generatedEntityClassName));
        require_once($generatedFilePath);
        $this->assertTrue(class_exists($generatedEntityClassName), 'requiring the generated file created the class');
        
        $entityManager = $this->createEntityManager($tempFolder);
        $this->runMigrations($entityManager);
        return $entityManager;
    }

    public function entityProvider()
    {
        $address = new AddressWithZipcodeCheck(
            new DatabaseText('Street'),
            new DatabaseText('42-A'),
            new DatabaseText('1234 AA'),
            new DatabaseText('Amsterdam')
        );
        yield 'Entity with auto increment id' => [
            new UserWithAutoincrementKey($address),
            'other',
            function (UserWithAutoincrementKey $domainObject) {
                $this->assertNull($domainObject->getId()->toNative(), 'Object id is not updated after flush(), before inject()');
            },
            function (UserWithAutoincrementKey $domainObject) {
                $this->assertNotNull($domainObject->getId()->toNative(), 'Object id is updated after inject()');
                $this->assertNull($domainObject->getPassword());
                $this->assertEquals('Street', $domainObject->getAddress()->getStreet()->toNative());
                $this->assertEquals('42-A', $domainObject->getAddress()->getStreetNumber()->toNative());
                $this->assertEquals('1234 AA', $domainObject->getAddress()->getZipcode()->toNative());
                $this->assertEquals('Amsterdam', $domainObject->getAddress()->getCity()->toNative());
            }
        ];
        $domainObject = new UserWithAddress($address);
        $id = $domainObject->getId()->toNative();
        yield 'Entity with predefined uuid' => [
            $domainObject,
            'default',
            null,
            function (UserWithAddress $persistedObject) use ($id) {
                $this->assertEquals($id, $persistedObject->getId()->toNative());
            }
        ];
    }
}
