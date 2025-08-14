<?php

namespace Plugin\MPBC43;

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TwigBlock implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'admin/Order/edit.twig' => 'onAdminOrderEdit',
        ];
    }

    /**
     * 受注管理画面で商品リンクエラーを回避するための処理
     */
    public function onAdminOrderEdit(TemplateEvent $event)
    {
        $source = $event->getSource();
        
        // 最も直接的で確実な方法：商品リンクの正規表現置換
        $linkPattern = '/<a href="{{ url\(\'admin_product_product_edit\', \{id: OrderItem\.ProductClass\.Product\.id\}\) }}" target="_blank">\s*{{ OrderItem\.product_name }}\s*<\/a>/';
        
        $linkReplacement = '{% if OrderItem.ProductClass and OrderItem.ProductClass.Product and OrderItem.ProductClass.Product.id %}
                                                                <a href="{{ url(\'admin_product_product_edit\', {id: OrderItem.ProductClass.Product.id}) }}" target="_blank">
                                                                    {{ OrderItem.product_name }}
                                                                </a>
                                                            {% else %}
                                                                <span class="text-muted">{{ OrderItem.product_name }} <small>(商品削除済み)</small></span>
                                                            {% endif %}';
        
        $source = preg_replace($linkPattern, $linkReplacement, $source);
        
        $event->setSource($source);
    }

    public function addNaviLink(TemplateEvent $event)
    {
        // no-op
    }
}