<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Identifiers\AutoIncrementInteger;
use Apie\Core\ValueObjects\Utils;
use Apie\DoctrineEntityConverter\Embeddables\MixedType;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class AutoincrementIntegerGenerator implements PropertyGeneratorInterface
{
    public function isSupported(ReflectionClass $class, ReflectionProperty $property): bool
    {
        $type = $property->getType();
        if (!($type instanceof ReflectionNamedType)) {
            return false;
        }
        if ($type->isBuiltin()) {
            return false;
        }
        return class_exists($type->getName())
            && ($type->getName() === AutoIncrementInteger::class || is_a($type->getName(), AutoIncrementInteger::class, true));
    }

    public function apply(GeneratedCode $code, ReflectionClass $class, ReflectionProperty $property): void
    {
        $code->addUse(MixedType::class);
        $fromCode = $this->generateFromCode($class, $property);
        $code->addCreateFromCode($fromCode);
        $inject = $this->generateInject($class, $property);
        $code->addInjectCode($inject);
        $code->addProperty('?int', $property->name)
            ->addAttribute(Column::class, ['type' => 'integer', 'nullable' => true])
            ->addAttribute(GeneratedValue::class)
            ->addAttribute(Id::class);
    }

    protected function generateFromCode(ReflectionClass $class, ReflectionProperty $property): string
    {
        $declaringClass = 'OriginalDomainObject';
        if ($property->getDeclaringClass()->name !== $class->name) {
            $declaringClass = '\\' . $property->getDeclaringClass()->name;
        }
        return sprintf(
            '$tmp = Utils::getValue($input, new \ReflectionProperty(%s::class, %s));
$instance->%s = $tmp === null ? null : \%s::toInt($tmp);',
            $declaringClass,
            var_export($property->name, true),
            $property->name,
            Utils::class
        );
    }
    public function generateInject(ReflectionClass $class, ReflectionProperty $property): string
    {
        return sprintf(
            '$property->setAccessible(true);
$property->setValue($instance, $this->%s);',
            $property->name
        );
    }
}
