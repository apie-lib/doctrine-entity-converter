<?php
namespace Apie\Tests\DoctrineEntityConverter\Concerns;

use Apie\Core\ApieLib;
use Apie\DoctrineEntityConverter\Concerns\HasGeneralDoctrineFields;
use Beste\Clock\FrozenClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class HasGeneralDoctrineFieldsTest extends TestCase
{
    use HasGeneralDoctrineFields;

    public function testOnPrePersist()
    {
        $time = new DateTimeImmutable('1970-01-01');
        ApieLib::setPsrClock(FrozenClock::at($time));
        $this->onPrePersist();
        $this->assertEquals($time, $this->createdAt);
        $this->assertEquals($time, $this->updatedAt);
    }

    public function testOnPreUpdate()
    {
        $this->createdAt = $this->updatedAt = new DateTimeImmutable('1970-01-01');
        $time = new DateTimeImmutable('1971-01-01');
        ApieLib::setPsrClock(FrozenClock::at($time));
        $this->onPreUpdate();
        $this->assertNotEquals($time, $this->createdAt);
        $this->assertEquals($time, $this->updatedAt);
    }
}
