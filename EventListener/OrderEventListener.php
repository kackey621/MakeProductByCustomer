<?php

namespace Plugin\MPBC43\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEventListener implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::FRONT_SHOPPING_COMPLETE_INITIALIZE => 'onShoppingComplete',
        ];
    }

    /**
     * 注文完了時の処理
     * 
     * @param EventArgs $event
     */
    public function onShoppingComplete(EventArgs $event)
    {
        /** @var \Eccube\Entity\Order $Order */
        $Order = $event->getArgument('Order');
        
        if (!$Order) {
            return;
        }

        try {
            $this->logger->info('[MPBC] Order completed, checking for custom products', [
                'order_id' => $Order->getId()
            ]);

            // 注文内の商品をチェック
            foreach ($Order->getOrderItems() as $OrderItem) {
                $Product = $OrderItem->getProduct();
                
                if (!$Product) {
                    continue;
                }

                // カスタム商品（セッション情報を含む商品）かどうかチェック
                $description = $Product->getDescriptionDetail();
                if ($description && strpos($description, '[セッション:') !== false) {
                    $this->logger->info('[MPBC] Found custom product in order, marking as unavailable', [
                        'product_id' => $Product->getId(),
                        'product_name' => $Product->getName()
                    ]);

                    // 商品を購入不可能な状態に変更
                    $this->disableCustomProduct($Product);
                }
            }

            $this->entityManager->flush();

        } catch (\Exception $e) {
            $this->logger->error('[MPBC] Error in order completion handler', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * カスタム商品を無効化
     * 
     * @param \Eccube\Entity\Product $Product
     */
    private function disableCustomProduct(\Eccube\Entity\Product $Product)
    {
        // すべてのProductClassを非表示にして購入を不可能にする
        foreach ($Product->getProductClasses() as $ProductClass) {
            $ProductClass->setVisible(false);
            $ProductClass->setStockUnlimited(false);
            $ProductClass->setStock(0);
            $this->entityManager->persist($ProductClass);
        }

        // 商品名に購入済みマークを追加
        $currentName = $Product->getName();
        if (strpos($currentName, '[購入済み]') === false) {
            $Product->setName($currentName . ' [購入済み]');
            $Product->setDescriptionDetail($Product->getDescriptionDetail() . ' ※この商品は購入済みです。');
            $this->entityManager->persist($Product);
        }

        $this->logger->info('[MPBC] Custom product disabled after purchase', [
            'product_id' => $Product->getId(),
            'product_name' => $Product->getName()
        ]);
    }
}
