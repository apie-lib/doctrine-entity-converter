<?php
namespace Apie\Tests\DoctrineEntityConverter\Mediators;

use Apie\DoctrineEntityConverter\Mediators\GeneratedCode;
use Apie\Fixtures\Dto\EmptyDto;
use Doctrine\ORM\Mapping\Column;
use PHPUnit\Framework\TestCase;

class GeneratedCodeTest extends TestCase
{
    public function testCodeGeneration()
    {
        $testItem = new GeneratedCode('Generated\Example', 'Example', EmptyDto::class);
        $fixture = __DIR__ . '/../../fixtures/Example.phpinc';
         file_put_contents($fixture, $testItem->toCode());
        $this->assertEquals(file_get_contents($fixture), $testItem->toCode());

        $testItem->addInjectCode('$instance->test = "example";');

        $testItem->addInjectCode('$this->addCreateFromCode = "example";');

        $testItem->addProperty('string', 'example')
            ->addAttribute(Column::class, ['name' => 'Example']);

        $fixture = __DIR__ . '/../../fixtures/Example2.phpinc';
         file_put_contents($fixture, $testItem->toCode());
        $this->assertEquals(file_get_contents($fixture), $testItem->toCode());
    }
}
