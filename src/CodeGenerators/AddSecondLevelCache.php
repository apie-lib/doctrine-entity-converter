<?php
namespace Apie\DoctrineEntityConverter\CodeGenerators;

use Apie\StorageMetadataBuilder\Interfaces\PostRunGeneratedCodeContextInterface;
use Apie\StorageMetadataBuilder\Interfaces\RootObjectInterface;
use Apie\StorageMetadataBuilder\Mediators\GeneratedCodeContext;
use Doctrine\ORM\Mapping\Cache;
use Nette\PhpGenerator\ClassType;

class AddSecondLevelCache implements PostRunGeneratedCodeContextInterface
{
    public function postRun(GeneratedCodeContext $generatedCodeContext): void
    {
        $rootObjects = $generatedCodeContext->generatedCode->generatedCodeHashmap->getObjectsWithInterface(RootObjectInterface::class);
        /** @var ClassType $rootObject */
        foreach ($rootObjects as $rootObject) {
            $rootObject->addAttribute(Cache::class, ['usage' => 'NONSTRICT_READ_WRITE']);
        }
    }
}
