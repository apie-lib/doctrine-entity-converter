<?php
namespace Apie\DoctrineEntityConverter\CodeGenerators;

use Apie\Core\Context\ApieContext;
use Apie\Core\Entities\RequiresRecalculatingInterface;
use Apie\Core\Identifiers\AutoIncrementInteger;
use Apie\Core\Metadata\MetadataFactory;
use Apie\Core\Utils\ConverterUtils;
use Apie\DoctrineEntityConverter\Concerns\HasGeneralDoctrineFields;
use Apie\DoctrineEntityConverter\Concerns\RequiresDomainUpdate;
use Apie\DoctrineEntityConverter\Entities\SearchIndex;
use Apie\StorageMetadata\Attributes\AclLinkAttribute;
use Apie\StorageMetadata\Attributes\DecimalPropertyAttribute;
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
use Doctrine\ORM\Mapping\OrderBy;
use Generator;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PromotedParameter;
use Nette\PhpGenerator\Property;
use ReflectionClass;
use ReflectionProperty;

/**
 * Adds created_at and updated_at and Doctrine attributes
 */
class AddDoctrineFields implements PostRunGeneratedCodeContextInterface
{
    public function postRun(GeneratedCodeContext $generatedCodeContext): void
    {
        foreach ($generatedCodeContext->generatedCode->generatedCodeHashmap as $code) {
            $this->patch($generatedCodeContext, $code);
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

    private function patch(GeneratedCodeContext $generatedCodeContext, ClassType $classType): void
    {
        $classType->addAttribute(Entity::class);
        $classType->addAttribute(HasLifecycleCallbacks::class);
        $classType->addTrait('\\' . HasGeneralDoctrineFields::class);

        // @see ClassTypeFactory
        $originalClass = $classType->getComment();
        if ($originalClass && class_exists($originalClass)) {
            if (is_a($originalClass, RequiresRecalculatingInterface::class, true)) {
                $classType->addTrait('\\' . RequiresDomainUpdate::class);
            }
        }

        foreach ($this->iterateProperties($classType) as $property) {
            $added = false;
            $attributes = [];
            foreach ($property->getAttributes() as $attribute) {
                switch ($attribute->getName()) {
                    case DecimalPropertyAttribute::class:
                        $added = true;
                        $arguments = $attribute->getArguments();
                        $attributes[] = new Attribute(Column::class, ['nullable' => true, 'type' => 'decimal', 'precision' => $arguments[2] ?? 2]);
                        break;
                    case GetMethodAttribute::class:
                    case PropertyAttribute::class:
                        $added = true;
                        if (in_array($property->getType(), ['DateTimeImmutable', '?DateTimeImmutable'])) {
                            $attributes[] = new Attribute(Column::class, ['nullable' => true, 'type' => 'datetimetz_immutable']);
                        } else {
                            $arguments = $attribute->getArguments();
                            if ($arguments[2] ?? false) {
                                $attributes[] = new Attribute(Column::class, ['nullable' => true, 'type' => 'text']);
                            } else {
                                $attributes[] = new Attribute(Column::class, ['nullable' => true]);
                            }
                        }
                        break;
                    case DiscriminatorMappingAttribute::class:
                        $added = true;
                        $attributes[] = new Attribute(Column::class, ['type' => 'json']);
                        break;
                    case ManyToOneAttribute::class:
                        $added = true;
                        $targetEntity = $property->getType();
                        $attributes[] = new Attribute(
                            ManyToOne::class,
                            [
                                'targetEntity' => $targetEntity,
                                'inversedBy' => $attribute->getArguments()[0],
                            ]
                        );
                        $attributes[] = new Attribute(
                            JoinColumn::class,
                            [
                                'nullable' => true,
                                'onDelete' => 'CASCADE',
                            ]
                        );
                        break;
                    case OneToManyAttribute::class:
                    case AclLinkAttribute::class:
                        $added = true;
                        $property->setType(Collection::class);
                        if ($attribute->getName() === OneToManyAttribute::class) {
                            $targetEntity = $attribute->getArguments()[1];
                            $mappedByProperty = $generatedCodeContext->findParentProperty($targetEntity);
                            $mappedByProperty ??= $attribute->getArguments()[0];
                            $mappedByProperty ??= 'ref_' . $classType->getName();
                        } else {
                            $targetEntity = $attribute->getArguments()[0];
                            $mappedByProperty = 'ref_' . $classType->getName();
                        }
                        $indexByProperty = $generatedCodeContext->findIndexProperty($targetEntity);
                        if ($indexByProperty) {
                            $attributes[] = new Attribute(OrderBy::class, [[$indexByProperty => 'ASC']]);
                        }
                        $attributes[] = new Attribute(
                            OneToMany::class,
                            [
                                'cascade' => ['all'],
                                'targetEntity' => $targetEntity,
                                'mappedBy' => $mappedByProperty,
                                'fetch' => 'EAGER',
                                'indexBy' => $indexByProperty,
                                'orphanRemoval' => true,
                            ]
                        );

                        break;
                    case OneToOneAttribute::class:
                        $added = true;
                        $targetEntity = $property->getType();
                        // look for @ParentAttribute for inversedBy?
                        $attributes[] = new Attribute(
                            OneToOne::class,
                            [
                                'cascade' => ['all'],
                                'targetEntity' => $targetEntity,
                                'fetch' => 'EAGER',
                                'orphanRemoval' => true,
                            ]
                        );
                        break;
                    case GetSearchIndexAttribute::class:
                        $added = true;
                        $property->setType(Collection::class);
                        $searchTableName = strpos($classType->getName(), 'apie_resource__') === 0
                            ? preg_replace('/^apie_resource__/', 'apie_index__', $classType->getName())
                            : 'apie_index__' . $classType->getName();
                        $searchTableName .= '_' . $property->getName();
                        $searchTable = SearchIndex::createFor(
                            $searchTableName,
                            $classType->getName(),
                            $property->getName(),
                        );
                        $generatedCodeContext->generatedCode->generatedCodeHashmap[$searchTableName] = $searchTable;
                        $attributes[] = new Attribute(
                            OneToMany::class,
                            [
                                'cascade' => ['all'],
                                'targetEntity' => $searchTableName,
                                'mappedBy' => 'parent',
                                'orphanRemoval' => true,
                            ]
                        );
                        $args = $attribute->getArguments();
                        $args['arrayValueType'] = $searchTableName;
                        $attribute = new Attribute($attribute->getName(), $args);
                        $type = $property->getType();
                        break;
                    case OrderAttribute::class:
                        $added = true;
                        $type = 'text';
                        if ($property->getType() === 'int') {
                            $type = 'integer';
                        }
                        $attributes[] = new Attribute(Column::class, ['type' => $type]);
                        break;
                    case ParentAttribute::class:
                        $added = true;
                        $inversedBy = $generatedCodeContext->findInverseProperty($property->getType(), $classType->getName());
                        $attributes[] = new Attribute(
                            ManyToOne::class,
                            ['targetEntity' => $property->getType(), 'inversedBy' => $inversedBy]
                        );
                        $attributes[] = new Attribute(
                            JoinColumn::class,
                            [
                                'onDelete' => 'CASCADE',
                            ]
                        );
                        break;
                }
                $attributes[] = $attribute;
            }
            if (!$added) {
                $type = $property->getType();
                switch ((string) $type) {
                    case 'string':
                        $attributes[] = new Attribute(Column::class, ['type' => 'text', 'nullable' => $property->isNullable()]);
                        break;
                    case 'float':
                        $attributes[] = new Attribute(Column::class, ['type' => 'float', 'nullable' => $property->isNullable()]);
                        break;
                    case 'int':
                    case '?int':
                        $attributes[] = new Attribute(Column::class, ['type' => 'integer', 'nullable' => $property->isNullable()]);
                        break;
                    case 'array':
                    case '?array':
                        $attributes[] = new Attribute(Column::class, ['type' => 'json', 'nullable' => $property->isNullable()]);
                        break;
                }
            }
            $property->setAttributes($attributes);
        }

        $this->applyId($classType);
    }
}
