<?php
namespace Apie\DoctrineEntityConverter\Factories;

use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\DoctrineEntityConverter\CodeGenerators\AddDoctrineFields;
use Apie\StorageMetadataBuilder\ChainedBootGeneratedCode;
use Apie\StorageMetadataBuilder\ChainedGeneratedCodeContext;
use Apie\StorageMetadataBuilder\ChainedPostGeneratedCodeContext;
use Apie\StorageMetadataBuilder\CodeGenerators\AddAutoIdGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\AddIndexesCodeGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\ItemListCodeGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\RootObjectCodeGenerator;
use Apie\StorageMetadataBuilder\CodeGenerators\SimplePropertiesCodeGenerator;
use Apie\StorageMetadataBuilder\Mediators\GeneratedCode;
use Apie\StorageMetadataBuilder\StorageMetadataBuilder;

final class PersistenceLayerFactory
{
    public function create(BoundedContextHashmap $boundedContextHashmap): GeneratedCode
    {
        $simple = new SimplePropertiesCodeGenerator();
        $indexer = new AddIndexesCodeGenerator();
        $testItem = new StorageMetadataBuilder(
            $boundedContextHashmap,
            new ChainedBootGeneratedCode(
                $simple,
                $indexer
            ),
            new ChainedGeneratedCodeContext(
                new AddAutoIdGenerator(),
                new ItemListCodeGenerator(),
                $simple,
                new RootObjectCodeGenerator()
            ),
            new ChainedPostGeneratedCodeContext(
                $indexer,
                new AddDoctrineFields(),
            )
        );
        return $testItem->generateCode();
    }
}
