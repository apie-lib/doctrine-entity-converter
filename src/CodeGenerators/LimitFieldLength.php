<?php
namespace Apie\DoctrineEntityConverter\CodeGenerators;

use Apie\StorageMetadataBuilder\Interfaces\PostRunGeneratedCodeContextInterface;
use Apie\StorageMetadataBuilder\Mediators\GeneratedCodeContext;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\Table;
use Generator;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PromotedParameter;
use Nette\PhpGenerator\Property;

/**
 * Find all doctrine column attributes set by AddDoctrineFields and checks if the generated column name
 * is not too long.
 *
 * Most database vendors have a 64 character limit, Postgres has a 63 character limit.
 * Foreign keys get a '_id' suffix, so any property name with more than 57 characters requires a different
 * column name.
 *
 * We want to rename the column to only 57 characters and add 3 digits after it if this column name is already defined.
 *
 * @see AddDoctrineFields
 */
class LimitFieldLength implements PostRunGeneratedCodeContextInterface
{
    public function postRun(GeneratedCodeContext $generatedCodeContext): void
    {
        foreach ($generatedCodeContext->generatedCode->generatedCodeHashmap as $code) {
            $this->patch($generatedCodeContext, $code);
        }
    }

    private function patch(GeneratedCodeContext $generatedCodeContext, ClassType $classType): void
    {
        if (strlen($classType->getName()) > 60) {
            $found = false;
            $suggestedTableName = substr($classType->getName(), 0, 30) . '_' . md5($classType->getName());
            $this->modifyAttributes(function (Attribute $attribute) use ($suggestedTableName, &$found) {
                if ($attribute->getName() === Table::class) {
                    $found = true;
                    return $this->setNameArgument($attribute, $suggestedTableName);
                }
                return $attribute;
            }, $classType);
            if (!$found) {
                $classType->addAttribute(Table::class, ['name' => $suggestedTableName]);
            }
        }
        $alreadyDefined = [];
        foreach ($this->iterateProperties($classType) as $property) {
            $this->limitJoinTable($classType, $property);
            $this->modifyAttributes(function (Attribute $attribute) {
                if (in_array($attribute->getName(), [Column::class])) {
                    return $this->fillInMissingStringLength($attribute);
                }
                return $attribute;
            }, $property);
            $propertyName = $property->getName();
            if (strlen($property->getName()) < 57 && !isset($alreadyDefined[$propertyName])) {
                $alreadyDefined[$propertyName] = true;
                continue;
            }
            $suggestedName = substr($propertyName, 0, 57);
            for ($i = 0; !empty($alreadyDefined[$suggestedName]); $i++) {
                $suggestedName = sprintf("%s%03u", substr($property->getName(), 0, 57), $i);
            }
            $this->modifyAttributes(function (Attribute $attribute) use ($suggestedName) {
                if (in_array($attribute->getName(), [Column::class, JoinColumn::class])) {
                    return $this->setNameArgument($attribute, $suggestedName);
                }
                return $attribute;
            }, $property);
            $alreadyDefined[$suggestedName] = true;
        }
    }

    /**
     * Doctrine generates the name of a join table of a ManyToMany as "<source table>_<target table>" and the
     * join columns as "<table>_id", so these can exceed the database limit while the tables themselves do not.
     */
    private function limitJoinTable(ClassType $classType, PromotedParameter|Property $property): void
    {
        $manyToMany = null;
        $joinTable = null;
        foreach ($property->getAttributes() as $attribute) {
            if ($attribute->getName() === ManyToMany::class) {
                $manyToMany = $attribute;
            }
            if ($attribute->getName() === JoinTable::class) {
                $joinTable = $attribute;
            }
        }
        $targetEntity = $manyToMany?->getArguments()['targetEntity'] ?? null;
        if (!is_string($targetEntity) || isset($joinTable?->getArguments()['name'])) {
            return;
        }
        $source = $classType->getName();
        $target = ltrim(strrchr('\\' . $targetEntity, '\\'), '\\');

        $arguments = $joinTable?->getArguments() ?? [];
        $tableName = strtolower($source . '_' . $target);
        if (strlen($tableName) > 60) {
            $arguments['name'] = substr($tableName, 0, 30) . '_' . md5($tableName);
        }
        // Doctrine uses source_id and target_id for self referencing relations, so we leave those alone.
        if ($source !== $target) {
            foreach (['joinColumns' => $source, 'inverseJoinColumns' => $target] as $key => $entityName) {
                $columnName = strtolower($entityName . '_id');
                if (!isset($arguments[$key]) && strlen($columnName) > 57) {
                    $shortName = substr($columnName, 0, 30) . '_' . md5($columnName);
                    $arguments[$key] = [new Literal(
                        'new \\' . JoinColumn::class . '(name: ?, referencedColumnName: \'id\')',
                        [$shortName]
                    )];
                }
            }
        }
        if (!$arguments) {
            return;
        }
        $attributes = [];
        foreach ($property->getAttributes() as $attribute) {
            if ($attribute !== $joinTable) {
                $attributes[] = $attribute;
            }
        }
        $attributes[] = new Attribute(JoinTable::class, $arguments);
        $property->setAttributes($attributes);
    }

    private function modifyAttributes(callable $callback, ClassType|PromotedParameter|Property $property): void
    {
        $attributes = [];
        foreach ($property->getAttributes() as $attribute) {
            $attributes[] = $callback($attribute);
        }
        $property->setAttributes($attributes);
    }

    private function fillInMissingStringLength(Attribute $attribute): Attribute
    {
        $arguments = $attribute->getArguments();
        if (($arguments['type'] ?? 'string')=== 'string' || !isset($arguments['type'])) {
            if (!isset($arguments['length'])) {
                $arguments['length'] = 255;
            }
        }
        return new Attribute($attribute->getName(), $arguments);
    }

    private function setNameArgument(Attribute $attribute, string $suggestedName): Attribute
    {
        $arguments = $attribute->getArguments();
        $arguments['name'] = $suggestedName;
        return new Attribute($attribute->getName(), $arguments);
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
}
