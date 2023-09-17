<?php
namespace Apie\Tests\DoctrineEntityConverter\Embeddables;

use Apie\DoctrineEntityConverter\Embeddables\MixedType;
use Apie\Fixtures\ValueObjects\CompositeValueObjectExample;
use PHPUnit\Framework\TestCase;

class MixedTypeTest extends TestCase
{
    /**
     * @test
     * @dataProvider mixedProvider
     */
    public function it_can_store_and_restore_anything(mixed $input)
    {
        $object = MixedType::createFrom($input);
        $this->assertEquals($input, $object->toDomainObject());
    }

    public function mixedProvider(): iterable
    {
        yield 'null' => [null];
        yield 'string value' => ['string'];
        yield 'integer' => [42];
        yield 'floating point' => [1.5];
        yield 'array of random object' => [new CompositeValueObjectExample()];
    }
}
