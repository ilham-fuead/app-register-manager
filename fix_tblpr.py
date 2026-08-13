import re

path = 'generate_manual.py'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix the tblPr access in add_code_block
old = "    tblPr = table._tbl.get_or_add_tblPr()"
new = "    tblPr = table._tbl.tblPr  # auto-creates if missing"
content = content.replace(old, new)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Fixed tblPr access in add_code_block")
