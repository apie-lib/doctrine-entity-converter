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
    
    #[Column(name: 'created_at', type: 'decimal', precision: 21, scale: 3)]
    public string $createdAt;

    #[Column(name: 'updated_at', type: 'decimal', precision: 21, scale: 3)]
    public string $updatedAt;

    #[PrePersist]
    public function onPrePersist(): void
    {
        // time is stored as decimal because not all datetime formats support microseconds on db platforms.
        $this->createdAt = ApieLib::getPsrClock()->now()->format('U.v');
        $this->updatedAt = $this->createdAt;
    }

    #[PreUpdate]
    public function onPreUpdate(): void
    {
        // time is stored as decimal because not all datetime formats support microseconds on db platforms.
        $this->updatedAt = ApieLib::getPsrClock()->now()->format('U.v');
        $this->lastUpdateApieVersion = ApieLib::VERSION;
    }
}
