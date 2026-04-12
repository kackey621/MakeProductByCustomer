<?php

/**
 * PHPUnit テストブートストラップ
 *
 * EC-CUBEが未インストールのCI環境でもテストを実行できるよう、
 * スタブクラスをComposerオートローダーの前に読み込む。
 */

// EC-CUBEクラスのスタブを最初に読み込む（Composerより先に）
require_once __DIR__ . '/Stubs/EccubeStubs.php';

// Composerオートローダー
require_once __DIR__ . '/../vendor/autoload.php';
