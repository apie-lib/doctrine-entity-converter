<?php
namespace Apie\DoctrineEntityConverter\Factories;

use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\DoctrineEntityConverter\CodeGenerators\AddDoctrineFields;
use Apie\DoctrineEntityConverter\CodeGenerators\AddSecondLevelCache;
use Apie\DoctrineEntityConverter\CodeGenerators\LimitFieldLength;
use Apie\StorageMetadataBuilder\ChainedBootGeneratedCode;
use Apie\StorageMetadataBuilder\ChainedGeneratedCodeContext;
use Apie\StorageMetadataBuilder\ChainedPostGeneratedCodeContext;
use Apie\StorageMetadataBuilder\CodeGenerators\AccessControlListGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\AddAutoIdGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\AddIndexesCodeGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\FileTableGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\ItemListCodeGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\RootObjectCodeGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\SimplePropertiesCodeGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\SubObjectCodeGenerator;
use Apie\StorageMetadataBuilder\Mediators\GeneratedCode;
use Apie\StorageMetadataBuilder\StorageMetadataBuilder;

final class PersistenceLayerFactory
{
    public function create(BoundedContextHashmap $boundedContextHashmap, bool $singleIndexTable = true): GeneratedCode
    {
        $simple = new SimplePropertiesCodeGenerator();
        $indexer = new AddIndexesCodeGenerator($singleIndexTable);
        $acl = new AccessControlListGenerator();
        $testItem = new StorageMetadataBuilder(
            $boundedContextHashmap,
            new ChainedBootGeneratedCode(
                $simple,
                $indexer,
                $acl,
            ),
            new ChainedGeneratedCodeContext(
                new AddAutoIdGenerator(),
                new FileTableGenerator(),
                new SubObjectCodeGenerator(),
                new ItemListCodeGenerator(),
                $simple,
                new RootObjectCodeGenerator()
            ),
            new ChainedPostGeneratedCodeContext(
                $indexer,
                $acl,
                new AddDoctrineFields(),
                new LimitFieldLength(),
                new AddSecondLevelCache(),
            )
        );
        return $testItem->generateCode();
    }
}
