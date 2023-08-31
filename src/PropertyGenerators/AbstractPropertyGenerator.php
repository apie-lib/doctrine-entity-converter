<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Nette\PhpGenerator\Property;
use ReflectionProperty;

abstract class AbstractPropertyGenerator implements PropertyGeneratorInterface
{
    protected ?Property $lastGeneratedProperty = null;

    public function isSupported(PersistenceTableInterface $table, PersistenceFieldInterface $field): bool
    {
        if ($field->getDeclaredClass() === null) {
            return false;
        }
        if (is_callable([$field, 'getProperty'])) {
            $property = $field->getProperty();
            return $property instanceof ReflectionProperty && $this->supportsProperty($table, $field, $property);
        }

        return false;
    }

    abstract protected function supportsProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): bool;

    abstract protected function generateFromCodeConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string;

    abstract protected function getTypeForProperty(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string;

    /**
     * @return class-string<object>
     */
    abstract protected function getDoctrineAttribute(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function getDoctrineAttributeValue(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field
    ): array;

    abstract protected function generateInjectConversion(
        PersistenceTableInterface $table,
        PersistenceFieldInterface $field,
        ReflectionProperty $property
    ): string;

    protected function hasFilter(): bool
    {
        return false;
    }

    public function apply(GeneratedCode $code, PersistenceTableInterface $table, PersistenceFieldInterface $field): void
    {
        $fromCode = $this->generateFromCode($table, $field);
        $code->addCreateFromCode($fromCode);
        $inject = $this->generateInject($table, $field);
        $code->addInjectCode($inject);
        $type = $this->getTypeForProperty($table, $field);
        if (str_starts_with($type, 'apie_')) {
            $type = $code->getNamespace() . '\\' . $type;
        }
        assert(is_callable([$field, 'getProperty']));
        $property = $field->getProperty();
        if ($this->hasFilter()) {
            $code->addMapping($property->name, $field->getName());
        }
        $this->lastGeneratedProperty = $code->addProperty($type, $field->getName());
        $this->lastGeneratedProperty->addAttribute($this->getDoctrineAttribute($table, $field), $this->getDoctrineAttributeValue($table, $field));
    }

    protected function generateFromCode(PersistenceTableInterface $table, PersistenceFieldInterface $field): string
    {
        assert(is_callable([$field, 'getProperty']));
        $property = $field->getProperty();
        assert($property instanceof ReflectionProperty);
        $declaredClass = $property->getDeclaringClass()->name;
        assert(null !== $declaredClass);
        $declaringClass = 'OriginalDomainObject';
        if ($table->getOriginalClass() !== $declaredClass) {
            $declaringClass = '\\' . $declaredClass;
        }
        
        return sprintf(
            '$raw = Utils::getProperty($input, new \ReflectionProperty(%s::class, %s));
$converted = %s;
$instance->%s = $converted;',
            $declaringClass,
            var_export($property->name, true),
            $this->generateFromCodeConversion($table, $field, $property),
            $field->getName(),
        );
    }

    protected function generateInject(PersistenceTableInterface $table, PersistenceFieldInterface $field): string
    {
        assert(is_callable([$field, 'getProperty']));
        $property = $field->getProperty();
        assert($property instanceof ReflectionProperty);
        $declaredClass = $property->getDeclaringClass()->name;
        assert(null !== $declaredClass);
        $declaringClass = 'OriginalDomainObject';
        if ($table->getOriginalClass() !== $declaredClass) {
            $declaringClass = '\\' . $declaredClass;
        }
        return sprintf(
            '$tmp = $this->%s;
$converted = %s;
Utils::setProperty($instance, new \ReflectionProperty(%s::class, %s), $converted);',
            $field->getName(),
            $this->generateInjectConversion($table, $field, $property),
            $declaringClass,
            var_export($property->getName(), true),
        );
    }
}
