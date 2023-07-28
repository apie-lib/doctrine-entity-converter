<?php
namespace Apie\DoctrineEntityConverter;

use Apie\Core\Entities\EntityInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Apie\DoctrineEntityConverter\PropertyGenerators\AutoincrementIntegerPropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\AutoincrementIntegerReferenceGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\IdPropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\MixedPropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\ValueObjectPropertyGenerator;
use ReflectionClass;

class EntityBuilder
{
    /** @var array<int, PropertyGeneratorInterface<object>> */
    private array $propertyGenerators;

    /**
     * @param PropertyGeneratorInterface<object> $propertyGenerators
     */
    public function __construct(private string $namespace, PropertyGeneratorInterface... $propertyGenerators)
    {
        $this->propertyGenerators = $propertyGenerators;
    }

    public static function create(string $namespace): self
    {
        return new self(
            $namespace,
            new AutoincrementIntegerPropertyGenerator(),
            new AutoincrementIntegerReferenceGenerator(),
            new IdPropertyGenerator(),
            new ValueObjectPropertyGenerator(),
            new MixedPropertyGenerator(),
        );
    }

    public function createCodeFor(PersistenceTableInterface $table): string
    {
        $generatedCode = new GeneratedCode($this->namespace, $table->getName(), $table->getOriginalClass());
        foreach ($table->getFields() as $field) {
            foreach ($this->propertyGenerators as $propertyGenerator) {
                if ($propertyGenerator->isSupported($table, $field)) {
                    $propertyGenerator->apply($generatedCode, $table, $field);
                    break;
                }
            }
        }
        return $generatedCode->toCode();
    }
}
