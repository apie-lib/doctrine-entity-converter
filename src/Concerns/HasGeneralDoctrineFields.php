<?php
namespace Apie\DoctrineEntityConverter\Concerns;

use Apie\Core\ApieLib;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;

trait HasGeneralDoctrineFields
{
    #[Column(type: 'string', length: 20, options: ['default' => 'unknown'])]
    public string $internalApieVersion = ApieLib::VERSION;
    /*
        #[Column(type: 'datetime_immutable')]
        public DateTimeImmutable $createdAt;

        #[Column(type: 'datetime_immutable')]
        public DateTimeImmutable $updatedAt;*/
}
