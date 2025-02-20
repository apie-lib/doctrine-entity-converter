<?php
namespace Apie\DoctrineEntityConverter;

use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory;
use Apie\StorageMetadataBuilder\Lists\GeneratedCodeHashmap;
use Apie\StorageMetadataBuilder\Resources\GeneratedCodeTimestamp;
use Doctrine\Common\Collections\Collection;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use RuntimeException;
use Symfony\Component\Finder\Finder;

final class OrmBuilder
{
    private GeneratedCodeTimestamp $lastGeneratedCode;

    public function __construct(
        private readonly PersistenceLayerFactory $persistenceLayerFactory,
        private readonly BoundedContextHashmap $boundedContextHashmap,
        private readonly bool $validatePhpCode = true,
        private readonly string $namespace = 'Generated\\ApieEntities'
    ) {
    }

    private function validate(string $phpCode, string $tableName): void
    {
        $parser = (new ParserFactory)->createForVersion(PhpVersion::fromString('8.3'));
        try {
            $parser->parse($phpCode);
        } catch (Error $error) {
            throw new RuntimeException(
                sprintf(
                    'I rendered an invalid PHP file for table %s. The error message is "%s". The generated code is ' . PHP_EOL . '%s',
                    $tableName(),
                    $error->getMessage(),
                    $phpCode
                ),
                0,
                $error
            );
        }
    }

    private function wrapNamespace(ClassType $classType): string
    {
        $namespace = 'Generated\\ApieEntities' . $this->lastGeneratedCode->getId();
        $type = new PhpNamespace($namespace);
        $type->addUse('Apie\\StorageMetadata\\Attributes', 'Attr');
        $type->addUse('Apie\\StorageMetadata\\Interfaces', 'StorageMetadata');
        $type->addUse(EntityInterface::class);
        $type->addUse('Doctrine\\ORM\\Mapping', 'DoctrineMapping');
        $type->addUse(Collection::class);
        foreach ($classType->getProperties() as $property) {
            if ($property->getType() && str_starts_with($property->getType(), 'apie_')) {
                $property->setType($namespace . '\\' . $property->getType());
            }
        }
        foreach ($classType->getMethods() as $method) {
            if ($method->getReturnType() && str_starts_with($method->getReturnType(), 'apie_')) {
                $method->setReturnType($namespace . '\\' . $method->getReturnType());
            }
        }
        $type->add($classType);
        return '<?php' . PHP_EOL . '// @codingStandardsIgnoreStart' . PHP_EOL . $type;
    }

    private function createCurrentPathCode(string $path, GeneratedCodeHashmap $generatedCode): bool
    {
        $modified = false;
        foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
            $filePath = basename($file->getRelativePathname(), '.php');
            if (!isset($generatedCode[$filePath])) {
                @unlink($file->getRealPath());
                $modified = true;
            }
        }
        foreach ($generatedCode as $filePath => $code) {
            $fileName = $path . DIRECTORY_SEPARATOR . $filePath . '.php';
            $phpCode = $this->wrapNamespace($code);
            if ($this->validatePhpCode) {
                $this->validate($phpCode, $filePath);
            }
            $modified = $this->putFile($fileName, $phpCode) || $modified;
        }

        return $modified;
    }

    private function createAliasCode(string $className): string
    {
        $generatedNamespace = 'Generated\\ApieEntities' . $this->lastGeneratedCode->getId();
        $buildPath = '/../build' . $this->lastGeneratedCode->getId() . '/' . $className . '.php';
        return '<?php

        // This file has been auto-generated by Apie for internal use.
        
        if (!\class_exists(\\' . $generatedNamespace . '\\' . $className . '::class, false)) {
            include_once(__DIR__. ' . var_export($buildPath, true) . ');
        }
        
        
        if (!\class_exists(\\' . $this->namespace . '\\' . $className . '::class, false)) {
            \class_alias(\\' . $generatedNamespace . '\\' . $className . '::class, \\' . $this->namespace . '\\' . $className . '::class, false);
        }';
    }

    private function createReferencedCode(string $currentPath, GeneratedCodeHashmap $hashmap): bool
    {
        $generatedCode = [];
        foreach ($hashmap as $filePath => $code) {
            $fullPath = $currentPath . '/' . $filePath . '.php';
            $generatedCode[$fullPath] = $this->createAliasCode($filePath);
        }

        $modified = false;
        foreach (Finder::create()->files()->in($currentPath)->name('*.php') as $file) {
            $filePath = (string) $file;
            if (!isset($generatedCode[$filePath])) {
                @unlink($file->getRealPath());
                $modified = true;
            }
        }
        foreach ($generatedCode as $filePath => $phpCode) {
            if ($this->validatePhpCode) {
                $this->validate($phpCode, $filePath);
            }
            $modified = $this->putFile($filePath, $phpCode) || $modified;
        }

        return $modified;
    }

    public function createOrm(string $path): bool
    {
        $tableList = $this->persistenceLayerFactory->create($this->boundedContextHashmap);
        $this->lastGeneratedCode = new GeneratedCodeTimestamp($tableList->generatedCodeHashmap);
        $currentPath = $path . '/current';
        $buildPath = $path . '/build' . $this->lastGeneratedCode->getId();
        if (!is_dir($currentPath)) {
            @mkdir($currentPath, recursive: true);
        }
        if (!is_dir($buildPath)) {
            @mkdir($buildPath, recursive: true);
        }
        $modified = $this->createCurrentPathCode($buildPath, $tableList->generatedCodeHashmap);
        $modified = $this->createReferencedCode($currentPath, $tableList->generatedCodeHashmap) || $modified;
        if ($modified) {
            file_put_contents($path . '/apie.meta', serialize($this->lastGeneratedCode));
        }
        return $modified;
    }

    public function getLastGeneratedCode(string $path): GeneratedCodeTimestamp
    {
        if (!isset($this->lastGeneratedCode)) {
            $this->lastGeneratedCode = new GeneratedCodeTimestamp(new GeneratedCodeHashmap());
            $lastContents = @file_get_contents($path . '/apie.meta');
            if ($lastContents) {
                $lastGeneratedCode = @unserialize($lastContents);
                if ($lastGeneratedCode instanceof GeneratedCodeTimestamp) {
                    $this->lastGeneratedCode = $lastGeneratedCode;
                }                
            }
        }
        return $this->lastGeneratedCode;
    }

    private function putFile(string $fileName, string $phpCode): bool
    {
        if (is_readable($fileName) && file_get_contents($fileName) === $phpCode) {
            // this keeps the current modification date active
            return false;
        }
        if (false === @file_put_contents($fileName, $phpCode)) {
            throw new RuntimeException(sprintf('Could not write file "%s"', $fileName));
        }

        return true;
    }
}
