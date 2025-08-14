<?php

namespace Plugin\MPBC43\EventListener;

use Doctrine\ORM\EntityManagerInterface;
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
            // CartEventListenerを一時的に無効化してEntityNotFoundExceptionを回避
            // EccubeEvents::FRONT_CART_ADD_COMPLETE => 'onCartAdd',
            // EccubeEvents::FRONT_CART_INDEX_INITIALIZE => 'onCartIndex',
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
     * カート内の商品を検証し、不正な商品を削除
     */
    private function validateCartProducts()
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $currentSessionId = $request->getSession()->getId();
        $Cart = $this->cartService->getCart();

        if (!$Cart) {
            return;
        }

        error_log('[MPBC] Cart validation started. Current session: ' . substr($currentSessionId, 0, 8));
        error_log('[MPBC] Cart has ' . count($Cart->getCartItems()) . ' items');

        try {
            $hasRemovedItems = false;

            foreach ($Cart->getCartItems() as $CartItem) {
                $ProductClass = $CartItem->getProductClass();
                if (!$ProductClass) {
                    error_log('[MPBC] Cart item has no ProductClass - skipping');
                    continue;
                }

                $Product = $ProductClass->getProduct();
                if (!$Product) {
                    error_log('[MPBC] ProductClass has no Product - skipping');
                    continue;
                }

                error_log('[MPBC] Validating product: ' . $Product->getName() . ' (ID: ' . $Product->getId() . ')');

                // カスタム商品かどうかチェック
                $description = $Product->getDescriptionDetail();
                if ($description && strpos($description, '[セッション:') !== false) {
                    // セッション情報を抽出
                    if (preg_match('/\[セッション:\s*([^\]]+)\]/', $description, $matches)) {
                        $productSessionId = $matches[1];
                        $currentSessionPrefix = substr($currentSessionId, 0, 8);

                        error_log('[MPBC] Custom product session check: product=' . $productSessionId . ', current=' . $currentSessionPrefix);

                        // セッションが一致しない場合は削除
                        if ($productSessionId !== $currentSessionPrefix) {
                            error_log('[MPBC] Session mismatch - removing product from cart');
                            $this->logger->info('[MPBC] Removing unauthorized custom product from cart', [
                                'product_id' => $Product->getId(),
                                'product_name' => $Product->getName(),
                                'product_session' => $productSessionId,
                                'current_session' => $currentSessionPrefix
                            ]);

                            // カートアイテムを安全に削除
                            try {
                                $Cart->removeCartItem($CartItem);
                                $hasRemovedItems = true;
                            } catch (\Exception $e) {
                                error_log('[MPBC] Error removing cart item: ' . $e->getMessage());
                            }
                        } else {
                            error_log('[MPBC] Session matches - keeping product');
                        }
                    }
                } else {
                    error_log('[MPBC] Not a custom product - keeping');
                }

                // 購入済み商品かどうかチェック
                if (strpos($Product->getName(), '[購入済み]') !== false) {
                    error_log('[MPBC] Product already purchased - removing');
                    $this->logger->info('[MPBC] Removing already purchased product from cart', [
                        'product_id' => $Product->getId(),
                        'product_name' => $Product->getName()
                    ]);

                    // カートアイテムを安全に削除
                    try {
                        $Cart->removeCartItem($CartItem);
                        $hasRemovedItems = true;
                    } catch (\Exception $e) {
                        error_log('[MPBC] Error removing purchased cart item: ' . $e->getMessage());
                    }
                }
            }

            if ($hasRemovedItems) {
                $this->cartService->save();
                error_log('[MPBC] Cart cleaned up, unauthorized products removed');
                $this->logger->info('[MPBC] Cart cleaned up, unauthorized products removed');
            } else {
                error_log('[MPBC] Cart validation completed - no items removed');
            }

        } catch (\Exception $e) {
            error_log('[MPBC] Cart validation error: ' . $e->getMessage());
            $this->logger->error('[MPBC] Error validating cart products', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
