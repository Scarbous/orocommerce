<?php

namespace OroB2B\Bundle\ProductBundle\Tests\Unit\Storage;

use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use OroB2B\Bundle\ProductBundle\Storage\AbstractSessionDataStorage;
use OroB2B\Bundle\ProductBundle\Tests\Unit\Storage\Stub\StubAbstractSessionDataStorage;

class AbstractSessionDataStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|SessionInterface
     */
    protected $session;

    /**
     * @var AbstractSessionDataStorage
     */
    protected $storage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|AttributeBagInterface
     */
    protected $sessionBag;

    protected function setUp()
    {
        $this->session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $this->sessionBag = $this->getMock('Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface');
        $this->session->expects($this->atMost(1))->method('getBag')->willReturn($this->sessionBag);

        $this->initStorage();
    }

    protected function initStorage()
    {
        $this->storage = new StubAbstractSessionDataStorage($this->session);
    }

    protected function tearDown()
    {
        unset($this->storage, $this->session);
    }

    public function testSet()
    {
        $data = [['productId' => 42, 'qty' => 100]];

        $this->sessionBag->expects($this->once())
            ->method('set')
            ->with($this->isType('string'), json_encode($data));

        $this->storage->set($data);
    }

    /**
     * @dataProvider getProductsDataProvider
     *
     * @param mixed $storageData
     * @param array $expectedData
     */
    public function testGet($storageData, array $expectedData)
    {
        $this->sessionBag->expects($this->once())
            ->method('get')
            ->with($this->isType('string'))
            ->willReturn($storageData);

        $this->sessionBag->expects($this->once())->method('has')->willReturn(true);

        $this->assertEquals($expectedData, $this->storage->get());
    }

    /**
     * @return array
     */
    public function getProductsDataProvider()
    {
        return [
            [null, []],
            ['test', []],
            [10, [10]],
            ['[{"productId":42,"qty":100}]', [['productId' => 42, 'qty' => 100]]],
            [
                '[{"productId":42,"qty":100}, {"productId":43,"qty":101}]',
                [['productId' => 42, 'qty' => 100], ['productId' => 43, 'qty' => 101]],
            ],
            ['[{invalid_json:100}]', []],
            [false, []],
            ['', []],
            ['[]', []],
        ];
    }

    public function testRemove()
    {
        $this->sessionBag->expects($this->once())->method('has')->willReturn(true);

        $this->sessionBag->expects($this->once())
            ->method('remove')
            ->with($this->isType('string'));

        $this->storage->remove();
    }

    public function testNothingToRemove()
    {
        $this->sessionBag->expects($this->once())->method('has')->willReturn(false);
        $this->sessionBag->expects($this->never())->method('remove');

        $this->storage->remove();
    }

    public function testNothingToGet()
    {
        $this->sessionBag->expects($this->once())->method('has')->willReturn(false);
        $this->sessionBag->expects($this->never())->method('get');

        $this->storage->get();
    }
}
