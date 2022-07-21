<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Identifiers\Uuid;
use Apie\Core\ValueObjects\Utils;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Apie\DoctrineEntityConverter\Utils\Utils as UtilsUtils;
use Doctrine\ORM\Mapping\Column;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class UuidGenerator implements PropertyGeneratorInterface
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
            && ($type->getName() === Uuid::class || is_a($type->getName(), Uuid::class, true));
    }

    public function apply(GeneratedCode $code, ReflectionClass $class, ReflectionProperty $property): void
    {
        $fromCode = $this->generateFromCode($class, $property);
        $code->addCreateFromCode($fromCode);
        $inject = $this->generateInject($class, $property);
        $code->addInjectCode($inject);
        $prop = $code->addProperty('string', $property->name)
            ->addAttribute(Column::class, ['type' => 'string', 'length' => 36]);
        UtilsUtils::addIdAttributeIfApplicable($class->name, $property->getType(), $prop);
    }

    protected function generateFromCode(ReflectionClass $class, ReflectionProperty $property): string
    {
        $declaringClass = 'OriginalDomainObject';
        if ($property->getDeclaringClass()->name !== $class->name) {
            $declaringClass = '\\' . $property->getDeclaringClass()->name;
        }
        return sprintf(
            '$tmp = Utils::getProperty($input, new \ReflectionProperty(%s::class, %s));
$tmp = $tmp ? $tmp->toNative(): null;
$instance->%s = $tmp === null ? null : \%s::toString($tmp);',
            $declaringClass,
            var_export($property->name, true),
            $property->name,
            Utils::class
        );
    }
    protected function generateInject(ReflectionClass $class, ReflectionProperty $property): string
    {
        $declaringClass = 'OriginalDomainObject';
        if ($property->getDeclaringClass()->name !== $class->name) {
            $declaringClass = '\\' . $property->getDeclaringClass()->name;
        }
        $propertyClass = '\\' . $property->getType();
        return sprintf(
            'Utils::setProperty($instance, new \ReflectionProperty(%s::class, %s), %s::fromNative($this->%s));',
            $declaringClass,
            var_export($property->name, true),
            $propertyClass,
            $property->name
        );
    }
}
