<?php
namespace Apie\DoctrineEntityConverter;

use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
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

    /**
     * @param ReflectionClass<EntityInterface> $className
     */
    public function createCodeFor(ReflectionClass $className): string
    {
        $generatedCode = new GeneratedCode($this->namespace, $className->getShortName(), $className->getName());
        foreach ($className->getProperties() as $property) {
            foreach ($this->propertyGenerators as $propertyGenerator) {
                if ($propertyGenerator->isSupported($className, $property)) {
                    $propertyGenerator->apply($generatedCode, $className, $property);
                    break;
                }
            }
        }
        return $generatedCode->toCode();
    }
}
