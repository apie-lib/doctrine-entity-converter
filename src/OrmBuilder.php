<?php
namespace Apie\DoctrineEntityConverter;

use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\Persistence\PersistenceLayerFactory;
use Apie\Core\Persistence\PersistenceTableInterface;
use PhpParser\Error;
use PhpParser\ParserFactory;
use RuntimeException;

final class OrmBuilder
{
    public function __construct(
        private readonly EntityBuilder $entityBuilder,
        private readonly PersistenceLayerFactory $persistenceLayerFactory,
        private readonly BoundedContextHashmap $boundedContextHashmap,
        private readonly bool $validatePhpCode = true
    ) {
    }

    private function validate(string $phpCode, PersistenceTableInterface $table): void
    {
        $parser = (new ParserFactory)->create(ParserFactory::ONLY_PHP7);
        try {
            $parser->parse($phpCode);
        } catch (Error $error) {
            throw new RuntimeException(
                sprintf(
                    'I rendered an invalid PHP file for table %s. The error message is "%s". The generated code is ' . PHP_EOL . '%s',
                    $table->getName(),
                    $error->getMessage(),
                    $phpCode
                ),
                0,
                $error
            );
        }
    }

    public function createOrm(string $path): bool
    {
        $tableList = $this->persistenceLayerFactory->create($this->boundedContextHashmap);
        if (!is_dir($path)) {
            mkdir($path, recursive: true);
        }
        $modified = false;
        foreach ($tableList as $table) {
            $fileName = $path . DIRECTORY_SEPARATOR . $table->getName() . '.php';
            $phpCode = $this->entityBuilder->createCodeFor($table);
            if ($this->validatePhpCode) {
                $this->validate($phpCode, $table);
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
