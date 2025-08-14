<?php

/*
 * Plugin Name: 商品名・価格自由設定プラグイン
 * Plugin Code: MPBC43
 * Version: 1.0.0
 * Description: 顧客が商品名と価格を自由に設定できるプラグインです。
 * Author: MPBC Development Team
 */

namespace Plugin\MPBC43;

class PluginInfo
{
    /**
     * プラグイン名を返します.
     *
     * @return string
     */
    public function getName()
    {
        return '商品名・価格自由設定プラグイン';
    }

    /**
     * プラグインコードを返します.
     *
     * @return string
     */
    public function getCode()
    {
        return 'MPBC43';
    }

    /**
     * プラグインのバージョンを返します.
     *
     * @return string
     */
    public function getVersion()
    {
        return '1.0.0';
    }

    /**
     * プラグインの説明を返します.
     *
     * @return string
     */
    public function getDescription()
    {
        return '顧客が商品名と価格を自由に設定できるプラグインです。';
    }

    /**
     * プラグインの作者を返します.
     *
     * @return string
     */
    public function getAuthor()
    {
        return 'MPBC Development Team';
    }
}
