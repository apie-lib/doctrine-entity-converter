<?php
namespace Apie\DoctrineEntityConverter\PropertyGenerators;

use Apie\Core\Identifiers\IdentifierInterface;
use Apie\DoctrineEntityConverter\Embeddables\MixedType;
use Apie\DoctrineEntityConverter\Interfaces\PropertyGeneratorInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\Id;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class MixedPropertyGenerator implements PropertyGeneratorInterface
{
    public function isSupported(ReflectionClass $class, ReflectionProperty $property): bool
    {
        return true;
    }

    public function apply(GeneratedCode $code, ReflectionClass $class, ReflectionProperty $property): void
    {
        $code->addUse(MixedType::class);
        $fromCode = $this->generateFromCode($class, $property);
        $code->addCreateFromCode($fromCode);
        $inject = $this->generateInject($class, $property);
        $code->addInjectCode($inject);
        $prop = $code->addProperty(MixedType::class, $property->name);
        $prop->addAttribute(Embedded::class, ['class' => MixedType::class]);
        $type = $property->getType();
        if (!$type->isBuiltin() && $type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            $typeReflClass = new ReflectionClass($typeName);
            if ($typeReflClass->implementsInterface(IdentifierInterface::class) && $typeName::getReferenceFor()->name === $class->name) {
                $prop->addAttribute(Id::class);
            }
        }
    }

    protected function generateFromCode(ReflectionClass $class, ReflectionProperty $property): string
    {
        $declaringClass = 'OriginalDomainObject';
        if ($property->getDeclaringClass()->name !== $class->name) {
            $declaringClass = '\\' . $property->getDeclaringClass()->name;
        }
        return sprintf(
            '$instance->%s = MixedType::createFrom(Utils::getProperty($input, new \ReflectionProperty(%s::class, %s)));',
            $property->name,
            $declaringClass,
            var_export($property->name, true)
        );
    }
    protected function generateInject(ReflectionClass $class, ReflectionProperty $property): string
    {
        $declaringClass = 'OriginalDomainObject';
        if ($property->getDeclaringClass()->name !== $class->name) {
            $declaringClass = '\\' . $property->getDeclaringClass()->name;
        }
        return sprintf(
            'Utils::setProperty($instance, new \ReflectionProperty(%s::class, %s), $this->%s->toDomainObject());',
            $declaringClass,
            var_export($property->name, true),
            $property->name
        );
    }
}
