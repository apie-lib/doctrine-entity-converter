<?php
namespace Apie\Tests\DoctrineEntityConverter;

use Apie\CommonValueObjects\Texts\DatabaseText;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\Exceptions\ContentsCouldNotBeDeserialized;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\Fixtures\ValueObjects\AddressWithZipcodeCheck;
use Apie\Tests\DoctrineEntityConverter\Concerns\HasEntityBuilder;
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
    public function testPersistenceAndRetrieval(EntityInterface $domainObject, ?callable $testBefore = null, ?callable $testAfter = null)
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
            $generatedEntityClassName = $namespace . '\\' . $reflClass->getShortName();
            $entityManager = $this->givenAnEntityManagerFromGeneratedClass(
                $namespace,
                $tempFolder,
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
        $tempFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('doctrine-');
        if (!@mkdir($tempFolder)) {
            $this->markTestSkipped('Can not create temp folder ' . $tempFolder);
        }

        try {
            $reflClass = new ReflectionClass(UserWithAutoincrementKey::class);
            $namespace = 'Generated\Example' . uniqid();
            $generatedEntityClassName = $namespace . '\\' . $reflClass->getShortName();
            $entityManager = $this->givenAnEntityManagerFromGeneratedClass(
                $namespace,
                $tempFolder,
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

    private function givenAnEntityManagerFromGeneratedClass(string $namespace, string $tempFolder, ReflectionClass $reflClass): EntityManagerInterface
    {
        $entityBuilder = $this->givenAEntityBuilder($namespace);

        $generatedFilePath = $tempFolder . DIRECTORY_SEPARATOR . $reflClass->getShortName() . '.php';
        $generatedEntityClassName = $namespace . '\\' . $reflClass->getShortName();
        file_put_contents(
            $generatedFilePath,
            $entityBuilder->createCodeFor($reflClass)
        );

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
        yield 'Entity with predefined uuid' => [
            new UserWithAddress($address),
        ];
    }
}
