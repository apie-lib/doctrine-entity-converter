<?php
namespace Apie\DoctrineEntityConverter\Concerns;

use Apie\Core\ApieLib;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;

trait HasGeneralDoctrineFields
{
    #[Column(name: 'internal_apie_version', type: 'string', length: 20, options: ['default' => 'unknown'])]
    public string $internalApieVersion = ApieLib::VERSION;
    
    #[Column(name: 'created_at', type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updated_at', type: 'datetime_immutable')]
    public DateTimeImmutable $updatedAt;

    #[PrePersist]
    public function onPrePersist()
    {
        $this->createdAt = new DateTimeImmutable("now");
        $this->updatedAt = new DateTimeImmutable("now");
    }

    #[PreUpdate]
    public function onPreUpdate()
    {
        $this->updatedAt = new DateTimeImmutable("now");
    }
}
