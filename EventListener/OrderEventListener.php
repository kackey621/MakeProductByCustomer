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
     * 注文完了時の処理。
     * セッションマーカー付きカスタム商品を購入不可能な状態にする。
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

        $this->logger->info('[MPBC] Order completed, checking for custom products', [
            'order_id' => $Order->getId(),
        ]);

        foreach ($Order->getOrderItems() as $OrderItem) {
            $Product = $OrderItem->getProduct();

            if (!$Product) {
                continue;
            }

            $description = $Product->getDescriptionDetail();
            if (!$description || strpos($description, '[セッション:') === false) {
                continue;
            }

            $this->logger->info('[MPBC] Found custom product in order, marking as purchased', [
                'product_id' => $Product->getId(),
                'product_name' => $Product->getName(),
            ]);

            try {
                $this->disableCustomProduct($Product);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->logger->error('[MPBC] Failed to disable custom product after order', [
                    'product_id' => $Product->getId(),
                    'error' => $e->getMessage(),
                ]);
                // 他の商品の処理を継続する
            }
        }
    }

    /**
     * カスタム商品を購入済み状態にして再購入を防止する
     *
     * @param \Eccube\Entity\Product $Product
     */
    private function disableCustomProduct(\Eccube\Entity\Product $Product): void
    {
        foreach ($Product->getProductClasses() as $ProductClass) {
            $ProductClass->setVisible(false);
            $ProductClass->setStockUnlimited(false);
            $ProductClass->setStock(0);
            $this->entityManager->persist($ProductClass);
        }

        $currentName = $Product->getName();
        if (strpos($currentName, '[購入済み]') === false) {
            $Product->setName($currentName . ' [購入済み]');
            $Product->setDescriptionDetail($Product->getDescriptionDetail() . ' ※この商品は購入済みです。');
            $this->entityManager->persist($Product);
        }

        $this->logger->info('[MPBC] Custom product disabled after purchase', [
            'product_id' => $Product->getId(),
            'product_name' => $Product->getName(),
        ]);
    }
}
