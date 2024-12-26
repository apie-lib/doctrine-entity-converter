<?php
namespace Apie\DoctrineEntityConverter;

use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory;
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
        $type = new PhpNamespace($this->namespace);
        $type->addUse('Apie\\StorageMetadata\\Attributes', 'Attr');
        $type->addUse('Apie\\StorageMetadata\\Interfaces', 'StorageMetadata');
        $type->addUse(EntityInterface::class);
        $type->addUse('Doctrine\\ORM\\Mapping', 'DoctrineMapping');
        $type->addUse(Collection::class);
        foreach ($classType->getProperties() as $property) {
            if ($property->getType() && str_starts_with($property->getType(), 'apie_')) {
                $property->setType($this->namespace . '\\' . $property->getType());
            }
        }
        foreach ($classType->getMethods() as $method) {
            if ($method->getReturnType() && str_starts_with($method->getReturnType(), 'apie_')) {
                $method->setReturnType($this->namespace . '\\' . $method->getReturnType());
            }
        }
        $type->add($classType);
        return '<?php' . PHP_EOL . '// @codingStandardsIgnoreStart' . PHP_EOL . $type;
    }

    public function createOrm(string $path): bool
    {
        $tableList = $this->persistenceLayerFactory->create($this->boundedContextHashmap);
        if (!is_dir($path)) {
            @mkdir($path, recursive: true);
        }
        $modified = false;
        foreach (Finder::create()->files()->in($path)->name('*.php') as $file) {
            $filePath = basename($file->getRelativePathname(), '.php');
            if (!isset($tableList->generatedCodeHashmap[$filePath])) {
                @unlink($file->getRealPath());
                $modified = true;
            }
        }
        foreach ($tableList->generatedCodeHashmap as $filePath => $code) {
            $fileName = $path . DIRECTORY_SEPARATOR . $filePath . '.php';
            $phpCode = $this->wrapNamespace($code);
            if ($this->validatePhpCode) {
                $this->validate($phpCode, $filePath);
            }
            $modified = $this->putFile($fileName, $phpCode) || $modified;
        }

        return $modified;
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
