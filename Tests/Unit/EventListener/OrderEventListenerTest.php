<?php

namespace Plugin\MPBC43\Tests\Unit\EventListener;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\OrderItem;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Mockery;
use PHPUnit\Framework\TestCase;
use Plugin\MPBC43\EventListener\OrderEventListener;

class OrderEventListenerTest extends TestCase
{
    private $entityManager;
    private $logger;
    private OrderEventListener $listener;

    protected function setUp(): void
    {
        $this->entityManager = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->logger = Mockery::mock(\Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();
        $this->listener = new OrderEventListener($this->entityManager, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testSubscribedEventsContainShoppingComplete(): void
    {
        $events = OrderEventListener::getSubscribedEvents();
        $this->assertArrayHasKey(EccubeEvents::FRONT_SHOPPING_COMPLETE_INITIALIZE, $events);
        $this->assertSame('onShoppingComplete', $events[EccubeEvents::FRONT_SHOPPING_COMPLETE_INITIALIZE]);
    }

    public function testNullOrderIsHandledGracefully(): void
    {
        $event = new EventArgs(['Order' => null]);
        // 例外なく実行できることを確認
        $this->listener->onShoppingComplete($event);
        $this->assertTrue(true);
    }

    public function testCustomProductIsDisabledAfterOrderCompletion(): void
    {
        $orderStatus = new OrderStatus();
        $orderStatus->setId(OrderStatus::NEW);

        $product = new Product();
        $product->setId(1);
        $product->setName('テスト商品');
        $product->setDescriptionDetail('カスタム商品: テスト [セッション: abc12345]');

        $productClass = new ProductClass();
        $productClass->setId(10);
        $productClass->setVisible(true);
        $productClass->setStock(1);
        $productClass->setStockUnlimited(false);
        $productClass->setProduct($product);
        $product->addProductClass($productClass);

        $orderItem = new OrderItem();
        $orderItem->setProduct($product);
        $orderItem->setProductClass($productClass);

        $order = new Order();
        $order->setId(100);
        $order->setOrderStatus($orderStatus);
        $order->getOrderItems()->add($orderItem);

        $this->entityManager->shouldReceive('persist')->with($productClass)->once();
        $this->entityManager->shouldReceive('persist')->with($product)->once();
        $this->entityManager->shouldReceive('flush')->once();

        $event = new EventArgs(['Order' => $order]);
        $this->listener->onShoppingComplete($event);

        $this->assertFalse($productClass->isVisible());
        $this->assertFalse($productClass->isStockUnlimited());
        $this->assertSame(0, $productClass->getStock());
        $this->assertStringContainsString('[購入済み]', $product->getName());
        $this->assertStringContainsString('購入済み', $product->getDescriptionDetail());
    }

    public function testNonCustomProductIsNotDisabled(): void
    {
        $orderStatus = new OrderStatus();
        $orderStatus->setId(OrderStatus::NEW);

        $product = new Product();
        $product->setId(2);
        $product->setName('通常商品');
        $product->setDescriptionDetail('通常の商品説明文');

        $productClass = new ProductClass();
        $productClass->setVisible(true);
        $productClass->setProduct($product);
        $product->addProductClass($productClass);

        $orderItem = new OrderItem();
        $orderItem->setProduct($product);

        $order = new Order();
        $order->setId(101);
        $order->setOrderStatus($orderStatus);
        $order->getOrderItems()->add($orderItem);

        // persist/flushは呼ばれない（通常商品は変更されない）
        $this->entityManager->shouldNotReceive('persist');
        $this->entityManager->shouldNotReceive('flush');

        $event = new EventArgs(['Order' => $order]);
        $this->listener->onShoppingComplete($event);

        $this->assertTrue($productClass->isVisible());
        $this->assertStringNotContainsString('[購入済み]', $product->getName());
    }

    public function testAllProductClassesAreDisabledForCustomProduct(): void
    {
        $orderStatus = new OrderStatus();
        $orderStatus->setId(OrderStatus::NEW);

        $product = new Product();
        $product->setId(3);
        $product->setName('マルチクラス商品');
        $product->setDescriptionDetail('カスタム商品: マルチ [セッション: xyz99999]');

        $productClass1 = new ProductClass();
        $productClass1->setId(21);
        $productClass1->setVisible(true);
        $productClass1->setStock(1);
        $productClass1->setProduct($product);
        $product->addProductClass($productClass1);

        $productClass2 = new ProductClass();
        $productClass2->setId(22);
        $productClass2->setVisible(true);
        $productClass2->setStock(1);
        $productClass2->setProduct($product);
        $product->addProductClass($productClass2);

        $orderItem = new OrderItem();
        $orderItem->setProduct($product);

        $order = new Order();
        $order->setId(102);
        $order->setOrderStatus($orderStatus);
        $order->getOrderItems()->add($orderItem);

        // 2 productClasses + 1 product = 3回persist
        $this->entityManager->shouldReceive('persist')->times(3);
        $this->entityManager->shouldReceive('flush')->once();

        $event = new EventArgs(['Order' => $order]);
        $this->listener->onShoppingComplete($event);

        $this->assertFalse($productClass1->isVisible());
        $this->assertFalse($productClass2->isVisible());
        $this->assertSame(0, $productClass1->getStock());
        $this->assertSame(0, $productClass2->getStock());
    }

    public function testOrderItemWithNullProductIsSkipped(): void
    {
        $orderStatus = new OrderStatus();
        $orderStatus->setId(OrderStatus::NEW);

        $orderItem = new OrderItem();
        // productをセットしない（null）

        $order = new Order();
        $order->setId(103);
        $order->setOrderStatus($orderStatus);
        $order->getOrderItems()->add($orderItem);

        $this->entityManager->shouldNotReceive('flush');

        $event = new EventArgs(['Order' => $order]);
        $this->listener->onShoppingComplete($event);
        $this->assertTrue(true); // 例外なく完了
    }

    public function testAlreadyDisabledProductIsNotDoubleMarked(): void
    {
        $orderStatus = new OrderStatus();
        $orderStatus->setId(OrderStatus::NEW);

        $product = new Product();
        $product->setId(4);
        $product->setName('テスト商品 [購入済み]'); // already marked
        $product->setDescriptionDetail('カスタム商品: テスト [セッション: abc12345] ※この商品は購入済みです。');

        $productClass = new ProductClass();
        $productClass->setId(30);
        $productClass->setVisible(false);
        $productClass->setStock(0);
        $productClass->setProduct($product);
        $product->addProductClass($productClass);

        $orderItem = new OrderItem();
        $orderItem->setProduct($product);

        $order = new Order();
        $order->setId(104);
        $order->setOrderStatus($orderStatus);
        $order->getOrderItems()->add($orderItem);

        // ProductClassはpersistされるが、商品名は変更されない
        $this->entityManager->shouldReceive('persist')->with($productClass)->once();
        $this->entityManager->shouldReceive('flush')->once();

        $event = new EventArgs(['Order' => $order]);
        $this->listener->onShoppingComplete($event);

        // 商品名に[購入済み]が2重につかないことを確認
        $this->assertSame('テスト商品 [購入済み]', $product->getName());
    }
}
