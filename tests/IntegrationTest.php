<?php
namespace Apie\Tests\DoctrineEntityConverter;

use Apie\Core\Entities\EntityInterface;
use Apie\Core\IdentifierUtils;
use Apie\Core\ValueObjects\DatabaseText;
use Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory;
use Apie\DoctrineEntityConverter\OrmBuilder;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\Fixtures\ValueObjects\AddressWithZipcodeCheck;
use Apie\StorageMetadata\DomainToStorageConverter;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class IntegrationTest extends TestCase
{
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
        $conn = [
            'driver' => 'pdo_sqlite',
            'memory' => is_null($path),
            'path'   => $path
        ];

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
            $generatedClassName = 'apie_resource__' . $boundedContextId . '_' . IdentifierUtils::classNameToUnderscore($reflClass);
            $generatedEntityClassName = $namespace . '\\' . $generatedClassName;
            $entityManager = $this->givenAnEntityManagerFromGeneratedClass(
                $namespace,
                $tempFolder,
                $boundedContextId,
                $reflClass
            );
            $converter = DomainToStorageConverter::create();

            $entity = $converter->createStorageObject(
                $domainObject,
                new ReflectionClass($generatedEntityClassName)
            );
            $entityManager->persist($entity);
            $entityManager->flush();
            $testBefore($domainObject);
            $converter->injectExistingDomainObject($domainObject, $entity);
            $testAfter($domainObject);
        } finally {
            system('rm -rf ' . escapeshellarg($tempFolder));
        }
    }

    private function givenAnEntityManagerFromGeneratedClass(string $namespace, string $tempFolder, string $boundedContextId, ReflectionClass $reflClass): EntityManagerInterface
    {
        $generatedClassName = 'apie_resource__' . $boundedContextId . '_' . IdentifierUtils::classNameToUnderscore($reflClass);
        $generatedFilePath = $tempFolder . DIRECTORY_SEPARATOR . $generatedClassName . '.php';
        $generatedEntityClassName = $namespace . '\\' . $generatedClassName;

        $ormBuilder = new OrmBuilder(
            new PersistenceLayerFactory(),
            BoundedContextFactory::createHashmapWithMultipleContexts(),
            true,
            $namespace
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
