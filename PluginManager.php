<?php

namespace Plugin\MPBC43;

use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Master\SaleType;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Plugin\AbstractPluginManager;
use Plugin\MPBC43\Entity\Config;
use Psr\Container\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    /**
     * プラグイン有効化時の処理
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function enable(array $meta, ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
       

        // Ensure Config exists
        $ConfigRepository = $entityManager->getRepository(Config::class);
        $Config = $ConfigRepository->findOneBy([]);
        if (!$Config) {
            $Config = new Config();
            $Config->setProductNameDisplayType('customer_input');
            $entityManager->persist($Config);
            $entityManager->flush($Config);
        } else {
            // 既存のConfigがあるが、product_name_display_typeが設定されていない場合のデフォルト値設定
            if ($Config->getProductNameDisplayType() === null) {
                $Config->setProductNameDisplayType('customer_input');
                $entityManager->persist($Config);
                $entityManager->flush($Config);
            }
        }
         
        // Ensure a hidden base product with a single class exists for MPBC
        $productRepo = $entityManager->getRepository(Product::class);
        $Product = $productRepo->findOneBy(['name' => 'MPB_Product']);
        if (!$Product) {
            $statusRepo = $entityManager->getRepository(ProductStatus::class);
            $saleTypeRepo = $entityManager->getRepository(SaleType::class);

            $Product = new Product();
            $Product->setName('MPB_Product');
            // Make it saleable/visible (1 is typically 表示)
            $ShowStatus = $statusRepo->find(1);
            if ($ShowStatus) {
                $Product->setStatus($ShowStatus);
            }
            // Minimal required class
            $ProductClass = new ProductClass();
            $ProductClass->setProduct($Product);
            $ProductClass->setCode('MPB-CLASS');
            $ProductClass->setStockUnlimited(true);
            $ProductClass->setPrice02(1); // base price (will be overridden at runtime)
            $ProductClass->setVisible(true);

            // Use default sale type (usually 1: 通常商品) on ProductClass
            $DefaultSaleType = $saleTypeRepo->find(1);
            if ($DefaultSaleType) {
                $ProductClass->setSaleType($DefaultSaleType);
            }

            $entityManager->persist($Product);
            $entityManager->persist($ProductClass);
            $entityManager->flush();
        }
    }

    /**
     * プラグイン無効化時の処理
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function disable(array $meta, ContainerInterface $container)
    {
        // 無効化時に必要な処理があればここに記述
    }

    /**
     * プラグインアンインストール時の処理
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function uninstall(array $meta, ContainerInterface $container)
    {
        // アンインストール時に必要な処理があればここに記述
    }

    /**
     * プラグインインストール時の処理
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function install(array $meta, ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        
        // データベーススキーマを更新
        $this->createSchema($entityManager);
    }
    
    /**
     * データベーススキーマ作成
     */
    private function createSchema($entityManager)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        // プラグイン固有のエンティティのメタデータのみを取得
        $configMetaData = $entityManager->getClassMetadata(Config::class);
        
        try {
            $tool->createSchema([$configMetaData]);
        } catch (\Exception $e) {
            // テーブルが既に存在する場合は無視
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }
}
