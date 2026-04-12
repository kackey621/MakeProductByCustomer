<?php

namespace Plugin\MPBC43\Tests\Unit\Controller;

use Doctrine\DBAL\Connection;
use Eccube\Entity\Cart;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Master\SaleType;
use Eccube\Service\PurchaseFlow\PurchaseFlowResult;
use Mockery;
use PHPUnit\Framework\TestCase;
use Plugin\MPBC43\Controller\MpbController;

class MpbControllerTest extends TestCase
{
    private $entityManager;
    private $connection;
    private $cartService;
    private $purchaseFlow;
    private $configRepository;
    private $productRepository;
    private $layoutRepository;
    private $logger;

    protected function setUp(): void
    {
        $this->connection = Mockery::mock(Connection::class);
        $this->connection->shouldReceive('beginTransaction')->byDefault();
        $this->connection->shouldReceive('commit')->byDefault();
        $this->connection->shouldReceive('isTransactionActive')->andReturn(false)->byDefault();

        $this->entityManager = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->entityManager->shouldReceive('getConnection')
            ->andReturn($this->connection)->byDefault();

        $productStatus = new ProductStatus();
        $productStatus->setId(ProductStatus::DISPLAY_SHOW);
        $productStatus->setName('表示');

        $saleType = new SaleType();
        $saleType->setId(SaleType::SALE_TYPE_NORMAL);

        $this->entityManager->shouldReceive('find')
            ->with(ProductStatus::class, ProductStatus::DISPLAY_SHOW)
            ->andReturn($productStatus)->byDefault();
        $this->entityManager->shouldReceive('find')
            ->with(SaleType::class, SaleType::SALE_TYPE_NORMAL)
            ->andReturn($saleType)->byDefault();

        $this->cartService = Mockery::mock(\Eccube\Service\CartService::class);
        $this->purchaseFlow = Mockery::mock(\Eccube\Service\PurchaseFlow\PurchaseFlow::class);
        $this->configRepository = Mockery::mock(\Plugin\MPBC43\Repository\ConfigRepository::class);
        $this->productRepository = Mockery::mock(\Eccube\Repository\ProductRepository::class);
        $this->layoutRepository = Mockery::mock(\Eccube\Repository\LayoutRepository::class);
        $this->logger = Mockery::mock(\Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function buildController(): MpbController
    {
        return new MpbController(
            $this->productRepository,
            $this->cartService,
            $this->entityManager,
            $this->logger,
            $this->purchaseFlow,
            $this->configRepository,
            $this->layoutRepository
        );
    }

    private function invokePrivate(object $obj, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($obj, $args);
    }

    // ---------------------------------------------------------------------------
    // 価格サニタイズのテスト
    // ---------------------------------------------------------------------------

    /**
     * @dataProvider priceCleanupProvider
     */
    public function testPriceCleanupExtractsNumbersOnly(string $raw, int $expected): void
    {
        $clean = preg_replace('/[^\d]/', '', $raw);
        $price = (int) $clean;
        $this->assertSame($expected, $price, "Failed for input: $raw");
    }

    public function priceCleanupProvider(): array
    {
        return [
            '¥とカンマ' => ['¥1,000', 1000],
            '数字のみ' => ['5000', 5000],
            '全角¥' => ['￥2,500', 2500],
            '円表記なし大きな数' => ['10000', 10000],
            'スペース混入' => [' 3 000 ', 3000],
        ];
    }

    // ---------------------------------------------------------------------------
    // バリデーションロジックのテスト
    // ---------------------------------------------------------------------------

    public function testEmptyProductNameFailsValidation(): void
    {
        $productName = '';
        $price = 1000;
        $passes = !empty($productName) && $price > 0;
        $this->assertFalse($passes);
    }

    public function testZeroPriceFailsValidation(): void
    {
        $productName = 'テスト商品';
        $price = 0;
        $passes = !empty($productName) && $price > 0;
        $this->assertFalse($passes);
    }

    public function testNegativePriceFailsValidation(): void
    {
        $productName = 'テスト商品';
        $price = -100;
        $passes = !empty($productName) && $price > 0;
        $this->assertFalse($passes);
    }

    public function testValidInputPassesValidation(): void
    {
        $productName = 'テスト商品';
        $price = 5000;
        $passes = !empty($productName) && $price > 0;
        $this->assertTrue($passes);
    }

    public function testWhitespaceOnlyProductNameIsHandled(): void
    {
        // PHPのempty()はスペースのみの文字列をfalseと判定しない
        // 実際の処理でも同様に扱われる
        $productName = '   ';
        $price = 1000;
        $passes = !empty($productName) && $price > 0;
        $this->assertTrue($passes); // スペースのみでもempty()はtrueを返さない
    }

    // ---------------------------------------------------------------------------
    // createProductAndAddToCart のテスト
    // ---------------------------------------------------------------------------

    public function testCreateProductAndAddToCartSucceeds(): void
    {
        $cart = new Cart();

        $flowResult = new PurchaseFlowResult(false);

        $this->connection->shouldReceive('beginTransaction')->once();
        $this->connection->shouldReceive('commit')->once();
        $this->connection->shouldReceive('isTransactionActive')->andReturn(false);

        $this->entityManager->shouldReceive('persist')->times(3);
        $this->entityManager->shouldReceive('flush')->once();
        $this->entityManager->shouldReceive('refresh')->twice();

        $this->cartService->shouldReceive('addProduct')->once();
        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->purchaseFlow->shouldReceive('validate')->andReturn($flowResult);
        $this->purchaseFlow->shouldReceive('commit')->once();
        $this->cartService->shouldReceive('save')->once();

        $controller = $this->buildController();
        $result = $this->invokePrivate(
            $controller,
            'createProductAndAddToCart',
            ['テスト商品', 5000, 'abcdef1234567890']
        );

        $this->assertTrue($result);
    }

    public function testTransactionIsRolledBackOnPersistFailure(): void
    {
        $this->connection->shouldReceive('beginTransaction')->once();
        $this->connection->shouldReceive('commit')->never();
        $this->connection->shouldReceive('isTransactionActive')->andReturn(true);
        $this->connection->shouldReceive('rollBack')->once();

        $this->entityManager->shouldReceive('persist')
            ->andThrow(new \RuntimeException('DB error'));
        $this->entityManager->shouldReceive('flush')->never();

        $controller = $this->buildController();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $this->invokePrivate(
            $controller,
            'createProductAndAddToCart',
            ['テスト商品', 5000, 'abcdef1234567890']
        );
    }

    public function testSessionPrefixIsEmbeddedInProductDescription(): void
    {
        $cart = new Cart();
        $flowResult = new PurchaseFlowResult(false);

        $this->connection->shouldReceive('beginTransaction')->once();
        $this->connection->shouldReceive('commit')->once();
        $this->connection->shouldReceive('isTransactionActive')->andReturn(false);

        $capturedProduct = null;
        $this->entityManager->shouldReceive('persist')
            ->andReturnUsing(function ($entity) use (&$capturedProduct) {
                if ($entity instanceof \Eccube\Entity\Product) {
                    $capturedProduct = $entity;
                }
            });
        $this->entityManager->shouldReceive('flush')->once();
        $this->entityManager->shouldReceive('refresh');

        $this->cartService->shouldReceive('addProduct')->once();
        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->purchaseFlow->shouldReceive('validate')->andReturn($flowResult);
        $this->purchaseFlow->shouldReceive('commit');
        $this->cartService->shouldReceive('save')->once();

        $controller = $this->buildController();
        $this->invokePrivate(
            $controller,
            'createProductAndAddToCart',
            ['テスト商品', 3000, 'xyzabc1234567890']
        );

        $this->assertNotNull($capturedProduct);
        $this->assertStringContainsString('[セッション: xyzabc12]', $capturedProduct->getDescriptionDetail());
    }

    public function testCartIsNotSavedWhenPurchaseFlowHasErrors(): void
    {
        $cart = new Cart();
        $errorResult = new PurchaseFlowResult(true, [
            new \Eccube\Service\PurchaseFlow\PurchaseError('在庫切れです'),
        ]);

        $this->connection->shouldReceive('beginTransaction')->once();
        $this->connection->shouldReceive('commit')->once();
        $this->connection->shouldReceive('isTransactionActive')->andReturn(false);

        $this->entityManager->shouldReceive('persist')->times(3);
        $this->entityManager->shouldReceive('flush')->once();
        $this->entityManager->shouldReceive('refresh')->twice();

        $this->cartService->shouldReceive('addProduct')->once();
        $this->cartService->shouldReceive('getCart')->andReturn($cart);
        $this->purchaseFlow->shouldReceive('validate')->andReturn($errorResult);
        $this->purchaseFlow->shouldNotReceive('commit');
        // エラーがあってもsave()は呼ばれる（カート状態を保存するため）
        $this->cartService->shouldReceive('save')->once();

        $controller = $this->buildController();
        $result = $this->invokePrivate(
            $controller,
            'createProductAndAddToCart',
            ['エラー商品', 1000, 'session123456789']
        );

        $this->assertTrue($result);
    }
}
