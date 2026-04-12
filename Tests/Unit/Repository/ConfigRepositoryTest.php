<?php

namespace Plugin\MPBC43\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Plugin\MPBC43\Entity\Config;

/**
 * ConfigRepositoryのテスト。
 * ConfigRepository::get()はDBを必要とするため、ここでは
 * Configエンティティのデフォルト値のみを検証する。
 */
class ConfigRepositoryTest extends TestCase
{
    public function testConfigEntityHasCorrectDefaultValues(): void
    {
        $config = new Config();
        $this->assertSame('customer_input', $config->getProductNameDisplayType());
        $this->assertNull($config->getPredefinedProductName());
        $this->assertNull($config->getPageLayout());
        $this->assertNull($config->getPageTitle());
        $this->assertNull($config->getPageDescription());
    }

    public function testConfigCanBeConfiguredForPredefinedName(): void
    {
        $config = new Config();
        $config->setProductNameDisplayType('predefined_name');
        $config->setPredefinedProductName('特別カスタム商品');
        $config->setPageLayout(2);
        $config->setPageTitle('ご注文フォーム');
        $config->setPageDescription('お好みの金額でご注文いただけます。');

        $this->assertSame('predefined_name', $config->getProductNameDisplayType());
        $this->assertSame('特別カスタム商品', $config->getPredefinedProductName());
        $this->assertSame(2, $config->getPageLayout());
        $this->assertSame('ご注文フォーム', $config->getPageTitle());
        $this->assertSame('お好みの金額でご注文いただけます。', $config->getPageDescription());
    }

    public function testConfigDisplayTypeCanBeSwitched(): void
    {
        $config = new Config();

        $config->setProductNameDisplayType('customer_input');
        $this->assertSame('customer_input', $config->getProductNameDisplayType());

        $config->setProductNameDisplayType('predefined_name');
        $this->assertSame('predefined_name', $config->getProductNameDisplayType());
    }
}
