<?php

namespace Plugin\MPBC43\EventListener;

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;

class OrderItemEventListener implements EventSubscriberInterface
{
    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'admin/Order/edit.twig' => 'onAdminOrderEdit',
        ];
    }

    /**
     * 受注管理画面で商品リンクエラーを回避するための処理
     * MPBCプラグインで作成された商品のリンクを無効化
     */
    public function onAdminOrderEdit(TemplateEvent $event)
    {
        $source = $event->getSource();
        
        // 最も確実な方法：商品リンクを完全に置換
        $patterns = [
            // HTMLで確認されたパターンをそのまま置換
            '/<a href="{{ url\(\'admin_product_product_edit\', \{id: OrderItem\.ProductClass\.Product\.id\}\) }}" target="_blank">\s*{{ OrderItem\.product_name }}\s*<\/a>/',
            // より緩いパターン
            '/<a[^>]*href="{{ url\(\'admin_product_product_edit\', \{id: OrderItem\.ProductClass\.Product\.id\}\)[^}]*}}"[^>]*>\s*{{ OrderItem\.product_name }}\s*<\/a>/',
        ];
        
        $replacement = '{{ mpbc_safe_product_link(OrderItem.ProductClass.Product.id, OrderItem.product_name, url) }}';
        
        foreach ($patterns as $pattern) {
            $source = preg_replace($pattern, $replacement, $source);
        }
        
        // 直接的な文字列置換も試行
        $directReplace = '<a href="{{ url(\'admin_product_product_edit\', {id: OrderItem.ProductClass.Product.id}) }}" target="_blank">
                                                                {{ OrderItem.product_name }}
                                                            </a>';
        
        if (strpos($source, $directReplace) !== false) {
            $source = str_replace($directReplace, $replacement, $source);
        }
        
        $event->setSource($source);
    }
}
