<?php

namespace Plugin\MPBC43\Twig;

use Eccube\Service\CartService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Psr\Log\LoggerInterface;

class CartIntegrityExtension extends AbstractExtension
{
    private $cartService;
    private $logger;

    public function __construct(CartService $cartService, LoggerInterface $logger)
    {
        $this->cartService = $cartService;
        $this->logger = $logger;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('mpbc_cart_integrity_check', [$this, 'checkCartIntegrity']),
        ];
    }

    public function checkCartIntegrity()
    {
        try {
            $carts = $this->cartService->getCarts();
            
            foreach ($carts as $cart) {
                foreach ($cart->getCartItems() as $cartItem) {
                    $productClass = $cartItem->getProductClass();
                    
                    // ProductClassまたはProductが存在しない場合は削除
                    if (!$productClass || !$productClass->getProduct()) {
                        $this->logger->warning('MPBCカート整合性チェック: 無効なカートアイテムを検出', [
                            'cart_item_id' => $cartItem->getId(),
                            'product_class_id' => $productClass ? $productClass->getId() : 'null'
                        ]);
                        return false; // テンプレートでの表示を停止
                    }
                }
            }
            
            return true; // 全て正常
        } catch (\Exception $e) {
            $this->logger->error('MPBCカート整合性チェックエラー', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
