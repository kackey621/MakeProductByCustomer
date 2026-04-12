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
use Plugin\MPBC43\EventListener\CartEventListener;

class CartEventListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testSubscribedEventsAreEnabled(): void
    {
        $events = CartEventListener::getSubscribedEvents();

        $this->assertArrayHasKey(EccubeEvents::FRONT_CART_ADD_COMPLETE, $events);
        $this->assertSame('onCartAdd', $events[EccubeEvents::FRONT_CART_ADD_COMPLETE]);

        $this->assertArrayHasKey(EccubeEvents::FRONT_CART_INDEX_INITIALIZE, $events);
        $this->assertSame('onCartIndex', $events[EccubeEvents::FRONT_CART_INDEX_INITIALIZE]);
    }

    private function buildListener(string $currentSessionId): array
    {
        $em = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
        $logger = Mockery::mock(\Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();
        $cartService = Mockery::mock(\Eccube\Service\CartService::class);

        $session = Mockery::mock(\Symfony\Component\HttpFoundation\Session\SessionInterface::class);
        $session->shouldReceive('getId')->andReturn($currentSessionId);

        $request = Mockery::mock(\Symfony\Component\HttpFoundation\Request::class);
        $request->shouldReceive('getSession')->andReturn($session);

        $requestStack = Mockery::mock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->shouldReceive('getCurrentRequest')->andReturn($request);

        $listener = new CartEventListener($em, $logger, $requestStack, $cartService);
        return [$listener, $cartService, $em];
    }

    public function testProductFromSameSessionIsKept(): void
    {
        [$listener, $cartService, $em] = $this->buildListener('abcdef1234567890');

        $product = new Product();
        $product->setId(1);
        $product->setName('テスト商品');
        $product->setDescriptionDetail('カスタム商品: test [セッション: abcdef12]');

        $productClass = new ProductClass();
        $productClass->setId(10);
        $productClass->setProduct($product);

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $cartService->shouldReceive('getCart')->andReturn($cart);
        $em->shouldReceive('find')
            ->with(\Eccube\Entity\Product::class, 1)
            ->andReturn($product);
        $cartService->shouldNotReceive('save');

        $event = new EventArgs([]);
        $listener->onCartIndex($event);

        $this->assertCount(1, $cart->getCartItems());
    }

    public function testProductFromDifferentSessionIsRemoved(): void
    {
        [$listener, $cartService, $em] = $this->buildListener('zzzzzzzz99999999');

        $product = new Product();
        $product->setId(2);
        $product->setName('別セッション商品');
        $product->setDescriptionDetail('カスタム商品: test [セッション: abcdef12]');

        $productClass = new ProductClass();
        $productClass->setId(20);
        $productClass->setProduct($product);

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $cartService->shouldReceive('getCart')->andReturn($cart);
        $em->shouldReceive('find')
            ->with(\Eccube\Entity\Product::class, 2)
            ->andReturn($product);
        $cartService->shouldReceive('save')->once();

        $event = new EventArgs([]);
        $listener->onCartIndex($event);

        $this->assertCount(0, $cart->getCartItems());
    }

    public function testAlreadyPurchasedProductIsRemoved(): void
    {
        [$listener, $cartService, $em] = $this->buildListener('abcdef1234567890');

        $product = new Product();
        $product->setId(3);
        $product->setName('テスト商品 [購入済み]');
        $product->setDescriptionDetail('カスタム商品: test [セッション: abcdef12]');

        $productClass = new ProductClass();
        $productClass->setId(30);
        $productClass->setProduct($product);

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $cartService->shouldReceive('getCart')->andReturn($cart);
        $em->shouldReceive('find')
            ->with(\Eccube\Entity\Product::class, 3)
            ->andReturn($product);
        $cartService->shouldReceive('save')->once();

        $event = new EventArgs([]);
        $listener->onCartIndex($event);

        $this->assertCount(0, $cart->getCartItems());
    }

    public function testNullCartIsHandledGracefully(): void
    {
        [$listener, $cartService] = $this->buildListener('abcdef1234567890');

        $cartService->shouldReceive('getCart')->andReturn(null);

        $event = new EventArgs([]);
        $listener->onCartIndex($event);
        $this->assertTrue(true); // 例外なく完了
    }

    public function testNullRequestIsHandledGracefully(): void
    {
        $em = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
        $logger = Mockery::mock(\Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();
        $cartService = Mockery::mock(\Eccube\Service\CartService::class);

        $requestStack = Mockery::mock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->shouldReceive('getCurrentRequest')->andReturn(null);

        $listener = new CartEventListener($em, $logger, $requestStack, $cartService);

        $event = new EventArgs([]);
        $listener->onCartIndex($event);
        $this->assertTrue(true); // 例外なく完了
    }

    public function testNonCustomProductIsNotAffected(): void
    {
        [$listener, $cartService, $em] = $this->buildListener('abcdef1234567890');

        $product = new Product();
        $product->setId(4);
        $product->setName('通常商品');
        $product->setDescriptionDetail('通常の商品説明文');

        $productClass = new ProductClass();
        $productClass->setId(40);
        $productClass->setProduct($product);

        $cartItem = new CartItem();
        $cartItem->setProductClass($productClass);

        $cart = new Cart();
        $cart->addCartItem($cartItem);

        $cartService->shouldReceive('getCart')->andReturn($cart);
        $em->shouldReceive('find')
            ->with(\Eccube\Entity\Product::class, 4)
            ->andReturn($product);
        $cartService->shouldNotReceive('save');

        $event = new EventArgs([]);
        $listener->onCartIndex($event);

        $this->assertCount(1, $cart->getCartItems());
    }
}
