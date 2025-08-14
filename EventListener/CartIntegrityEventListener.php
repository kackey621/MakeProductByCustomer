<?php

namespace Plugin\MPBC43\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Service\CartService;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * カート整合性チェック用EventListener
 */
class CartIntegrityEventListener implements EventSubscriberInterface
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
     * @var CartService
     */
    protected $cartService;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        CartService $cartService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->cartService = $cartService;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::FRONT_CART_INDEX_INITIALIZE => 'onCartIndex',
        ];
    }

    /**
     * カート表示時の整合性チェック
     * 
     * @param EventArgs $event
     */
    public function onCartIndex(EventArgs $event)
    {
        $this->cleanupInvalidCartItems();
    }

    /**
     * カート内の無効なアイテムをクリーンアップ
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
                
                // ProductClassが存在しないか、IDが無い場合
                if (!$ProductClass || !$ProductClass->getId()) {
                    $itemsToRemove[] = $CartItem;
                    continue;
                }

                try {
                    // ProductClassがデータベースに存在するかチェック
                    $dbProductClass = $this->entityManager->find(ProductClass::class, $ProductClass->getId());
                    if (!$dbProductClass) {
                        $itemsToRemove[] = $CartItem;
                        continue;
                    }
                    
                    // Productが存在するかチェック
                    $Product = $dbProductClass->getProduct();
                    if (!$Product || !$Product->getId()) {
                        $itemsToRemove[] = $CartItem;
                        continue;
                    }

                    // Productがデータベースに存在するかチェック
                    $dbProduct = $this->entityManager->find(Product::class, $Product->getId());
                    if (!$dbProduct) {
                        $itemsToRemove[] = $CartItem;
                        continue;
                    }
                    
                } catch (\Exception $e) {
                    // エンティティが見つからない場合は削除対象に追加
                    $itemsToRemove[] = $CartItem;
                    $this->logger->warning('[MPBC] Invalid cart item detected', [
                        'error' => $e->getMessage(),
                        'product_class_id' => $ProductClass ? $ProductClass->getId() : 'null'
                    ]);
                }
            }

            // 無効なアイテムを削除
            if (!empty($itemsToRemove)) {
                foreach ($itemsToRemove as $item) {
                    $Cart->removeCartItem($item);
                }
                
                $this->cartService->save();
                $this->logger->info('[MPBC] Cleaned up invalid cart items', [
                    'removed_count' => count($itemsToRemove)
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('[MPBC] Error in cart integrity check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
