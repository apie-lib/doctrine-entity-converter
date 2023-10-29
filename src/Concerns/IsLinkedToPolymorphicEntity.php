<?php
namespace Apie\DoctrineEntityConverter\Concerns;

use Apie\Core\Entities\PolymorphicEntityInterface;
use Apie\Core\Utils\EntityUtils;
use Doctrine\ORM\Mapping\Column;
use ReflectionClass;

trait IsLinkedToPolymorphicEntity
{
    #[Column(name:"_apie_discriminator", type: 'json')]
    private array $discriminatorMapping = [];

    public function newDomainClassInstance(): PolymorphicEntityInterface
    {
        $class = EntityUtils::findClass($this->discriminatorMapping, new ReflectionClass($this->getOriginalClassName()));
        return $class->newInstanceWithoutConstructor();
    }
}
