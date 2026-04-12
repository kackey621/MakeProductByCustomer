<?php

namespace Plugin\MPBC43\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Plugin\MPBC43\Entity\Config;

class ConfigTest extends TestCase
{
    public function testDefaultProductNameDisplayType(): void
    {
        $config = new Config();
        $this->assertSame('customer_input', $config->getProductNameDisplayType());
    }

    public function testSetGetProductNameDisplayType(): void
    {
        $config = new Config();
        $config->setProductNameDisplayType('predefined_name');
        $this->assertSame('predefined_name', $config->getProductNameDisplayType());
    }

    public function testFluentInterfaceForProductNameDisplayType(): void
    {
        $config = new Config();
        $result = $config->setProductNameDisplayType('customer_input');
        $this->assertSame($config, $result);
    }

    public function testGetIdReturnsNullByDefault(): void
    {
        $config = new Config();
        $this->assertNull($config->getId());
    }

    public function testPredefinedProductNameIsNullByDefault(): void
    {
        $config = new Config();
        $this->assertNull($config->getPredefinedProductName());
    }

    public function testSetGetPredefinedProductName(): void
    {
        $config = new Config();
        $config->setPredefinedProductName('テスト商品');
        $this->assertSame('テスト商品', $config->getPredefinedProductName());
    }

    public function testSetPredefinedProductNameToNull(): void
    {
        $config = new Config();
        $config->setPredefinedProductName('商品名');
        $config->setPredefinedProductName(null);
        $this->assertNull($config->getPredefinedProductName());
    }

    public function testPageLayoutIsNullByDefault(): void
    {
        $config = new Config();
        $this->assertNull($config->getPageLayout());
    }

    public function testSetGetPageLayout(): void
    {
        $config = new Config();
        $config->setPageLayout(2);
        $this->assertSame(2, $config->getPageLayout());
    }

    public function testPageTitleIsNullByDefault(): void
    {
        $config = new Config();
        $this->assertNull($config->getPageTitle());
    }

    public function testSetGetPageTitle(): void
    {
        $config = new Config();
        $config->setPageTitle('カスタムタイトル');
        $this->assertSame('カスタムタイトル', $config->getPageTitle());
    }

    public function testPageDescriptionIsNullByDefault(): void
    {
        $config = new Config();
        $this->assertNull($config->getPageDescription());
    }

    public function testSetGetPageDescription(): void
    {
        $config = new Config();
        $config->setPageDescription('詳細な説明文をここに記載します。');
        $this->assertSame('詳細な説明文をここに記載します。', $config->getPageDescription());
    }
}
