-- Sample Data for Testing
-- نظام إدارة محل أجهزة منزلية

USE store_management;

-- Sample Products
INSERT INTO products (code, name, unit, price, wholesale_price, cost_price, stock_quantity, min_stock_level, description) VALUES
('MQ001', 'مقشات', 'قطعة', 120.00, 100.00, 80.00, 15, 5, 'مقشات تنظيف عالية الجودة'),
('SC001', 'صحون بلاستيك', 'طقم', 250.00, 220.00, 180.00, 8, 3, 'طقم صحون بلاستيك 12 قطعة'),
('GL001', 'أكواب زجاج', 'طقم', 180.00, 150.00, 120.00, 12, 5, 'طقم أكواب زجاج 6 قطع'),
('KN001', 'سكاكين مطبخ', 'قطعة', 95.00, 80.00, 60.00, 20, 10, 'سكاكين مطبخ استانلس'),
('PT001', 'حلل طبخ', 'طقم', 850.00, 750.00, 600.00, 5, 2, 'طقم حلل طبخ 7 قطع'),
('BK001', 'سلة غسيل', 'قطعة', 65.00, 55.00, 40.00, 25, 8, 'سلة غسيل بلاستيك كبيرة'),
('TR001', 'ترمس شاي', 'قطعة', 145.00, 125.00, 95.00, 10, 4, 'ترمس شاي استانلس 1 لتر'),
('CL001', 'منظف أرضيات', 'قطعة', 45.00, 38.00, 28.00, 30, 10, 'منظف أرضيات معطر'),
('SP001', 'إسفنجة تنظيف', 'عبوة', 25.00, 20.00, 15.00, 50, 15, 'عبوة إسفنج تنظيف 5 قطع'),
('TB001', 'طاولة كي', 'قطعة', 320.00, 280.00, 220.00, 4, 2, 'طاولة كي قابلة للطي');

-- Sample Suppliers
INSERT INTO suppliers (name, phone, visit_days, expected_products, balance) VALUES
('مورد الهرم', '01012345678', '["الأربعاء", "السبت"]', 'مقشات، صحون، أكواب', 0),
('مورد المنصورة', '01098765432', '["الأحد", "الخميس"]', 'حلل، سكاكين، أدوات مطبخ', 0),
('مورد القاهرة', '01155667788', '["الاثنين"]', 'منظفات، إسفنج، مواد تنظيف', 0);

-- Sample Customers
INSERT INTO customers (name, phone, address, balance) VALUES
('أحمد علي محمد', '01011112222', 'شارع الجمهورية، المنصورة', 0),
('فاطمة حسن', '01022223333', 'ميت غمر، الدقهلية', 0),
('محمود السيد', '01033334444', 'شارع البحر، المنصورة', 0),
('نورا إبراهيم', '01044445555', 'طلخا، الدقهلية', 0),
('خالد عبدالله', '01055556666', 'شارع الجيش، المنصورة', 0);

-- Sample Sales Invoice
INSERT INTO invoices (invoice_number, type, customer_id, date, total_amount, discount, paid_amount, remaining_amount, payment_method, payment_status, handled_by) VALUES
('INV-001', 'sale', 1, '2025-12-05', 240.00, 0, 240.00, 0, 'كاش', 'paid', 'المدير');

INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, unit, quantity, unit_price, total) VALUES
(1, 1, 'MQ001', 'مقشات', 'قطعة', 2, 120.00, 240.00);

-- Sample Purchase Invoice
INSERT INTO invoices (invoice_number, type, supplier_id, date, total_amount, discount, paid_amount, remaining_amount, payment_method, payment_status, handled_by) VALUES
('PUR-001', 'purchase', 1, '2025-12-04', 1600.00, 0, 1000.00, 600.00, 'كاش', 'partial', 'المدير');

INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, unit, quantity, unit_price, total) VALUES
(2, 1, 'MQ001', 'مقشات', 'قطعة', 20, 80.00, 1600.00);

-- Sample Installment
INSERT INTO installments (invoice_id, customer_id, total_amount, down_payment, remaining_amount, num_installments, installment_amount, status) VALUES
(1, 1, 240.00, 40.00, 200.00, 4, 50.00, 'active');

INSERT INTO installment_payments (installment_id, payment_number, due_date, amount, paid_amount, paid_date, status) VALUES
(1, 1, '2025-12-06', 50.00, 50.00, '2025-12-06', 'paid'),
(1, 2, '2025-12-13', 50.00, 0, NULL, 'pending'),
(1, 3, '2025-12-20', 50.00, 0, NULL, 'pending'),
(1, 4, '2025-12-27', 50.00, 0, NULL, 'pending');

-- Sample Activity Log
INSERT INTO activity_log (action, description, user) VALUES
('إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-001', 'المدير'),
('إنشاء فاتورة', 'تم إنشاء فاتورة شراء رقم PUR-001', 'المدير'),
('إضافة منتج', 'تم إضافة منتج: مقشات', 'المدير');
