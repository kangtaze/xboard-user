import subprocess, sys

path = '/www/resources/views/admin/cf-worker.blade.php'

ssh_cmd = ['ssh', '-i', 'C:/Users/Administrator/.ssh/id_ed25519',
           'root@154.3.38.212', 'python3 -c']

# Read current file from server
result = subprocess.run(
    ['ssh', '-i', 'C:/Users/Administrator/.ssh/id_ed25519',
     'root@154.3.38.212', 'docker exec xboard cat /www/resources/views/admin/cf-worker.blade.php'],
    capture_output=True, text=True
)
content = result.stdout

old_block = """                            <div class="flex items-center gap-1.5 mt-1">
                                <template x-for="gid in (worker.group_ids || [])" :key="gid">
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-brand-500/20 text-brand-300"
                                          x-text="'VIP' + (gid - 1)"></span>
                                </template>
                                <span x-show="!worker.group_ids || worker.group_ids.length === 0"
                                      class="text-xs px-1.5 py-0.5 rounded bg-red-500/20 text-red-400">未分配用户组</span>
                            </div>"""

new_block = """                            <div class="flex items-center gap-2 mt-1" x-show="worker.plan_name || worker.group_name">
                                <span x-show="worker.plan_name" class="text-xs px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300"
                                      x-text="'套餐: ' + worker.plan_name"></span>
                                <span x-show="worker.group_name" class="text-xs px-1.5 py-0.5 rounded bg-brand-500/20 text-brand-300"
                                      x-text="'权限组: ' + worker.group_name"></span>
                            </div>
                            <div x-show="!worker.plan_name && !worker.group_name" class="mt-1">
                                <span class="text-xs px-1.5 py-0.5 rounded bg-red-500/20 text-red-400">未分配用户组</span>
                            </div>"""

if old_block in content:
    content = content.replace(old_block, new_block)
    # Pipe the result back to the container
    proc = subprocess.run(
        ['ssh', '-i', 'C:/Users/Administrator/.ssh/id_ed25519',
         'root@154.3.38.212', 'docker exec -i xboard tee /www/resources/views/admin/cf-worker.blade.php > /dev/null'],
        input=content.encode(), capture_output=True, text=True
    )
    if proc.returncode == 0:
        print('OK: Blade template updated')
    else:
        print(f'ERROR: {proc.stderr}')
else:
    print('ERROR: Old block not found in template')
