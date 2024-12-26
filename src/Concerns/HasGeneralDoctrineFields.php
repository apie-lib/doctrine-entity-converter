<?php
namespace Apie\DoctrineEntityConverter\Concerns;

use Apie\Core\ApieLib;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;

trait HasGeneralDoctrineFields
{
    #[Column(name: 'created_in_internal_apie_version', type: 'string', length: 20, options: ['default' => 'unknown'])]
    public string $internalApieVersion = ApieLib::VERSION;

    #[Column(name: 'updated_in_internal_apie_version', type: 'string', length: 20, options: ['default' => 'unknown'])]
    public string $lastUpdateApieVersion = ApieLib::VERSION;
    
    #[Column(name: 'created_at', type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updated_at', type: 'datetime_immutable')]
    public DateTimeImmutable $updatedAt;

    #[PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = ApieLib::getPsrClock()->now();
        $this->updatedAt = $this->createdAt;
    }

    #[PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = ApieLib::getPsrClock()->now();
        $this->lastUpdateApieVersion = ApieLib::VERSION;
    }
}
