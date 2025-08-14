<?php

namespace Plugin\MPBC43\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Product;
use Eccube\Entity\Layout;
use Eccube\Repository\LayoutRepository;
use Plugin\MPBC43\Entity\Config;

class ProductSafetyExtension extends AbstractExtension
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var LayoutRepository */
    private $layoutRepository;

    public function __construct(EntityManagerInterface $entityManager, LayoutRepository $layoutRepository)
    {
        $this->entityManager = $entityManager;
        $this->layoutRepository = $layoutRepository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mpbc_product_exists', [$this, 'productExists']),
            new TwigFunction('mpbc_safe_product_link', [$this, 'safeProductLink'], ['is_safe' => ['html']]),
            new TwigFunction('mpbc_safe_product_link_front', [$this, 'safeProductLinkFront'], ['is_safe' => ['html']]),
            new TwigFunction('mpbc_get_product_name_display_type', [$this, 'getProductNameDisplayType']),
            new TwigFunction('mpbc_get_predefined_product_name', [$this, 'getPredefinedProductName']),
            new TwigFunction('mpbc_get_page_layout', [$this, 'getPageLayout']),
            new TwigFunction('mpbc_get_page_title', [$this, 'getPageTitle']),
            new TwigFunction('mpbc_get_page_description', [$this, 'getPageDescription']),
        ];
    }

    /**
     * 商品が存在するかチェックする
     */
    public function productExists($productId): bool
    {
        if (!$productId) {
            return false;
        }

        try {
            $product = $this->entityManager->getRepository(Product::class)->find($productId);
            return $product !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 安全な商品リンクを生成する
     */
    public function safeProductLink($productId, $productName, $urlGenerator): string
    {
        if (!$this->productExists($productId)) {
            return '<span class="text-muted">' . htmlspecialchars($productName) . ' <small>(商品削除済み)</small></span>';
        }

        $url = $urlGenerator('admin_product_product_edit', ['id' => $productId]);
        return '<a href="' . $url . '" target="_blank">' . htmlspecialchars($productName) . '</a>';
    }

    /**
     * フロントエンド用の安全な商品リンクを生成する
     */
    public function safeProductLinkFront($productId, $productName, $urlGenerator): string
    {
        if (!$this->productExists($productId)) {
            return '<span class="text-muted">' . htmlspecialchars($productName) . ' <small>(商品削除済み)</small></span>';
        }

        $url = $urlGenerator('product_detail', ['id' => $productId]);
        return '<a href="' . $url . '">' . htmlspecialchars($productName) . '</a>';
    }

    /**
     * 商品名の表示方法設定を取得する
     */
    public function getProductNameDisplayType(): string
    {
        try {
            $config = $this->entityManager->getRepository(Config::class)->findOneBy([]);
            if ($config) {
                return $config->getProductNameDisplayType();
            }
            return 'customer_input'; // デフォルト値
        } catch (\Exception $e) {
            return 'customer_input'; // エラー時のデフォルト値
        }
    }

    /**
     * 店側設定の商品名を取得する
     */
    public function getPredefinedProductName(): string
    {
        try {
            $config = $this->entityManager->getRepository(Config::class)->findOneBy([]);
            if ($config && $config->getPredefinedProductName()) {
                return $config->getPredefinedProductName();
            }
            return 'MPB_Product'; // デフォルト値
        } catch (\Exception $e) {
            return 'MPB_Product'; // エラー時のデフォルト値
        }
    }

    /**
     * ページレイアウト設定を取得する
     */
    public function getPageLayout(): ?Layout
    {
        try {
            $config = $this->entityManager->getRepository(Config::class)->findOneBy([]);
            if ($config && $config->getPageLayout()) {
                return $this->layoutRepository->find($config->getPageLayout());
            }
            return null; // レイアウトが設定されていない場合
        } catch (\Exception $e) {
            return null; // エラー時はnull
        }
    }

    /**
     * ページタイトル設定を取得する
     */
    public function getPageTitle(): ?string
    {
        try {
            $config = $this->entityManager->getRepository(Config::class)->findOneBy([]);
            if ($config && $config->getPageTitle()) {
                return $config->getPageTitle();
            }
            return null; // カスタムタイトルがない場合
        } catch (\Exception $e) {
            return null; // エラー時はnull
        }
    }

    /**
     * ページ説明文設定を取得する
     */
    public function getPageDescription(): ?string
    {
        try {
            $config = $this->entityManager->getRepository(Config::class)->findOneBy([]);
            if ($config && $config->getPageDescription()) {
                return $config->getPageDescription();
            }
            return null; // 説明文がない場合
        } catch (\Exception $e) {
            return null; // エラー時はnull
        }
    }
}
