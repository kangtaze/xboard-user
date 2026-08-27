<?php
/**
 * VP92 Xboard PHPUnit bootstrap
 *
 * 在 vendor/autoload.php 之前 touch 测试 SQLite 文件
 * （Xboard config/database.php:41 强制 base_path() 包裹 DB_DATABASE，SQLiteConnector 要求物理文件预存在）
 *
 * 真实应用启动在 Laravel Testing\TestCase::setUp() 阶段由 phpunit.xml 的 bootstrap="vendor/autoload.php" 链式触发。
 */

$dbPath = dirname(__DIR__) . '/storage/framework/testing.sqlite';
if (!file_exists($dbPath)) {
    @mkdir(dirname($dbPath), 0775, true);
    @touch($dbPath);
    @chmod($dbPath, 0666);
}

require dirname(__DIR__) . '/vendor/autoload.php';