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
    }

    /**
     * @Route("/mpb", name="mpb_product_entry", methods={"GET", "POST"})
     * @Template("@MPBC43/front/mpb.twig")
     */
    public function index(Request $request)
    {
        $this->logger->info('[MPBC] Starting mpb product entry', [
            'method' => $request->getMethod(),
            'session_id' => substr($request->getSession()->getId(), 0, 8),
        ]);

        $form = $this->createForm(MpbType::class);

        if ($request->isMethod('POST')) {
            // フォームデータを手動取得（クライアント側フォーマット ¥/カンマ をFormバリデーターがブロックするため）
            $mpbData = $request->request->all('mpb');

            $productName = $mpbData['product_name'] ?? '';
            $priceRaw = $mpbData['price'] ?? '';

            // 価格から数値のみ抽出（¥マーク・カンマ・全角文字を除去）
            $priceClean = preg_replace('/[^\d]/', '', $priceRaw);
            $price = (int) $priceClean;

            $this->logger->info('[MPBC] POST request received', [
                'product_name' => $productName,
                'price' => $price,
            ]);

            if (!empty($productName) && $price > 0) {
                try {
                    $sessionId = $request->getSession()->getId();
                    $result = $this->createProductAndAddToCart($productName, $price, $sessionId);

                    if ($result) {
                        $this->addFlash('eccube.front.cart.add.complete', '商品をカートに追加しました。');
                        return $this->redirectToRoute('cart');
                    }

                    $this->addFlash('eccube.front.cart.add.error', 'カートへの追加に失敗しました。');
                } catch (\Exception $e) {
                    $this->logger->error('[MPBC] Error adding product to cart', [
                        'error' => $e->getMessage(),
                    ]);
                    $this->addFlash('eccube.front.request.error', 'エラーが発生しました。しばらくしてから再度お試しください。');
                }
            } else {
                $this->addFlash('eccube.front.request.error', '商品名と価格を正しく入力してください。');
            }
        }

        $Page = new Page();
        $Page->setUrl('mpb');
        $Page->setName('商品作成');

        $config = $this->configRepository->get();
        $layout = null;
        if ($config && $config->getPageLayout()) {
            $layout = $this->layoutRepository->find($config->getPageLayout());
        }

        $this->cleanupInvalidCartItems();

        return $this->render('@MPBC43/front/mpb.twig', [
            'form' => $form->createView(),
            'Page' => $Page,
            'Layout' => $layout,
            'page_title' => $config ? $config->getPageTitle() : null,
            'page_description' => $config ? $config->getPageDescription() : null,
        ]);
    }

    /**
     * 商品を作成してカートに追加する。
     * すべてのDB操作を単一トランザクションで行い、失敗時はロールバックする。
     *
     * @param string $productName
     * @param int $price
     * @param string $sessionId
     * @return bool
     * @throws \Exception
     */
    private function createProductAndAddToCart(string $productName, int $price, string $sessionId): bool
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            // 商品エンティティ作成
            $product = new Product();
            $product->setName($productName);
            $product->setStatus($this->entityManager->find(ProductStatus::class, ProductStatus::DISPLAY_SHOW));
            $product->setCreateDate(new \DateTime());
            $product->setUpdateDate(new \DateTime());
            $product->setDescriptionDetail(
                'カスタム商品: ' . $productName . ' [セッション: ' . substr($sessionId, 0, 8) . ']'
            );
            $product->setDescriptionList('お客様専用のカスタム商品です。このセッションでのみ購入可能。');

            // ProductClassエンティティ作成
            $productClass = new ProductClass();
            $productClass->setProduct($product);
            $productClass->setPrice01($price);
            $productClass->setPrice02($price);
            $productClass->setVisible(true);
            $productClass->setStockUnlimited(false);
            $productClass->setStock(1);
            $productClass->setCreateDate(new \DateTime());
            $productClass->setUpdateDate(new \DateTime());
            $productClass->setSaleType($this->entityManager->find(SaleType::class, SaleType::SALE_TYPE_NORMAL));
            $productClass->setClassCategory1(null);
            $productClass->setClassCategory2(null);
            $productClass->setCode(null);
            $productClass->setDeliveryFee(null);
            $product->addProductClass($productClass);

            // ProductStockエンティティ作成
            $productStock = new ProductStock();
            $productStock->setProductClass($productClass);
            $productStock->setStock(1);
            $productStock->setCreateDate(new \DateTime());
            $productStock->setUpdateDate(new \DateTime());
            $productClass->setProductStock($productStock);

            // 単一トランザクションで一括永続化・フラッシュ
            $this->entityManager->persist($product);
            $this->entityManager->persist($productClass);
            $this->entityManager->persist($productStock);
            $this->entityManager->flush();

            $connection->commit();

            $this->logger->info('[MPBC] Created custom product', [
                'product_id' => $product->getId(),
                'product_class_id' => $productClass->getId(),
                'price' => $price,
            ]);

        } catch (\Exception $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->logger->error('[MPBC] Failed to create product, transaction rolled back', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // トランザクションコミット後にDB値を同期
        $this->entityManager->refresh($product);
        $this->entityManager->refresh($productClass);

        // カートに追加
        $this->cartService->addProduct($productClass, 1);

        $Cart = $this->cartService->getCart();
        if ($Cart) {
            $flowResult = $this->cartPurchaseFlow->validate($Cart, new PurchaseContext());
            if ($flowResult->hasError()) {
                foreach ($flowResult->getErrors() as $error) {
                    $this->logger->warning('[MPBC] Purchase flow validation error', [
                        'message' => $error->getMessage(),
                    ]);
                }
            } else {
                $this->cartPurchaseFlow->commit($Cart, new PurchaseContext());
            }
            $this->cartService->save();
        }

        $this->logger->info('[MPBC] Product added to cart successfully');

        return true;
    }

    /**
     * カート内の無効なProductClassを持つアイテムを削除する
     */
    private function cleanupInvalidCartItems(): void
    {
        try {
            $Cart = $this->cartService->getCart();
            if (!$Cart) {
                return;
            }

            $itemsToRemove = [];
            foreach ($Cart->getCartItems() as $CartItem) {
                $ProductClass = $CartItem->getProductClass();

                if (!$ProductClass || !$ProductClass->getId()) {
                    $itemsToRemove[] = $CartItem;
                    continue;
                }

                try {
                    $dbProductClass = $this->entityManager->find(ProductClass::class, $ProductClass->getId());
                    if (!$dbProductClass) {
                        $itemsToRemove[] = $CartItem;
                        continue;
                    }

                    $Product = $dbProductClass->getProduct();
                    if (!$Product || !$Product->getId()) {
                        $itemsToRemove[] = $CartItem;
                        continue;
                    }

                    $dbProduct = $this->entityManager->find(Product::class, $Product->getId());
                    if (!$dbProduct) {
                        $itemsToRemove[] = $CartItem;
                    }
                } catch (\Exception $e) {
                    $itemsToRemove[] = $CartItem;
                    $this->logger->warning('[MPBC] Invalid cart item detected during cleanup', [
                        'product_class_id' => $ProductClass ? $ProductClass->getId() : null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($itemsToRemove)) {
                foreach ($itemsToRemove as $item) {
                    $Cart->removeCartItem($item);
                }
                $this->cartService->save();
                $this->logger->info('[MPBC] Cleaned up invalid cart items', [
                    'removed_count' => count($itemsToRemove),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[MPBC] Error during cart cleanup', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
