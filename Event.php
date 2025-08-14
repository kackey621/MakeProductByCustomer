<?php

namespace Plugin\MPBC43;

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class Event implements EventSubscriberInterface
{
    /** @var LoggerInterface|null */
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }
    public static function getSubscribedEvents(): array
    {
        return [
            '@default/Block/category_nav_pc.twig' => 'addNaviLink',
            'admin/Order/edit.twig' => 'onAdminOrderEdit',
            'Mypage/history.twig' => 'onMypageHistory',
        ];
    }

    public function addNaviLink(TemplateEvent $event)
    {
        $source = $event->getSource();
        $search = '<ul class="ec-itemNav__nav">';
        $replace = '<ul class="ec-itemNav__nav">'."\n".'              <li><a href="{{ url(\'mpb_product_entry\') }}">オリジナル商品</a></li>';
        $source = str_replace($search, $replace, $source);
        $event->setSource($source);
    }

    /**
     * 受注管理画面で商品リンクエラーを回避するための処理
     */
    public function onAdminOrderEdit(TemplateEvent $event)
    {
        $source = $event->getSource();
        
        // 注文者情報のヘッダー表示を修正
        $this->fixOrdererDisplayLayout($source);
        
        // より直接的で確実な方法：実際のHTMLパターンに基づく置換
        // 複数のパターンで商品リンクを安全なものに置換
        
        // パターン1: 基本的な商品リンクパターン
        $pattern1 = '<a href="{{ url(\'admin_product_product_edit\', {id: OrderItem.ProductClass.Product.id}) }}" target="_blank">
                                                                {{ OrderItem.product_name }}
                                                            </a>';
        
        $replacement1 = '{{ mpbc_safe_product_link(OrderItem.ProductClass.Product.id, OrderItem.product_name, url) }}';
        
        $source = str_replace($pattern1, $replacement1, $source);
        
        // パターン2: 正規表現でより柔軟にマッチ
        $regexPattern = '/<a\s+href="{{ url\(\'admin_product_product_edit\',\s*\{id:\s*OrderItem\.ProductClass\.Product\.id\}\)\s*}}"\s+target="_blank">\s*{{\s*OrderItem\.product_name\s*}}\s*<\/a>/';
        
        $source = preg_replace($regexPattern, $replacement1, $source);
        
        // パターン3: シンプルなパターンも試行
        $simplePattern = '/<a href="{{ url\(\'admin_product_product_edit\', \{id: OrderItem\.ProductClass\.Product\.id\}\) }}" target="_blank">\s*{{ OrderItem\.product_name }}\s*<\/a>/';
        
        $source = preg_replace($simplePattern, $replacement1, $source);
        
        $event->setSource($source);
    }

    /**
     * マイページの購入履歴画面で商品リンクエラーを回避するための処理
     */
    public function onMypageHistory(TemplateEvent $event)
    {
        $source = $event->getSource();
        
        // マイページの購入履歴画面での商品リンクを安全なものに置換
        // 商品が存在しない場合は商品名のみ表示
        
        // パターン1: 基本的な商品リンクパターン
        $pattern1 = '<a href="{{ url(\'product_detail\', {id: OrderItem.ProductClass.Product.id}) }}">{{ OrderItem.product_name }}</a>';
        $replacement1 = '{{ mpbc_safe_product_link_front(OrderItem.ProductClass.Product.id, OrderItem.product_name, url) }}';
        
        $source = str_replace($pattern1, $replacement1, $source);
        
        // パターン2: より複雑なHTMLが含まれるパターン
        $pattern2 = '/<a\s+href="{{ url\(\'product_detail\',\s*\{id:\s*OrderItem\.ProductClass\.Product\.id\}\)\s*}}"[^>]*>\s*{{\s*OrderItem\.product_name\s*}}\s*<\/a>/';
        $source = preg_replace($pattern2, $replacement1, $source);
        
        // パターン3: 複数行にわたるパターン
        $pattern3 = '/<a\s+href="{{ url\(\'product_detail\',\s*\{id:\s*OrderItem\.ProductClass\.Product\.id\}\)\s*}}"\s*>\s*{{\s*OrderItem\.product_name\s*}}\s*<\/a>/s';
        $source = preg_replace($pattern3, $replacement1, $source);
        
        $event->setSource($source);
    }

    

    // Note: event hooks for cart/order conversion are not used in this setup.
}