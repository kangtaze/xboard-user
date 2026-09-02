/**
 * 用途: invite.ts 单元测试 — 验证 4 个场景
 * 调用方: 本地 TDD（node --experimental-strip-types 直接跑）
 * 关联记录: 2026-09-02 invite_code 跨页面保持 Bug 修复（方案 B Step 7）
 */

import { setInviteCode, getInviteCode, clearInviteCode, isInviteValid } from '../src/utils/invite.ts'

// sessionStorage mock（Node 环境无 window）
const _store = new Map<string, string>()
;(globalThis as any).sessionStorage = {
  getItem: (k: string) => _store.get(k) ?? null,
  setItem: (k: string, v: string) => { _store.set(k, v) },
  removeItem: (k: string) => { _store.delete(k) },
  clear: () => _store.clear(),
}

// 简易断言
let pass = 0, fail = 0
function assertEq(actual: any, expected: any, label: string) {
  if (actual === expected) {
    console.log(`✅ PASS: ${label}`)
    pass++
  } else {
    console.error(`❌ FAIL: ${label}\n   expected: ${JSON.stringify(expected)}\n   actual:   ${JSON.stringify(actual)}`)
    fail++
  }
}

// 测试 1: set → get 往返
;(globalThis as any).sessionStorage.clear()
setInviteCode('ABC123')
assertEq(getInviteCode(), 'ABC123', 'set → get 往返')

// 测试 2: clear 后 get 返回空字符串
;(globalThis as any).sessionStorage.clear()
setInviteCode('XYZ')
clearInviteCode()
assertEq(getInviteCode(), '', 'clear 后 get 返回空字符串')

// 测试 3: XSS 注入被白名单拦截
;(globalThis as any).sessionStorage.clear()
setInviteCode('ABC";alert(1)//')
assertEq(getInviteCode(), '', 'XSS 注入 code "ABC";alert(1)//" 被白名单拦截')

// 测试 4: 长度超过 64 字符被拒绝
;(globalThis as any).sessionStorage.clear()
setInviteCode('A'.repeat(65))
assertEq(getInviteCode(), '', '长度超过 64 字符被拒绝')

// 测试 5: 空字符串被忽略
;(globalThis as any).sessionStorage.clear()
setInviteCode('')
assertEq(getInviteCode(), '', '空字符串被忽略')

// 测试 6: null/undefined 被忽略
;(globalThis as any).sessionStorage.clear()
setInviteCode(null as any)
setInviteCode(undefined as any)
assertEq(getInviteCode(), '', 'null/undefined 被忽略')

// 测试 7: isInviteValid 工具函数
assertEq(isInviteValid('ABC123'), true, 'isInviteValid("ABC123") = true')
assertEq(isInviteValid('abc-123_xyz'), true, 'isInviteValid 允许 - 和 _')
assertEq(isInviteValid('abc 123'), false, 'isInviteValid 拒绝空格')
assertEq(isInviteValid('abc;alert'), false, 'isInviteValid 拒绝分号')
assertEq(isInviteValid('A'.repeat(65)), false, 'isInviteValid 拒绝 >64')

console.log(`\n${pass} passed, ${fail} failed`)
process.exit(fail > 0 ? 1 : 0)
