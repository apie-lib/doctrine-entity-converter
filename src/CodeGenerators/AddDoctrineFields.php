<?php
namespace Apie\DoctrineEntityConverter\CodeGenerators;

use Apie\Core\Context\ApieContext;
use Apie\Core\Identifiers\AutoIncrementInteger;
use Apie\Core\Identifiers\KebabCaseSlug;
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
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
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
        $doctrineType = null;
        $nullable = false;
        $generatedValue = false;
        if ($classType->hasProperty('id')) {
            $property = $classType->getProperty('id');
        } elseif ($classType->hasProperty('search_id')) {
            $property = $classType->getProperty('search_id')->cloneWithName('id');
            $classType->addMember($property);
        }
        if ($property === null) {
            $property = $classType->addProperty('id')->setType('?int');
            $generatedValue = true;
            $doctrineType = 'integer';
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
                    $type = $hashmap['id']->getTypehint();
                    $nullable = $hashmap['id']->allowsNull();
                    $class = ConverterUtils::toReflectionClass($type);
                    if ($class && $class->isSubclassOf(AutoIncrementInteger::class)) {
                        $generatedValue = true;
                        $nullable = false;
                        $property->setInitialized(true);
                    }
                    $scalarType = MetadataFactory::getScalarForType($hashmap['id']->getTypehint(), true);
                    $property->setType(
                        $scalarType->value
                    );
                    $doctrineType = $scalarType->toDoctrineType();
                }
            }
        }
        
        if (in_array(AutoIncrementTableInterface::class, $classType->getImplements())
            || in_array(MixedStorageInterface::class, $classType->getImplements())) {
            $generatedValue = true;
            $nullable = false;
        }

        $hasIdAttribute = false;
        $hasColumnAttribute = false;
        foreach ($property->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), [Column::class, ManyToOne::class, OneToMany::class, ManyToMany::class])) {
                $hasColumnAttribute = true;
                break;
            }
            if ($attribute->getName() === GeneratedValue::class) {
                $generatedValue = false;
            }
            if ($attribute->getName() === Id::class) {
                $hasIdAttribute = true;
            }
        }
        if (!$hasIdAttribute) {
            $property->addAttribute(Id::class);
        }
        if (!$hasColumnAttribute) {
            if ($doctrineType === null) {
                $doctrineType = MetadataFactory::getScalarForType(
                    ReflectionTypeFactory::createReflectionType($property->getType()),
                    true
                )->toDoctrineType();
            }
            $property->addAttribute(Column::class, ['type' => $doctrineType, 'nullable' => $nullable]);
        }
        if ($generatedValue) {
            $property->addAttribute(GeneratedValue::class);
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
                        $property->addAttribute(
                            JoinColumn::class,
                            [
                                'nullable' => true,
                            ]
                        );
                        break;
                    case OneToManyAttribute::class:
                        $added = true;
                        $property->setType(Collection::class);
                        $property->addAttribute(
                            OneToMany::class,
                            [
                                'cascade' => ['all'],
                                'targetEntity' => $attribute->getArguments()[1],
                                'mappedBy' => $attribute->getArguments()[0] ?? ('ref_' . $classType->getName()),
                            ]
                        );
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
                        $property->addAttribute(
                            ManyToOne::class,
                            ['targetEntity' => $property->getType()]
                        );
                        break;
                }
            }
            if (!$added) {
                $type = $property->getType();
                switch ((string) $type) {
                    case 'string':
                        $property->addAttribute(Column::class, ['type' => 'string', 'nullable' => $property->isNullable()]);
                        break;
                    case 'float':
                        $property->addAttribute(Column::class, ['type' => 'float', 'nullable' => $property->isNullable()]);
                        break;
                    case 'int':
                        $property->addAttribute(Column::class, ['type' => 'integer', 'nullable' => $property->isNullable()]);
                        break;
                    case '?int':
                        $property->addAttribute(Column::class, ['type' => 'integer', 'nullable' => $property->isNullable()]);
                        break;
                }
                
            }
        }

        $this->applyId($classType);
    }
}
