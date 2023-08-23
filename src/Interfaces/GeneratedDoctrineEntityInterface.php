<?php
namespace Apie\DoctrineEntityConverter\Interfaces;

use Apie\Core\Entities\EntityInterface;

/**
 * Marker interface for generated doctrine entities.
 *
 * @method void inject(object $object)
 * @method self updateFrom(object $object)
 * @method static self createFrom(object $object)
 * @method static class-string<EntityInterface> getOriginalClassName()
 *
 */
interface GeneratedDoctrineEntityInterface
{
}
