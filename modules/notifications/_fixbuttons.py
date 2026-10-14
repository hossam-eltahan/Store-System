files = [r'C:/xampp/htdocs/Store-System/modules/gaming/receipt.php', r'C:/xampp/htdocs/Store-System/modules/billiards/receipt.php']
for p in files:
    s=open(p,'r',encoding='utf-8').read()
    s=s.replace(">PRINT<", ">طباعة<").replace(">CLOSE<", ">إغلاق<")
    s=s.replace('onclick="window.close()"', 'onclick="if(window.self===window.top){window.close();}else if(window.parent&&typeof window.parent.closeReceiptModal===\'function\'){window.parent.closeReceiptModal();}"')
    open(p,'w',encoding='utf-8').write(s)
    print(p,'done')
