<?php
namespace Apie\DoctrineEntityConverter\CodeGenerators;

use Apie\Core\Context\ApieContext;
use Apie\Core\Identifiers\AutoIncrementInteger;
use Apie\Core\Metadata\MetadataFactory;
use Apie\Core\Utils\ConverterUtils;
use Apie\DoctrineEntityConverter\Concerns\HasGeneralDoctrineFields;
use Apie\StorageMetadata\Attributes\DiscriminatorMappingAttribute;
use Apie\StorageMetadata\Attributes\GetMethodAttribute;
use Apie\StorageMetadata\Attributes\GetSearchIndexAttribute;
use Apie\StorageMetadata\Attributes\ManyToOneAttribute;
use Apie\StorageMetadata\Attributes\OneToManyAttribute;
use Apie\StorageMetadata\Attributes\OneToOneAttribute;
use Apie\StorageMetadata\Attributes\OrderAttribute;
use Apie\StorageMetadata\Attributes\ParentAttribute;
use Apie\StorageMetadata\Attributes\PropertyAttribute;
use Apie\StorageMetadata\Interfaces\AutoIncrementTableInterface;
use Apie\StorageMetadataBuilder\Interfaces\MixedStorageInterface;
use Apie\StorageMetadataBuilder\Interfaces\PostRunGeneratedCodeContextInterface;
use Apie\StorageMetadataBuilder\Mediators\GeneratedCodeContext;
use Apie\TypeConverter\ReflectionTypeFactory;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToOne;
use Generator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PromotedParameter;
use Nette\PhpGenerator\Property;
use ReflectionClass;

/**
 * Adds created_at and updated_at and Doctrine attributes
 */
class AddDoctrineFields implements PostRunGeneratedCodeContextInterface
{
    public function postRun(GeneratedCodeContext $generatedCodeContext): void
    {
        foreach ($generatedCodeContext->generatedCode->generatedCodeHashmap as $code) {
            $this->patch($code);
        }
    }

    private function applyId(ClassType $classType): void
    {
        $property = null;
        if ($classType->hasProperty('id')) {
            $property = $classType->getProperty('id');
        } elseif ($classType->hasProperty('search_id')) {
            $property = $classType->getProperty('search_id')->cloneWithName('id');
            $classType->addMember($property);
        } else {
            // @see ClassTypeFactory
            $originalClass = $classType->getComment();
            if ($originalClass && class_exists($originalClass)) {
                $metadata = MetadataFactory::getResultMetadata(
                    new ReflectionClass($originalClass),
                    new ApieContext()
                );
                $hashmap = $metadata->getHashmap();
                if (isset($hashmap['id'])) {
                    $property = $classType->addProperty('id');
                    $scalarType = MetadataFactory::getScalarForType($hashmap['id']->getTypehint());
                    $property->setType(
                        $scalarType->value
                    );
                    $property->addAttribute(Column::class, ['type' => $scalarType->toDoctrineType()]);
                }
            }
        }
        if ($property === null) {
            $property = $classType->addProperty('id')->setType('?int');
        }
        $property->addAttribute(Id::class);
        if (in_array(AutoIncrementTableInterface::class, $classType->getImplements())
            || in_array(MixedStorageInterface::class, $classType->getImplements())) {
            $property->addAttribute(GeneratedValue::class);
        }
        // @see ClassTypeFactory
        $originalClass = $classType->getComment();
        if ($originalClass && class_exists($originalClass)) {
            $metadata = MetadataFactory::getResultMetadata(
                new ReflectionClass($originalClass),
                new ApieContext()
            );
            $hashmap = $metadata->getHashmap();
            if (isset($hashmap['id'])) {
                $type = $hashmap['id']->getTypehint();
                $class = ConverterUtils::toReflectionClass($type);
                if ($class && $class->isSubclassOf(AutoIncrementInteger::class)) {
                    $property->addAttribute(GeneratedValue::class);
                    $property->setInitialized(true);
                }
            }
        }
        if ($property->getType() === '?int') {
            $property->addAttribute(Column::class, ['type' => 'integer']);
        }
        $hasColumnAttribute = false;
        foreach ($property->getAttributes() as $attribute) {
            if ($attribute->getName() === Column::class) {
                $hasColumnAttribute = true;
                break;
            }
        }
        if (!$hasColumnAttribute) {
            $scalarType = MetadataFactory::getScalarForType(ReflectionTypeFactory::createReflectionType($property->getType()));
            $property->addAttribute(Column::class, ['type' => $scalarType->toDoctrineType()]);
        }
    }

    /**
     * @return Generator<int, PromotedParameter|Property>
     */
    private function iterateProperties(ClassType $classType): Generator
    {
        foreach ($classType->getProperties() as $property) {
            yield $property;
        }
        if ($classType->hasMethod('__construct')) {
            foreach ($classType->getMethod('__construct')->getParameters() as $parameter) {
                if ($parameter instanceof PromotedParameter) {
                    yield $parameter;
                }
            }
        }
    }

    private function patch(ClassType $classType): void
    {
        $classType->addAttribute(Entity::class);
        $classType->addAttribute(HasLifecycleCallbacks::class);
        $classType->addTrait('\\' . HasGeneralDoctrineFields::class);

        foreach ($this->iterateProperties($classType) as $property) {
            $added = false;
            foreach ($property->getAttributes() as $attribute) {
                switch ($attribute->getName()) {
                    case GetMethodAttribute::class:
                    case PropertyAttribute::class:
                        $added = true;
                        $property->addAttribute(Column::class, ['nullable' => true]);
                        break;
                    case DiscriminatorMappingAttribute::class:
                        $added = true;
                        $property->addAttribute(Column::class, ['type' => 'json']);
                        break;
                    case ManyToOneAttribute::class:
                        $added = true;
                        $targetEntity = $property->getType();
                        $property->addAttribute(
                            ManyToOne::class,
                            [
                                'targetEntity' => $targetEntity,
                                'inversedBy' => $attribute->getArguments()[0],
                            ]
                        );
                        break;
                    case OneToManyAttribute::class:
                        $added = true;
                        // TODO
                        break;
                    case OneToOneAttribute::class:
                        $added = true;
                        $targetEntity = $property->getType();
                        // look for @ParentAttribute for inversedBy?
                        $property->addAttribute(
                            OneToOne::class,
                            [
                                'cascade' => ['all'],
                                'targetEntity' => $targetEntity,
                            ]
                        );
                        break;
                    case GetSearchIndexAttribute::class:
                        $added = true;
                        // TODO
                        break;
                    case OrderAttribute::class:
                        $added = true;
                        $property->addAttribute(Column::class, ['type' => 'integer']);
                        break;
                    case ParentAttribute::class:
                        $added = true;
                        // TODO
                        break;
                }
            }
            if (!$added) {
                switch ($property->getType()) {
                    case '?string':
                        $property->addAttribute(Column::class, ['type' => 'string', 'nullable' => true]);
                        break;
                    case 'string':
                        $property->addAttribute(Column::class, ['type' => 'string']);
                        break;
                    case 'float':
                        $property->addAttribute(Column::class, ['type' => 'float']);
                        break;
                    case '?float':
                        $property->addAttribute(Column::class, ['type' => 'float', 'nullable' => true]);
                        break;
                    case 'int':
                        $property->addAttribute(Column::class, ['type' => 'integer']);
                        break;
                    case '?int':
                        $property->addAttribute(Column::class, ['type' => 'integer', 'nullable' => true]);
                        break;
                }
                
            }
        }

        $this->applyId($classType);
    }
}
