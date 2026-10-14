p = r'C:/xampp/htdocs/Store-System/modules/gaming/receipt.php'
lines = open(p,'r',encoding='utf-8').read().splitlines(keepends=True)
out=[]
for l in lines:
    if l.strip().startswith(": 'AT"):
        out.append("    : 'غير منتهية';\n")
    else:
        out.append(l)
open(p,'w',encoding='utf-8').write(''.join(out))
print('done')
