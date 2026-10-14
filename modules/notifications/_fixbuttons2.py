import re
files = [
 r'C:/xampp/htdocs/Store-System/modules/gaming/receipt.php',
 r'C:/xampp/htdocs/Store-System/modules/billiards/receipt.php',
]
for p in files:
    b=open(p,'rb').read()
    b2=re.sub(rb'<button class="btn-print" onclick="window\.print\(\)">.*?</button>', b'<button class="btn-print" onclick="window.print()">\xd8\xb7\xd8\xa8\xd8\xa7\xd8\xb9\xd8\xa9</button>', b, flags=re.S)
    b2=re.sub(rb'<button class="btn-print" style="background:#475569;" onclick=".*?">.*?</button>', b'<button class="btn-print" style="background:#475569;" onclick="if(window.self===window.top){window.close();}else if(window.parent&&typeof window.parent.closeReceiptModal===\'function\'){window.parent.closeReceiptModal();}">\xd8\xa5\xd8\xba\xd9\x84\xd8\xa7\xd9\x82</button>', b2, flags=re.S)
    open(p,'wb').write(b2)
    print(p, 'changed' if b2!=b else 'nochange')
