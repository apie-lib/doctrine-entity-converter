<?php
namespace Apie\DoctrineEntityConverter\CodeGenerators;

use Apie\StorageMetadataBuilder\Interfaces\PostRunGeneratedCodeContextInterface;
use Apie\StorageMetadataBuilder\Mediators\GeneratedCodeContext;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\Table;
use Generator;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PromotedParameter;
use Nette\PhpGenerator\Property;
use ReflectionProperty;

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
            foreach ($classType->getAttributes() as $attribute) {
                if ($attribute->getName() === Table::class) {
                    $found = true;
                    $this->setNameArgument($attribute, $suggestedTableName);
                }
            }
            if (!$found) {
                $classType->addAttribute(Table::class, ['name' => $suggestedTableName]);
            }
        }
        $alreadyDefined = [];
        foreach ($this->iterateProperties($classType) as $property) {
            $alreadyDefined[$property->getName()] = true;
            if (strlen($property->getName()) < 57) {
                continue;
            }
            $suggestedName = substr($property->getName(), 0, 57);
            for ($i = 0; !empty($alreadyDefined[$suggestedName]); $i++) {
                $suggestedName = sprintf("%s%03u", substr($property->getName(), 0, 57), $i);
            }
            foreach ($property->getAttributes() as $attribute) {
                if (in_array($attribute->getName(), [Column::class, JoinColumn::class])) {
                    $this->setNameArgument($attribute, $suggestedName);
                }
            }
        }
    }

    private function setNameArgument(Attribute $attribute, string $suggestedName): void
    {
        $arguments = $attribute->getArguments();
        $arguments['name'] = $suggestedName;
        $refl = new ReflectionProperty(Attribute::class, 'args');
        $refl->setValue($attribute, $arguments);
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
