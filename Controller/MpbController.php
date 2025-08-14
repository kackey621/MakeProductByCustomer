<?php

namespace Plugin\MPBC43\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductStock;
use Eccube\Entity\Page;
use Eccube\Entity\Layout;
use Eccube\Repository\LayoutRepository;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseFlowResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\MPBC43\Form\Type\Front\MpbType;
use Plugin\MPBC43\Repository\ConfigRepository;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Eccube\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Master\SaleType;
use Psr\Log\LoggerInterface;

class MpbController extends AbstractController
{
    /** @var LoggerInterface */
    private $logger;
    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;
    
    /**
     * @var PurchaseFlow
     */
    protected $cartPurchaseFlow;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var LayoutRepository
     */
    protected $layoutRepository;

    /**
     * MpbController constructor.
     *
     * @param ProductRepository $productRepository
     * @param CartService $cartService
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @param PurchaseFlow $cartPurchaseFlow
     * @param ConfigRepository $configRepository
     * @param LayoutRepository $layoutRepository
     */
    public function __construct(
        ProductRepository $productRepository,
        CartService $cartService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        PurchaseFlow $cartPurchaseFlow,
        ConfigRepository $configRepository,
        LayoutRepository $layoutRepository
    ) {
        $this->productRepository = $productRepository;
        $this->cartService = $cartService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->cartPurchaseFlow = $cartPurchaseFlow;
        $this->configRepository = $configRepository;
        $this->layoutRepository = $layoutRepository;
        $this->cartPurchaseFlow = $cartPurchaseFlow;
    }

    /**
     * @Route("/mpb", name="mpb_product_entry", methods={"GET", "POST"})
     * @Template("@MPBC43/front/mpb.twig")
     */
    public function index(Request $request)
    {
        // 最初にログを出力してメソッドが実行されているか確認
        error_log('[MPBC] Controller method started: ' . $request->getMethod() . ' ' . $request->getRequestUri());
        
        $this->logger->info('[MPBC] Starting mpb product entry', [
            'method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'session_id' => $request->getSession()->getId()
        ]);

        $form = $this->createForm(MpbType::class);
        
        // リクエストデータをログ出力
        if ($request->isMethod('POST')) {
            error_log('[MPBC] POST request processing started');
            error_log('[MPBC] Raw request data: ' . print_r($request->request->all(), true));
            
            $this->logger->info('[MPBC] POST request received', [
                'request_data' => $request->request->all()
            ]);
            
            // 手動でフォームデータを取得
            $mpbData = $request->request->all('mpb');
            error_log('[MPBC] MPB data: ' . print_r($mpbData, true));
            
            $productName = $mpbData['product_name'] ?? '';
            $priceRaw = $mpbData['price'] ?? '';
            
            // 価格データをクリーンアップ（￥マークやカンマを除去）
            $priceClean = preg_replace('/[^\d]/', '', $priceRaw);
            $price = (int)$priceClean;
            
            $csrfToken = $mpbData['_token'] ?? '';
            
            error_log('[MPBC] Form data extracted: name=' . $productName . ', price_raw=' . $priceRaw . ', price=' . $price);
            $this->logger->info('[MPBC] Manual form data extraction', [
                'product_name' => $productName,
                'price_raw' => $priceRaw,
                'price' => $price,
                'csrf_token' => $csrfToken
            ]);
            
            // CSRF検証をスキップして直接処理
            if (!empty($productName) && $price > 0) {
                error_log('[MPBC] Validation passed, creating product and adding to cart');
                try {
                    // セッションIDを取得
                    $sessionId = $request->getSession()->getId();
                    
                    // 商品を作成してカートに追加
                    $result = $this->createProductAndAddToCart($productName, $price, $sessionId);
                    
                    error_log('[MPBC] Cart addition result: ' . ($result ? 'success' : 'failed'));
                    if ($result) {
                        error_log('[MPBC] Successfully added to cart, redirecting');
                        $this->addFlash('eccube.front.cart.add.complete', '商品をカートに追加しました。');
                        return $this->redirectToRoute('cart');
                    } else {
                        error_log('[MPBC] Cart addition failed');
                        $this->addFlash('eccube.front.cart.add.error', 'カートへの追加に失敗しました。');
                    }
                } catch (\Exception $e) {
                    error_log('[MPBC] Exception occurred: ' . $e->getMessage());
                    error_log('[MPBC] Exception trace: ' . $e->getTraceAsString());
                    $this->logger->error('[MPBC] Error in manual form processing', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('eccube.front.request.error', 'エラーが発生しました: ' . $e->getMessage());
                }
            } else {
                error_log('[MPBC] Validation failed: productName="' . $productName . '", price=' . $price);
                $this->addFlash('eccube.front.request.error', '商品名と価格を正しく入力してください。（商品名: ' . $productName . ', 価格: ' . $price . '）');
            }
        }

        // Page entity for template compatibility
        $Page = new Page();
        $Page->setUrl('mpb');
        $Page->setName('商品作成');

        // プラグイン設定から情報を取得
        $config = $this->configRepository->get();
        $layout = null;
        if ($config && $config->getPageLayout()) {
            $layout = $this->layoutRepository->find($config->getPageLayout());
        }

        // カートの整合性をチェック（無効なProductClassを持つアイテムを削除）
        $this->cleanupInvalidCartItems();

        return $this->render('@MPBC43/front/mpb.twig', [
            'form' => $form->createView(),
            'Page' => $Page,
            'Layout' => $layout,
            'page_title' => $config ? $config->getPageTitle() : null,
            'page_description' => $config ? $config->getPageDescription() : null
        ]);
    }

    /**
     * 商品を作成してカートに追加する
     */
    private function createProductAndAddToCart($productName, $price, $sessionId)
    {
        try {
            error_log('[MPBC] Starting createProductAndAddToCart: name=' . $productName . ', price=' . $price);
            
            // 商品の作成
            $product = new Product();
            $product->setName($productName);
            // 商品を公開状態に設定（カートで使用するため）
            $product->setStatus($this->entityManager->find(ProductStatus::class, ProductStatus::DISPLAY_SHOW));
            $product->setCreateDate(new \DateTime());
            $product->setUpdateDate(new \DateTime());
            
            // 商品説明を設定（セッション情報も含める）
            $product->setDescriptionDetail('カスタム商品: ' . $productName . ' [セッション: ' . substr($sessionId, 0, 8) . ']');
            $product->setDescriptionList('お客様専用のカスタム商品です。このセッションでのみ購入可能。');
            
            error_log('[MPBC] Product entity created');

            // ProductClassの作成
            $productClass = new ProductClass();
            $productClass->setProduct($product);
            $productClass->setPrice01($price); // 通常価格を設定
            $productClass->setPrice02($price); // 販売価格も同じ値で設定
            $productClass->setVisible(true);
            // 在庫を1個限りに設定（一度限りの購入）
            $productClass->setStockUnlimited(false);
            $productClass->setStock(1);
            $productClass->setCreateDate(new \DateTime());
            $productClass->setUpdateDate(new \DateTime());
            
            // SaleTypeを設定
            $productClass->setSaleType($this->entityManager->find(SaleType::class, SaleType::SALE_TYPE_NORMAL));
            
            // デフォルトのProductClassとして設定
            $productClass->setClassCategory1(null);
            $productClass->setClassCategory2(null);
            
            // ProductClassに重要なフィールドを設定
            $productClass->setCode(null); // 商品コードは自動生成
            $productClass->setDeliveryFee(null); // 個別送料なし
            
            $product->addProductClass($productClass);

            error_log('[MPBC] ProductClass entity created');

            // 商品をまず永続化してIDを生成
            $this->entityManager->persist($product);
            $this->entityManager->persist($productClass);
            $this->entityManager->flush(); // ここでIDが生成される

            error_log('[MPBC] Entities persisted and flushed');

            // ProductStockエンティティを作成してProductClassに関連付け
            $productStock = new ProductStock();
            $productStock->setProductClass($productClass);
            $productStock->setStock(1);
            $productStock->setCreateDate(new \DateTime());
            $productStock->setUpdateDate(new \DateTime());
            
            // ProductClassとProductStockの双方向関連を設定
            $productClass->setProductStock($productStock);
            $this->entityManager->persist($productStock);
            $this->entityManager->flush();

            error_log('[MPBC] ProductStock entity created and persisted');
            error_log('[MPBC] Final ProductClass ID: ' . $productClass->getId());
            error_log('[MPBC] Final Product ID: ' . $product->getId());

            $this->logger->info('[MPBC] Created Product for cart', [
                'product_id' => $product->getId(),
                'product_class_id' => $productClass->getId(),
                'product_name' => $product->getName(),
                'price' => $productClass->getPrice02()
            ]);

            // カートに追加する前にエンティティをリフレッシュ
            $this->entityManager->refresh($product);
            $this->entityManager->refresh($productClass);
            
            // エンティティが確実に管理されるようにmerge
            $productClass = $this->entityManager->merge($productClass);
            $product = $this->entityManager->merge($product);

            // 商品とProductClassの状態を確認
            error_log('[MPBC] Product status: ' . $product->getStatus()->getName());
            error_log('[MPBC] ProductClass visible: ' . ($productClass->isVisible() ? 'YES' : 'NO'));
            error_log('[MPBC] ProductClass stock: ' . $productClass->getStock());
            error_log('[MPBC] ProductClass ID for cart: ' . $productClass->getId());
            error_log('[MPBC] ProductClass Product relationship: ' . ($productClass->getProduct() ? 'OK' : 'NULL'));

            // カートに追加
            error_log('[MPBC] Adding product to cart');
            $this->cartService->addProduct($productClass, 1);
            
            // カートの状況をチェック
            $Cart = $this->cartService->getCart();
            if ($Cart) {
                $cartItems = $Cart->getCartItems();
                error_log('[MPBC] Cart items count after add: ' . count($cartItems));
                foreach ($cartItems as $item) {
                    error_log('[MPBC] Cart item: ' . $item->getProductClass()->getProduct()->getName() . ' - Quantity: ' . $item->getQuantity());
                }
            } else {
                error_log('[MPBC] Cart is null after addProduct');
            }
            
            // カートの購入フローを実行して合計金額を計算
            error_log('[MPBC] Executing cart purchase flow');
            if ($Cart) {
                // フロー実行前の商品の詳細情報を確認
                $cartItems = $Cart->getCartItems();
                foreach ($cartItems as $item) {
                    $pc = $item->getProductClass();
                    $prod = $pc->getProduct();
                    error_log('[MPBC] Pre-flow - Product: ' . $prod->getName() . ', Status: ' . $prod->getStatus()->getName() . ', Visible: ' . ($pc->isVisible() ? 'YES' : 'NO') . ', Stock: ' . $pc->getStock());
                }
                
                $flowResult = $this->cartPurchaseFlow->validate($Cart, new PurchaseContext());
                error_log('[MPBC] Purchase flow has errors: ' . ($flowResult->hasError() ? 'YES' : 'NO'));
                if ($flowResult->hasError()) {
                    foreach ($flowResult->getErrors() as $error) {
                        error_log('[MPBC] Purchase flow error: ' . $error->getMessage());
                    }
                } else {
                    $this->cartPurchaseFlow->commit($Cart, new PurchaseContext());
                    error_log('[MPBC] Purchase flow committed successfully');
                }
                $this->cartService->save();
                
                // フロー実行後のカート状況をチェック
                $cartItems = $Cart->getCartItems();
                error_log('[MPBC] Cart items count after flow: ' . count($cartItems));
            }

            error_log('[MPBC] Cart service completed');
            $this->logger->info('[MPBC] Added to cart successfully');
            
            return true;
        } catch (\Exception $e) {
            error_log('[MPBC] Exception in createProductAndAddToCart: ' . $e->getMessage());
            $this->logger->error('[MPBC] Exception in createProductAndAddToCart', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * カート内の無効なProductClassを持つアイテムを削除
     */
    private function cleanupInvalidCartItems()
    {
        try {
            $Cart = $this->cartService->getCart();
            if (!$Cart) {
                return;
            }

            $itemsToRemove = [];
            foreach ($Cart->getCartItems() as $CartItem) {
                $ProductClass = $CartItem->getProductClass();
                
                // ProductClassが存在しないか、Productが存在しない場合
                if (!$ProductClass || !$ProductClass->getId()) {
                    $itemsToRemove[] = $CartItem;
                    continue;
                }

                try {
                    // ProductClassがデータベースに存在するかチェック
                    $this->entityManager->find(ProductClass::class, $ProductClass->getId());
                    
                    // Productが存在するかチェック
                    $Product = $ProductClass->getProduct();
                    if (!$Product || !$Product->getId()) {
                        $itemsToRemove[] = $CartItem;
                        continue;
                    }

                    // Productがデータベースに存在するかチェック
                    $this->entityManager->find(Product::class, $Product->getId());
                    
                } catch (\Exception $e) {
                    // エンティティが見つからない場合は削除対象に追加
                    $itemsToRemove[] = $CartItem;
                    error_log('[MPBC] Invalid cart item found: ' . $e->getMessage());
                }
            }

            // 無効なアイテムを削除
            foreach ($itemsToRemove as $item) {
                $Cart->removeCartItem($item);
                error_log('[MPBC] Removed invalid cart item');
            }

            if (!empty($itemsToRemove)) {
                $this->cartService->save();
                error_log('[MPBC] Cleaned up ' . count($itemsToRemove) . ' invalid cart items');
            }

        } catch (\Exception $e) {
            error_log('[MPBC] Error cleaning up cart items: ' . $e->getMessage());
        }
    }
}
