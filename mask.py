import os

base = 'd:/jj/vacation_system'
versions = ['vacation', 'vacation_design_mod', 'vacation_redesign']
files = [os.path.join(base, v, 'config', 'database.php') for v in versions]

# Mask passwords
for f in files:
    with open(f, 'r', encoding='utf-8') as file:
        content = file.read()
    content = content.replace("'jjblaid!@#'", "'*****'")
    with open(f, 'w', encoding='utf-8') as file:
        file.write(content)
