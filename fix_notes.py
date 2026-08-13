import re

path = 'generate_manual.py'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix all add_note_box calls that are missing 'doc' as first arg
content = re.sub(r'add_note_box\("', r'add_note_box(doc, "', content)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Fixed add_note_box calls")
for line in content.split('\n'):
    if 'add_note_box' in line and 'def add_note_box' not in line:
        print(repr(line.strip()))
