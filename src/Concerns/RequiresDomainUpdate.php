<?php
namespace Apie\DoctrineEntityConverter\Concerns;

use Apie\StorageMetadata\Attributes\GetMethodAttribute;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;

/**
 * @see RequiresRecalculatingInterface
 * @phpstan-ignore trait.unused
 */
trait RequiresDomainUpdate
{
    #[Column(name: 'requires_update', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[GetMethodAttribute('getDateToRecalculate')]
    public ?DateTimeImmutable $requiredUpdate = null;
}
