<?php

namespace Forter\Forter\Test\Unit\Helper;

use Forter\Forter\Helper\EntityHelper;
use Forter\Forter\Model\Entity;
use Forter\Forter\Model\EntityFactory;
use Forter\Forter\Model\ResourceModel\Entity\Collection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EntityHelperTest extends TestCase
{
    /**
     * @var Collection|MockObject
     */
    private $collection;

    /**
     * @var EntityHelper
     */
    private $helper;

    /**
     * @var array
     */
    private $orders = [];

    protected function setUp(): void
    {
        $this->orders = [];
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('addFieldToFilter')->willReturnSelf();
        $this->collection->method('setOrder')->willReturnCallback(
            function ($field, $direction) {
                $this->orders[] = [$field, $direction];
                return $this->collection;
            }
        );

        $entity = $this->createMock(Entity::class);
        $entity->method('getCollection')->willReturn($this->collection);

        $factory = $this->createMock(EntityFactory::class);
        $factory->method('create')->willReturn($entity);

        $this->helper = new EntityHelper(
            $this->createMock(Entity::class),
            $factory,
            $this->createMock(DateTime::class)
        );
    }

    public function testEmptyIncrementIdReturnsNull(): void
    {
        $this->collection->expects($this->never())->method('getFirstItem');

        $this->assertNull($this->helper->getForterEntityByIncrementId(''));
    }

    public function testLookupLoadsSingleRowWithoutCountQuery(): void
    {
        $found = $this->createMock(Entity::class);
        $found->method('getId')->willReturn('7');

        $this->collection->expects($this->never())->method('getSize');
        $this->collection->expects($this->once())->method('setPageSize')->with(1)->willReturnSelf();
        $this->collection->method('getFirstItem')->willReturn($found);

        $this->assertSame($found, $this->helper->getForterEntityByIncrementId('000000001'));
        $this->assertSame([['created_at', 'DESC'], ['entity_id', 'DESC']], $this->orders);
    }

    public function testLookupWithoutMatchReturnsNull(): void
    {
        $empty = $this->createMock(Entity::class);
        $empty->method('getId')->willReturn(null);

        $this->collection->method('setPageSize')->willReturnSelf();
        $this->collection->method('getFirstItem')->willReturn($empty);

        $this->assertNull($this->helper->getForterEntityByIncrementId('000000001'));
    }
}
