<?php

namespace Plugin\MPBC43\Tests\Unit\EventListener;

use Eccube\Entity\Cart;
use Eccube\Entity\CartItem;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Mockery;
use PHPUnit\Framework\TestCase;
use Plugin\MPBC43\EventListener\CartIntegrityEventListener;

class CartIntegrityEventListenerTest extends TestCase
{
    private $entityManager;
    private $logger;
    private $cartService;
    private CartIntegrityEventListener $listener;

    protected function setUp(): void
    {
        $this->entityManager = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->logger = Mockery::mock(\Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();
        $this->cartService = Mockery::mock(\Eccube\Service\CartService::class);

        $this->listener = new CartIntegrityEventListener(
            $this->entityManager,
            $this->logger,
            $this->cartService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testSubscribedEventsContainCartIndex(): void
    {
        $events = CartIntegrityEventListener::getSubscribedEvents();
        $this->assertArrayHasKey(EccubeEvents::FRONT_CART_INDEX_INITIALIZE, $events);
        $this->assertSame('onCartIndex', $events[EccubeEvents::FRONT_CART_INDEX_INITIALIZE]);
    }

    public function testNullCartIsHandledGracefully(): void
    {
        $this->cartService->shouldReceive('getCart')->andReturn(null);

        $event = new EventArgs([]);
        $this->listener->onCartIndex($event);
        $this->assertTrue(true); // 例外なく完了
    }

    public function testItemWithNullProductClassIsRemoved(): void
    {
        $cartItem = new CartItem();
        // productClassをセットしない

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->cartService->shouldReceive('save')->once();

        $event = new EventArgs([]);
        $this->listener->onCartIndex($event);

        $this->assertCount(0, $cart->getCartItems());
    }

    public function testItemWithProductClassWithoutIdIsRemoved(): void
    {
        $productClass = new ProductClass();
        // IDをセットしない（persist前の状態）

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->cartService->shouldReceive('save')->once();

        $event = new EventArgs([]);
        $this->listener->onCartIndex($event);

        $this->assertCount(0, $cart->getCartItems());
    }

    public function testItemWithDeletedProductClassIsRemoved(): void
    {
        $productClass = new ProductClass();
        $productClass->setId(999);

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->entityManager->shouldReceive('find')
            ->with(ProductClass::class, 999)
            ->andReturn(null); // DBに存在しない
        $this->cartService->shouldReceive('save')->once();

        $event = new EventArgs([]);
        $this->listener->onCartIndex($event);

        $this->assertCount(0, $cart->getCartItems());
    }

    public function testItemWithDeletedProductIsRemoved(): void
    {
        $product = new Product();
        $product->setId(50);

        $productClass = new ProductClass();
        $productClass->setId(5);
        $productClass->setProduct($product);

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->entityManager->shouldReceive('find')
            ->with(ProductClass::class, 5)
            ->andReturn($productClass);
        $this->entityManager->shouldReceive('find')
            ->with(Product::class, 50)
            ->andReturn(null); // Productが削除済み
        $this->cartService->shouldReceive('save')->once();

        $event = new EventArgs([]);
        $this->listener->onCartIndex($event);

        $this->assertCount(0, $cart->getCartItems());
    }

    public function testValidItemIsKept(): void
    {
        $product = new Product();
        $product->setId(10);

        $productClass = new ProductClass();
        $productClass->setId(5);
        $productClass->setProduct($product);

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->entityManager->shouldReceive('find')
            ->with(ProductClass::class, 5)
            ->andReturn($productClass);
        $this->entityManager->shouldReceive('find')
            ->with(Product::class, 10)
            ->andReturn($product);

        // 有効なアイテムのみなのでsave()は呼ばれない
        $this->cartService->shouldNotReceive('save');

        $event = new EventArgs([]);
        $this->listener->onCartIndex($event);

        $this->assertCount(1, $cart->getCartItems());
    }

    public function testEmptyCartIsHandledGracefully(): void
    {
        $cart = new Cart();

        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->cartService->shouldNotReceive('save');

        $event = new EventArgs([]);
        $this->listener->onCartIndex($event);

        $this->assertCount(0, $cart->getCartItems());
    }

    public function testExceptionDuringEntityFindMarksItemForRemoval(): void
    {
        $productClass = new ProductClass();
        $productClass->setId(77);

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->entityManager->shouldReceive('find')
            ->with(ProductClass::class, 77)
            ->andThrow(new \RuntimeException('DB connection error'));
        $this->cartService->shouldReceive('save')->once();

        $event = new EventArgs([]);
        $this->listener->onCartIndex($event);

        $this->assertCount(0, $cart->getCartItems());
    }
}
