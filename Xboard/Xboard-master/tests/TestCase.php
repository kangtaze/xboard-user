<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * VP92 Xboard 测试基类（Laravel 12 模式）
 *
 * 自动从 bootstrap/app.php 启动应用 — 不需要 CreatesApplication trait。
 *
 * 环境要求（phpunit.xml 自动设置）：
 *   - APP_ENV=testing
 *   - DB_CONNECTION=sqlite + DB_DATABASE=storage/framework/testing.sqlite
 *   - SQLiteConnector 要求物理文件存在 — setUpBeforeClass 自动 touch
 *
 * 注意：Xboard 的 config/database.php:41 强行 base_path() 包裹 DB_DATABASE，
 *      所以 :memory: 和绝对路径都会被破坏。必须用 storage/ 下相对路径。
 *
 * 历史：v19 完成时漏创建此文件，导致 server-only TDD 实际从未跑过任何测试。
 *      v20.1.1 RED 阶段发现并补建。
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Xboard 配置强制 base_path() 包裹，DB_DATABASE 必须是相对路径
        // SQLiteConnector 要求物理文件存在 — 第一次跑测试时 touch
        $dbPath = config('database.connections.sqlite.database');
        if ($dbPath && !str_starts_with($dbPath, ':memory:') && !file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0775, true);
            @touch($dbPath);
            @chmod($dbPath, 0666);
        }
    }
}
