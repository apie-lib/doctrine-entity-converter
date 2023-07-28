<?php
namespace Apie\DoctrineEntityConverter\Interfaces;

use Apie\Core\Persistence\PersistenceFieldInterface;
use Apie\Core\Persistence\PersistenceTableInterface;
use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;

interface PropertyGeneratorInterface
{
    public function isSupported(PersistenceTableInterface $table, PersistenceFieldInterface $field): bool;
    public function apply(GeneratedCode $code, PersistenceTableInterface $table, PersistenceFieldInterface $field): void;
}
