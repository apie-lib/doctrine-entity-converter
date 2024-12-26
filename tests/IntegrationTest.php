<?php
namespace Apie\Tests\DoctrineEntityConverter;

use Apie\Core\Entities\EntityInterface;
use Apie\Core\FileStorage\FileStorageFactory;
use Apie\Core\IdentifierUtils;
use Apie\Core\ValueObjects\DatabaseText;
use Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory;
use Apie\DoctrineEntityConverter\OrmBuilder;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\Fixtures\ValueObjects\AddressWithZipcodeCheck;
use Apie\StorageMetadata\DomainToStorageConverter;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class IntegrationTest extends TestCase
{
    protected function createEntityManager(string $mockPath, ?string $path = null): EntityManagerInterface
    {
        $isDevMode = true;
        $proxyDir = null;
        $cache = null;
        $config = ORMSetup::createAttributeMetadataConfiguration(
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
        $connection = DriverManager::getConnection($conn, $config);
        return new EntityManager($connection, $config);
    }

    protected function runMigrations(EntityManagerInterface $entityManager)
    {
        $tool = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $sql = $tool->getDropDatabaseSQL($classes);
        foreach ($sql as $statement) {
            $entityManager->getConnection()->executeStatement($statement);
        }
        $sql = $tool->getUpdateSchemaSql($classes);
        foreach ($sql as $statement) {
            $entityManager->getConnection()->executeStatement($statement);
        }
    }

    #[RequiresPhpExtension('sqlite3')]
    #[\PHPUnit\Framework\Attributes\DataProvider('entityProvider')]
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
            $converter = DomainToStorageConverter::create(FileStorageFactory::create());

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

    public static function entityProvider()
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
                TestCase::assertNull($domainObject->getId()->toNative(), 'Object id is not updated after flush(), before inject()');
            },
            function (UserWithAutoincrementKey $domainObject) {
                TestCase::assertNotNull($domainObject->getId()->toNative(), 'Object id is updated after inject()');
                TestCase::assertNull($domainObject->getPassword());
                TestCase::assertEquals('Street', $domainObject->getAddress()->getStreet()->toNative());
                TestCase::assertEquals('42-A', $domainObject->getAddress()->getStreetNumber()->toNative());
                TestCase::assertEquals('1234 AA', $domainObject->getAddress()->getZipcode()->toNative());
                TestCase::assertEquals('Amsterdam', $domainObject->getAddress()->getCity()->toNative());
            }
        ];
        $domainObject = new UserWithAddress($address);
        $id = $domainObject->getId()->toNative();
        yield 'Entity with predefined uuid' => [
            $domainObject,
            'default',
            null,
            function (UserWithAddress $persistedObject) use ($id) {
                TestCase::assertEquals($id, $persistedObject->getId()->toNative());
            }
        ];
    }
}
