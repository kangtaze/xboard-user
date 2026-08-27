<?php

namespace Tests\Feature;

use App\Services\AiChatService;
use ReflectionClass;
use Tests\TestCase;

/**
 * 用途: 验证 AiChatService 的 SYSTEM_PROMPT【最新版本动态】段含 2026-08-28 用户培训话术新增的产品卖点关键词 + 禁止 changelog 逐条复述规则
 * 调用方: phpunit 服务器端 TDD
 * 被调用方: AiChatService::SYSTEM_PROMPT (const)
 * 关联记录: v20.6 wakeup (2026-08-28) — 客户反馈 AI 把 changelog 原文翻成大白话(支付状态细节), 要求强制从产品卖点角度回答
 *
 * 测试逻辑 (RED→GREEN):
 * - RED: 当前 prompt 只有"说结果和感受"软规则, 没有"网络稳定/连接顺畅"硬关键词, 也没有"禁止复述"硬规则
 * - GREEN: 改 SYSTEM_PROMPT 加 4 个产品卖点硬关键词 + 1 条"禁止逐条复述"硬规则
 */
class AiChatServicePromptTest extends TestCase
{
    /**
     * 反射读 SYSTEM_PROMPT const 内容
     */
    private function getPrompt(): string
    {
        $reflection = new ReflectionClass(AiChatService::class);
        $const = $reflection->getReflectionConstant('SYSTEM_PROMPT');
        return $const->getValue();
    }

    /**
     * v20.6 RED→GREEN 核心断言:
     * SYSTEM_PROMPT【最新版本动态】段必须含产品卖点硬关键词,
     * 因为之前 prompt 只有软规则"说结果和感受", AI 还是把 changelog
     * 原文 (checkOrder 状态范围修复) 翻成大白话 (支付状态),
     * 用户反馈"应该是说功能上的优化啊, 突出网络稳定"
     */
    public function test_system_prompt_contains_network_stability_selling_points(): void
    {
        $prompt = $this->getPrompt();

        // 4 个核心卖点关键词必须全部出现
        $this->assertStringContainsString('网络更稳定', $prompt, 'SYSTEM_PROMPT 必须含"网络更稳定"卖点关键词');
        $this->assertStringContainsString('连接更顺畅', $prompt, 'SYSTEM_PROMPT 必须含"连接更顺畅"卖点关键词');
        $this->assertStringContainsString('速度', $prompt, 'SYSTEM_PROMPT 必须含"速度"卖点关键词');
        $this->assertStringContainsString('省心', $prompt, 'SYSTEM_PROMPT 必须含"省心"卖点关键词 (与既有"更省心"延续)');
    }

    /**
     * v20.6 RED→GREEN 第二断言:
     * 必须显式禁止"逐条复述 changelog"和"按原文顺序翻译",
     * 否则 AI 还是会按 changelog 原文 1:1 输出支付状态等条目
     */
    public function test_system_prompt_explicitly_bans_changelog_paraphrasing(): void
    {
        $prompt = $this->getPrompt();

        // 禁用规则必须有"禁止逐条"或等价表述
        $hasAntiParaphrase = str_contains($prompt, '禁止逐条复述')
            || str_contains($prompt, '不要逐条复述')
            || str_contains($prompt, '不要按 changelog 原文');

        $this->assertTrue(
            $hasAntiParaphrase,
            'SYSTEM_PROMPT 必须显式禁止逐条复述 changelog (之前 AI 把 checkOrder 状态修复翻成 3 条支付状态)'
        );

        // 必须有"主动调用产品卖点"的硬规则 (而非被动翻译)
        $this->assertStringContainsString('卖点', $prompt, 'SYSTEM_PROMPT 必须有"卖点"关键词, 让 AI 主动从产品角度包装');
    }

    /**
     * 回归保护: 既有"结尾轻引导一句"必须保留, 不能改丢
     */
    public function test_system_prompt_preserves_existing_closing_guide(): void
    {
        $prompt = $this->getPrompt();

        $this->assertStringContainsString(
            '用着顺手可以继续使用，想体验新版可到官网下载',
            $prompt,
            'SYSTEM_PROMPT【最新版本动态】结尾轻引导必须保留 (v15.4 已上线规则, 不能改丢)'
        );
    }
}
