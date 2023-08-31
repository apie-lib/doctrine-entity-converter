<?php
namespace Apie\DoctrineEntityConverter\Interfaces;

use Apie\Core\Entities\EntityInterface;
use Doctrine\Common\Collections\Collection;

/**
 * Marker interface for generated doctrine entities.
 *
 * @method void inject(object $object)
 * @method self updateFrom(object $object)
 * @method static self createFrom(object $object)
 * @method static class-string<EntityInterface> getOriginalClassName()
 * @method static array<string, string> getMapping()
 * @property Collection<int, GeneratedDoctrineEntityInterface> $_indexTable
 */
interface GeneratedDoctrineEntityInterface
{
}
