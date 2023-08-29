<?php
namespace Apie\DoctrineEntityConverter;

use Apie\Core\Persistence\Fields\FieldInvariant;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\CountryAndPhoneNumber\PropertyGenerators\CountryAndPhoneNumberPropertyGenerator;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Apie\DoctrineEntityConverter\PropertyGenerators\AutoincrementIntegerPropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\AutoincrementIntegerReferenceGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\EnumPropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\FieldReferencePropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\IdPropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\IndexTableReferencePropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\ManyToEntityReferencePropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\MixedPropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\PrimitivePropertyGenerator;
use Apie\DoctrineEntityConverter\PropertyGenerators\ValueObjectPropertyGenerator;

class EntityBuilder
{
    /** @var array<int, PropertyGeneratorInterface> */
    private array $propertyGenerators;

    public function __construct(private string $namespace, PropertyGeneratorInterface... $propertyGenerators)
    {
        $this->propertyGenerators = $propertyGenerators;
    }

    public static function create(string $namespace): self
    {
        $additional = [];
        if (class_exists(CountryAndPhoneNumberPropertyGenerator::class)) {
            $additional[] = new CountryAndPhoneNumberPropertyGenerator();
        }
        $additional[] = new IndexTableReferencePropertyGenerator();
        $additional[] = new ManyToEntityReferencePropertyGenerator();
        $additional[] = new AutoincrementIntegerPropertyGenerator();
        $additional[] = new AutoincrementIntegerReferenceGenerator();
        $additional[] = new FieldReferencePropertyGenerator();
        $additional[] = new IdPropertyGenerator();
        $additional[] = new PrimitivePropertyGenerator();
        $additional[] = new ValueObjectPropertyGenerator();
        $additional[] = new EnumPropertyGenerator();
        $additional[] = new MixedPropertyGenerator();
        return new self(
            $namespace,
            ...$additional,
        );
    }

    public function createCodeFor(PersistenceTableInterface $table): string
    {
        $generatedCode = new GeneratedCode($this->namespace, $table->getName(), $table->getOriginalClass());
        foreach ($table->getFields() as $field) {
            $realField = ($field instanceof FieldInvariant) ? $field->getDecoratedField() : $field;
            $found = false;
            foreach ($this->propertyGenerators as $propertyGenerator) {
                if ($propertyGenerator->isSupported($table, $realField)) {
                    $found = true;
                    $comment = PHP_EOL . '// generated from ' . get_class($propertyGenerator) . PHP_EOL . '// field class ' . get_class($realField);
                    $generatedCode->addCreateFromCode($comment);
                    $generatedCode->addInjectCode($comment);
                    $propertyGenerator->apply($generatedCode, $table, $field);
                    break;
                }
            }
            if (!$found) {
                $comment = PHP_EOL . '// no property generator' . PHP_EOL . '// field class ' . get_class($realField);
                $generatedCode->addCreateFromCode($comment);
                $generatedCode->addInjectCode($comment);
            }
        }
        return $generatedCode->toCode();
    }
}
