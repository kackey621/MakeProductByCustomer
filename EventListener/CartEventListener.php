<?php

namespace Plugin\MPBC43\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Service\CartService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CartEventListener implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var CartService
     */
    protected $cartService;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        RequestStack $requestStack,
        CartService $cartService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->cartService = $cartService;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::FRONT_CART_ADD_COMPLETE => 'onCartAdd',
            EccubeEvents::FRONT_CART_INDEX_INITIALIZE => 'onCartIndex',
        ];
    }

    /**
     * カート追加時の処理
     *
     * @param EventArgs $event
     */
    public function onCartAdd(EventArgs $event)
    {
        $this->validateCartProducts();
    }

    /**
     * カート表示時の処理
     *
     * @param EventArgs $event
     */
    public function onCartIndex(EventArgs $event)
    {
        $this->validateCartProducts();
    }

    /**
     * カート内の商品を検証し、不正な商品を削除する。
     * セッション不一致の商品、購入済み商品を除去する。
     */
    private function validateCartProducts(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $Cart = $this->cartService->getCart();
        if (!$Cart) {
            return;
        }

        $currentSessionId = $request->getSession()->getId();
        $currentSessionPrefix = substr($currentSessionId, 0, 8);

        $hasRemovedItems = false;

        foreach ($Cart->getCartItems() as $CartItem) {
            $ProductClass = $CartItem->getProductClass();
            if (!$ProductClass) {
                continue;
            }

            // エンティティの遅延ロードで EntityNotFoundException が発生する可能性があるため
            // EntityManagerから直接取得して安全にチェックする
            $Product = null;
            try {
                $productId = null;
                if ($ProductClass->getProduct()) {
                    $productId = $ProductClass->getProduct()->getId();
                }
                if ($productId) {
                    $Product = $this->entityManager->find(Product::class, $productId);
                }
            } catch (\Exception $e) {
                $this->logger->warning('[MPBC] Could not load product for cart validation', [
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (!$Product) {
                continue;
            }

            // 購入済み商品チェック
            if (strpos($Product->getName(), '[購入済み]') !== false) {
                $this->logger->info('[MPBC] Removing already purchased product from cart', [
                    'product_id' => $Product->getId(),
                    'product_name' => $Product->getName(),
                ]);
                try {
                    $Cart->removeCartItem($CartItem);
                    $hasRemovedItems = true;
                } catch (\Exception $e) {
                    $this->logger->warning('[MPBC] Failed to remove purchased product from cart', [
                        'error' => $e->getMessage(),
                    ]);
                }
                continue;
            }

            // カスタム商品のセッション検証
            $description = $Product->getDescriptionDetail();
            if ($description && strpos($description, '[セッション:') !== false) {
                if (preg_match('/\[セッション:\s*([^\]]+)\]/', $description, $matches)) {
                    $productSessionPrefix = trim($matches[1]);

                    if ($productSessionPrefix !== $currentSessionPrefix) {
                        $this->logger->info('[MPBC] Removing unauthorized custom product from cart', [
                            'product_id' => $Product->getId(),
                            'product_session' => $productSessionPrefix,
                            'current_session' => $currentSessionPrefix,
                        ]);
                        try {
                            $Cart->removeCartItem($CartItem);
                            $hasRemovedItems = true;
                        } catch (\Exception $e) {
                            $this->logger->warning('[MPBC] Failed to remove unauthorized product from cart', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }

        if ($hasRemovedItems) {
            $this->cartService->save();
            $this->logger->info('[MPBC] Cart cleaned up, unauthorized products removed');
        }
    }
}
