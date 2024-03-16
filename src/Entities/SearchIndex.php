<?php
namespace Apie\DoctrineEntityConverter\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\MappedSuperclass;
use JsonSerializable;
use Nette\PhpGenerator\ClassType;
use Stringable;

#[MappedSuperclass]
abstract class SearchIndex implements JsonSerializable, Stringable
{
    #[Column()]
    public string $value;

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    final public static function createFor(
        string $indexTableName,
        string $originalTableName,
        string $fieldName
    ): ClassType {
        $table = new ClassType($indexTableName);
        $table->addAttribute(Entity::class);
        $idProperty = $table->addProperty('id')->setPublic()->setType('int');
        $idProperty->addAttribute(
            Id::class
        );
        $idProperty->addAttribute(
            Column::class
        );
        $idProperty->addAttribute(
            GeneratedValue::class
        );
        $parentProperty = $table->addProperty('parent')->setPublic()->setType($originalTableName);
        $parentProperty->addAttribute(
            ManyToOne::class,
            [
                'targetEntity' => $originalTableName,
                'inversedBy' => $fieldName,
            ]
        );
        $table->setExtends(SearchIndex::class);
        $table->addAttribute(Index::class, ['columns' => ['parent_id', 'value']]);
        return $table;
    }
}