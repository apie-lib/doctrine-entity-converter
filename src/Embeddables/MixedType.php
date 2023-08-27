<?php
namespace Apie\DoctrineEntityConverter\Embeddables;

use Apie\DoctrineEntityConverter\Exceptions\ContentsCouldNotBeDeserialized;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;

/**
 * Maps any type to a doctrine column. To allow any type, we basically store the serialized php string.
 */
#[Embeddable]
class MixedType
{
    #[Column(type: 'text', nullable: true)]
    private ?string $serializedString = null;

    #[Column(type: 'string', nullable: true)]
    private ?string $originalClass = null;

    private function __construct()
    {
    }

    public static function createFrom(mixed $input): self
    {
        $instance = new self();
        $instance->serializedString = serialize($input);
        $instance->originalClass = get_debug_type($input);
        return $instance;
    }

    public function toDomainObject(): mixed
    {
        $result = unserialize($this->serializedString);
        if (get_debug_type($result) !== $this->originalClass) {
            throw new ContentsCouldNotBeDeserialized($result, $this->originalClass);
        }
        return $result;
    }
}
