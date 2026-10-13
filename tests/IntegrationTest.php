<?php
namespace Apie\Tests\DoctrineEntityConverter;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\FileStorage\FileStorageFactory;
use Apie\Core\IdentifierUtils;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Core\ValueObjects\DatabaseText;
use Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory;
use Apie\DoctrineEntityConverter\OrmBuilder;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\Entities\Order;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\Fixtures\Identifiers\UserWithAddressIdentifier;
use Apie\Fixtures\ValueObjects\AddressWithZipcodeCheck;
use Apie\StorageMetadata\DomainToStorageConverter;
use Apie\Tests\DoctrineEntityConverter\Fixtures\ManyToManyTestEntity;
use Apie\Tests\DoctrineEntityConverter\Fixtures\ManyToManyTestEntityHashmap;
use Apie\Tests\DoctrineEntityConverter\Fixtures\ManyToManyTestEntityIdentifier;
use Apie\Tests\DoctrineEntityConverter\Fixtures\ManyToManyTestEntityList;
use Apie\Tests\DoctrineEntityConverter\Fixtures\ManyToManyTestOwner;
use Apie\Tests\DoctrineEntityConverter\Fixtures\ManyToManyTestOwnerIdentifier;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class IntegrationTest extends TestCase
{
    private const ID = '449032c6-0510-4973-9ab7-3b5b45777504';

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

    #[\PHPUnit\Framework\Attributes\DataProvider('entityProvider')]
    #[RunInSeparateProcess]
    public function testPersistenceAndRetrieval(
        EntityInterface $domainObject,
        string $boundedContextId,
        ?callable $testBefore = null,
        ?callable $testAfter = null
    ) {
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

    #[RunInSeparateProcess]
    public function testEntityListsAndHashmapsUseOwnerLocalManyToManyRows(): void
    {
        $tempFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('doctrine-');
        if (!@mkdir($tempFolder)) {
            $this->markTestSkipped('Can not create temp folder ' . $tempFolder);
        }

        try {
            $domainClass = new ReflectionClass(ManyToManyTestOwner::class);
            $namespace = 'Generated\\ManyToManyTest';
            $generatedClassName = $namespace . '\\apie_resource__default_' . IdentifierUtils::classNameToUnderscore($domainClass);
            $contexts = new BoundedContextHashmap([
                'default' => new BoundedContext(
                    new BoundedContextId('default'),
                    new ReflectionClassList([
                        $domainClass,
                        new ReflectionClass(Order::class),
                    ]),
                    new ReflectionMethodList()
                ),
            ]);
            $entityManager = $this->givenAnEntityManagerFromGeneratedClass(
                $namespace,
                $tempFolder,
                'default',
                $domainClass,
                $contexts
            );
            $converter = DomainToStorageConverter::create(FileStorageFactory::create());
            $metadata = $entityManager->getClassMetadata($generatedClassName);
            $associations = array_filter(
                $metadata->associationMappings,
                static fn ($mapping): bool => $mapping instanceof \Doctrine\ORM\Mapping\ManyToManyOwningSideMapping
            );
            $this->assertCount(2, $associations);
            $orderMetadata = $entityManager->getClassMetadata($namespace . '\\apie_resource__default_order');
            $this->assertInstanceOf(
                \Doctrine\ORM\Mapping\OneToManyAssociationMapping::class,
                $orderMetadata->getAssociationMapping('apie_order_order_lines')
            );

            $entityId1 = ManyToManyTestEntityIdentifier::fromNative('550e8400-e29b-41d4-a716-446655440001');
            $entityId2 = ManyToManyTestEntityIdentifier::fromNative('550e8400-e29b-41d4-a716-446655440002');
            $owners = [
                new ManyToManyTestOwner(
                    ManyToManyTestOwnerIdentifier::fromNative('550e8400-e29b-41d4-a716-446655440010'),
                    new ManyToManyTestEntityList([
                        new ManyToManyTestEntity($entityId2, 'second'),
                        new ManyToManyTestEntity($entityId1, 'first'),
                    ]),
                    new ManyToManyTestEntityHashmap([
                        'featured' => new ManyToManyTestEntity($entityId1, 'first'),
                    ])
                ),
                new ManyToManyTestOwner(
                    ManyToManyTestOwnerIdentifier::fromNative('550e8400-e29b-41d4-a716-446655440020'),
                    new ManyToManyTestEntityList([
                        new ManyToManyTestEntity($entityId2, 'second'),
                        new ManyToManyTestEntity($entityId1, 'first'),
                    ]),
                    new ManyToManyTestEntityHashmap([
                        'featured' => new ManyToManyTestEntity($entityId1, 'first'),
                    ])
                ),
            ];
            foreach ($owners as $owner) {
                $entityManager->persist(
                    $converter->createStorageObject($owner, new ReflectionClass($generatedClassName))
                );
            }
            $entityManager->flush();
            $entityManager->clear();

            foreach ($owners as $owner) {
                $storageObject = $entityManager->find($generatedClassName, $owner->getId()->toNative());
                $loadedOwner = $converter->createDomainObject($storageObject);
                self::assertInstanceOf(ManyToManyTestOwner::class, $loadedOwner);
                self::assertSame(
                    [$entityId2->toNative(), $entityId1->toNative()],
                    array_map(
                        static fn (ManyToManyTestEntity $entity): string => $entity->getId()->toNative(),
                        $loadedOwner->getEntities()->toArray()
                    )
                );
                self::assertSame(['featured'], array_keys($loadedOwner->getEntitiesByKey()->toArray()));
            }

            foreach ($associations as $association) {
                $targetMetadata = $entityManager->getClassMetadata($association->targetEntity);
                $targetTable = $targetMetadata->getTableName();
                $expectedCount = str_contains($association->fieldName, 'entities_by_key') ? 2 : 4;
                $count = (int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM "' . $targetTable . '"');
                $this->assertSame(
                    $expectedCount,
                    $count,
                    $targetTable
                );
            }
        } finally {
            system('rm -rf ' . escapeshellarg($tempFolder));
        }
    }

    private function givenAnEntityManagerFromGeneratedClass(
        string $namespace,
        string $tempFolder,
        string $boundedContextId,
        ReflectionClass $reflClass,
        ?BoundedContextHashmap $boundedContextHashmap = null
    ): EntityManagerInterface {
        $generatedClassName = 'apie_resource__' . $boundedContextId . '_' . IdentifierUtils::classNameToUnderscore($reflClass);
        $generatedFilePath = $tempFolder . DIRECTORY_SEPARATOR . 'current' . DIRECTORY_SEPARATOR . $generatedClassName . '.php';
        $generatedEntityClassName = $namespace . '\\' . $generatedClassName;

        $ormBuilder = new OrmBuilder(
            new PersistenceLayerFactory(),
            $boundedContextHashmap ?? BoundedContextFactory::createHashmapWithMultipleContexts(),
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

    private static function assertDomainObjectIdIsNull(UserWithAutoincrementKey $domainObject)
    {
        TestCase::assertNull($domainObject->getId()->toNative(), 'Object id is not updated after flush(), before inject()');
    }

    private static function assertDomainIsProperlySaved(UserWithAutoincrementKey $domainObject)
    {
        TestCase::assertNotNull($domainObject->getId()->toNative(), 'Object id is updated after inject()');
        TestCase::assertNull($domainObject->getPassword());
        TestCase::assertEquals('Street', $domainObject->getAddress()->getStreet()->toNative());
        TestCase::assertEquals('42-A', $domainObject->getAddress()->getStreetNumber()->toNative());
        TestCase::assertEquals('1234 AA', $domainObject->getAddress()->getZipcode()->toNative());
        TestCase::assertEquals('Amsterdam', $domainObject->getAddress()->getCity()->toNative());
    }

    private static function assertDomainObjectWIthUuidHasSameId(UserWithAddress $persistedObject)
    {
        TestCase::assertEquals(self::ID, $persistedObject->getId()->toNative());
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
            [__CLASS__, 'assertDomainObjectIdIsNull'],
            [__CLASS__, 'assertDomainIsProperlySaved']
        ];
        $domainObject = new UserWithAddress($address, UserWithAddressIdentifier::fromNative(self::ID));
        yield 'Entity with predefined uuid' => [
            $domainObject,
            'default',
            null,
            [__CLASS__, 'assertDomainObjectWIthUuidHasSameId']
        ];
    }
}
