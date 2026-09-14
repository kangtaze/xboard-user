#!/usr/bin/env python3
target = '/etc/supervisor/conf.d/supervisord.conf'
with open(target, 'r', encoding='utf-8') as f:
    content = f.read()

PROGRAMS = ['telegram-ai', 'admin-ai', 'kawu-ai']
new_lines = 'startsecs=5\nstartretries=10\nexitcodes=0\n'
old_pattern = 'numprocs=1\nstopwaitsecs=5\nstopsignal=SIGINT\nstopasgroup=true\nkillasgroup=true\npriority='
new_pattern = 'numprocs=1\n' + new_lines + 'stopwaitsecs=15\nstopsignal=SIGINT\nstopasgroup=true\nkillasgroup=true\npriority='

modified = 0
for prog in PROGRAMS:
    needle = f'[program:{prog}]'
    idx = content.find(needle)
    if idx < 0:
        print(f'FAIL [{prog}]: not found')
        continue
    next_section = content.find('[program:', idx + len(needle))
    section_end = next_section if next_section > 0 else len(content)
    section = content[idx:section_end]
    if 'startsecs=5' in section:
        print(f'SKIP [{prog}]: already patched')
        continue
    if old_pattern not in section:
        print(f'FAIL [{prog}]: pattern not in section')
        continue
    new_section = section.replace(old_pattern, new_pattern, 1)
    content = content[:idx] + new_section + content[section_end:]
    modified += 1
    print(f'OK [{prog}]: patched')

with open(target, 'w', encoding='utf-8') as f:
    f.write(content)
print(f'Total: {modified}/3')
print(f'Size: {len(content)} bytes')
