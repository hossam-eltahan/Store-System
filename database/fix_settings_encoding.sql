-- Fix settings Arabic UTF-8 text
SET NAMES utf8mb4;
UPDATE `settings` SET `setting_value` = 'كافيه عروسة النيل' WHERE `setting_key` = 'store_name';
UPDATE `settings` SET `setting_value` = 'مصر' WHERE `setting_key` = 'store_address';
UPDATE `settings` SET `setting_value` = 'ج.م' WHERE `setting_key` = 'currency';
UPDATE `settings` SET `setting_value` = 'المدير' WHERE `setting_key` = 'default_user';
UPDATE `settings` SET `setting_value` = 'أهلاً بكم في كافيه عروسة النيل' WHERE `setting_key` = 'receipt_header';
UPDATE `settings` SET `setting_value` = 'شكراً لزيارتكم - نتمنى لكم وقتاً ممتعاً' WHERE `setting_key` = 'receipt_footer';
