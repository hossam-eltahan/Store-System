-- Backup created: 2026-09-06 22:21:46
-- Database: store_management

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `user` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('1', NULL, 'ÏÑ┘åÏ┤ÏºÏí ┘üÏºÏ¬┘êÏ▒Ï®', 'Ï¬┘à ÏÑ┘åÏ┤ÏºÏí ┘üÏºÏ¬┘êÏ▒Ï® Ï¿┘èÏ╣ Ï▒┘é┘à INV-001', 'Ïº┘ä┘àÏ»┘èÏ▒', NULL, '2025-12-06 22:59:29');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('2', NULL, 'ÏÑ┘åÏ┤ÏºÏí ┘üÏºÏ¬┘êÏ▒Ï®', 'Ï¬┘à ÏÑ┘åÏ┤ÏºÏí ┘üÏºÏ¬┘êÏ▒Ï® Ï┤Ï▒ÏºÏí Ï▒┘é┘à PUR-001', 'Ïº┘ä┘àÏ»┘èÏ▒', NULL, '2025-12-06 22:59:29');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('3', NULL, 'ÏÑÏÂÏº┘üÏ® ┘à┘åÏ¬Ï¼', 'Ï¬┘à ÏÑÏÂÏº┘üÏ® ┘à┘åÏ¬Ï¼: ┘à┘éÏ┤ÏºÏ¬', 'Ïº┘ä┘àÏ»┘èÏ▒', NULL, '2025-12-06 22:59:29');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('4', NULL, 'إضافة منتج', 'تم إضافة المنتج: تلفزيون سامسونج (كود: TV001)', 'المدير', '::1', '2025-12-06 23:59:11');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('5', NULL, 'إضافة عميل', 'تم إضافة العميل: أحمد محمد', 'المدير', '::1', '2025-12-07 00:04:33');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('6', NULL, 'إضافة مورد', 'تم إضافة المورد: شركة الإلكترونيات', 'المدير', '::1', '2025-12-07 00:10:06');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('7', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00001', 'المدير', '::1', '2025-12-07 00:16:51');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('8', NULL, 'تعديل منتج', 'تم تعديل المنتج: تلفزيون سامسونج (كود: TV001)', 'المدير', '::1', '2025-12-07 00:25:11');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('9', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00002', 'المدير', '::1', '2025-12-11 01:21:07');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('10', NULL, 'تعديل منتج', 'تم تعديل المنتج: باسكت قمامة (كود: BK001)', 'المدير', '::1', '2025-12-11 12:10:51');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('11', NULL, 'إضافة منتج', 'تم إضافة المنتج: فنجان (كود: 0015)', 'المدير', '::1', '2025-12-11 12:14:15');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('12', NULL, 'تعديل منتج', 'تم تعديل المنتج: باسكت قمامة (كود: BK001)', 'المدير', '::1', '2025-12-11 12:16:09');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('13', NULL, 'إضافة منتج', 'تم إضافة المنتج: مقشه بلح (كود: PRD-00001)', 'المدير', '::1', '2025-12-11 12:26:17');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('14', NULL, 'حذف منتج', 'تم حذف المنتج: مقشه بلح', 'المدير', '::1', '2025-12-11 13:01:49');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('15', NULL, 'حذف منتج', 'تم حذف المنتج: فنجان', 'المدير', '::1', '2025-12-11 13:04:59');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('16', NULL, 'حذف منتج', 'تم حذف المنتج: طقم سكاكين', 'المدير', '::1', '2025-12-11 13:07:42');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('17', NULL, 'إضافة مورد', 'تم إضافة المورد: أحمد محمد محمد', 'المدير', '::1', '2025-12-11 13:11:51');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('18', NULL, 'تعديل مورد', 'تم تعديل بيانات المورد: أحمد محمد احمد', 'المدير', '::1', '2025-12-11 13:12:14');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('19', NULL, 'حذف مورد', 'تم حذف المورد: أحمد محمد احمد', 'المدير', '::1', '2025-12-11 13:36:07');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('20', NULL, 'تعديل مورد', 'تم تعديل بيانات المورد: شركة الإلكترونيات', 'المدير', '::1', '2025-12-11 13:37:11');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('21', NULL, 'إضافة عميل تلقائي', 'تم إضافة العميل تلقائياً: حسام من فاتورة بيع', 'المدير', '::1', '2025-12-11 17:21:53');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('22', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00003', 'المدير', '::1', '2025-12-11 17:21:53');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('23', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2025-12-11 17:27:27');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('24', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2025-12-11 17:28:29');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('25', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00004', 'المدير', '::1', '2025-12-11 17:41:25');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('26', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00005', 'المدير', '::1', '2025-12-11 17:44:39');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('27', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00001 بقيمة 145', 'المدير', '::1', '2025-12-11 20:04:16');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('28', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00006', 'المدير', '::1', '2025-12-11 20:17:33');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('29', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00002 بقيمة 65', 'المدير', '::1', '2025-12-11 20:19:38');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('30', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00007', 'المدير', '::1', '2025-12-11 20:36:19');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('31', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00003 بقيمة 40', 'المدير', '::1', '2025-12-11 20:39:24');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('32', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00001', 'المدير', '::1', '2025-12-11 21:08:12');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('33', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00002', 'المدير', '::1', '2025-12-11 21:48:44');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('34', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00008', 'المدير', '::1', '2025-12-11 21:50:40');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('35', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00009', 'المدير', '::1', '2025-12-11 21:52:34');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('36', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00010', 'المدير', '::1', '2025-12-11 21:58:14');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('37', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00011', 'المدير', '::1', '2025-12-11 22:00:08');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('38', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00012', 'المدير', '::1', '2025-12-11 22:07:25');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('39', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00003', 'المدير', '::1', '2025-12-11 22:09:01');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('40', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00004', 'المدير', '::1', '2025-12-11 22:23:52');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('41', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00005', 'المدير', '::1', '2025-12-11 22:29:24');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('42', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00004 بقيمة 65', 'المدير', '::1', '2025-12-11 23:09:11');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('43', NULL, 'إنشاء مرتجع مورد', 'تم إنشاء مرتجع للمورد رقم RET-00005 بقيمة 40', 'المدير', '::1', '2025-12-12 16:26:49');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('44', NULL, 'إنشاء مرتجع مورد', 'تم إنشاء مرتجع للمورد رقم RET-00006 بقيمة 40', 'المدير', '::1', '2025-12-12 16:43:16');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('45', NULL, 'إنشاء مرتجع مورد', 'تم إنشاء مرتجع للمورد رقم RET-00007 بقيمة 160', 'المدير', '::1', '2025-12-12 16:43:37');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('46', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00008 بقيمة 40', 'المدير', '::1', '2025-12-12 16:46:04');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('47', NULL, 'دفع مستحقات عميل', 'تم تسجيل دفعة من العميل أحمد محمد بمبلغ 5000', 'المدير', '::1', '2025-12-12 18:28:08');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('48', NULL, 'دفع مستحقات عميل', 'تم تسجيل دفعة من العميل أحمد محمد بمبلغ 5000', 'المدير', '::1', '2025-12-12 18:29:34');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('49', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00013', 'المدير', '::1', '2025-12-12 18:52:01');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('50', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2025-12-12 19:27:10');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('51', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00014', 'المدير', '::1', '2025-12-12 21:24:45');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('52', NULL, 'دفع مستحقات عميل', 'تم تسجيل دفعة من العميل حسام بمبلغ 1000', 'المدير', '::1', '2025-12-12 21:39:55');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('53', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00015', 'المدير', '::1', '2025-12-12 21:43:27');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('54', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00016', 'المدير', '::1', '2025-12-12 21:59:29');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('55', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00006', 'المدير', '::1', '2025-12-12 22:07:51');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('56', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00017', 'المدير', '::1', '2025-12-12 22:10:12');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('57', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00007', 'المدير', '::1', '2025-12-12 22:11:13');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('58', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00008', 'المدير', '::1', '2025-12-12 22:11:52');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('59', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00009', 'المدير', '::1', '2025-12-12 22:18:12');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('60', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00018', 'المدير', '::1', '2025-12-12 22:19:48');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('61', NULL, 'إنشاء خطة تقسيط', 'تم إنشاء خطة تقسيط لـالعميل حسام بمبلغ 200 على 4 قسط', 'المدير', '::1', '2025-12-12 22:20:25');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('62', NULL, 'إنشاء خطة تقسيط', 'تم إنشاء خطة تقسيط لـالعميل حسام بمبلغ 200 على 4 قسط', 'المدير', '::1', '2025-12-12 22:23:42');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('63', NULL, 'إنشاء خطة تقسيط', 'تم إنشاء خطة تقسيط لـالعميل حسام بمبلغ 200 على 4 قسط', 'المدير', '::1', '2025-12-12 22:24:06');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('64', NULL, 'دفع قسط', 'تم دفع 200 من أقساط العميل حسام', 'المدير', '::1', '2025-12-12 22:41:26');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('65', NULL, 'دفع قسط', 'تم دفع 50 من أقساط العميل حسام', 'المدير', '::1', '2025-12-12 22:45:48');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('66', NULL, 'دفع قسط', 'تم دفع 50 من أقساط العميل حسام', 'المدير', '::1', '2025-12-12 23:29:42');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('67', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00010', 'المدير', '::1', '2025-12-12 23:31:54');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('68', NULL, 'إنشاء خطة تقسيط', 'تم إنشاء خطة تقسيط لـالمورد شركة النور بمبلغ 1000 على 5 قسط', 'المدير', '::1', '2025-12-12 23:32:19');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('69', NULL, 'دفع قسط', 'تم دفع 200 من أقساط المورد شركة النور', 'المدير', '::1', '2025-12-12 23:32:50');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('70', NULL, 'دفع قسط', 'تم دفع 300 من أقساط المورد شركة النور', 'المدير', '::1', '2025-12-12 23:33:19');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('71', NULL, 'دفع مستحقات مورد', 'تم تسجيل دفعة للمورد شركة النور بمبلغ 300', 'المدير', '::1', '2025-12-12 23:35:52');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('72', NULL, 'تعديل أقساط', 'تم خصم 80 من خطة التقسيط #4 بسبب مرتجع', 'المدير', '::1', '2025-12-13 00:40:56');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('73', NULL, 'إنشاء مرتجع مورد', 'تم إنشاء مرتجع للمورد رقم RET-00009 بقيمة 80', 'المدير', '::1', '2025-12-13 00:40:56');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('74', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2025-12-13 02:53:41');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('75', NULL, 'إزالة اللوجو', 'تم إزالة شعار المحل', 'المدير', '::1', '2025-12-13 03:03:05');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('76', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2025-12-13 03:03:14');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('77', NULL, 'إزالة اللوجو', 'تم إزالة شعار المحل', 'المدير', '::1', '2025-12-13 03:03:18');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('78', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2025-12-13 03:03:25');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('79', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2025-12-13 03:06:04');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('80', NULL, 'نسخ احتياطي', 'تم إنشاء نسخة احتياطية: backup_2025-12-13_03-14-22.sql', 'المدير', '::1', '2025-12-13 03:14:22');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('81', NULL, 'نسخ احتياطي', 'تم إنشاء نسخة احتياطية: backup_2025-12-13_03-20-54.sql', 'المدير', '::1', '2025-12-13 03:20:54');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('82', NULL, 'نسخ احتياطي', 'تم إنشاء نسخة احتياطية: backup_2025-12-13_03-21-03.sql', 'المدير', '::1', '2025-12-13 03:21:03');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('83', NULL, 'حذف نسخة احتياطية', 'تم حذف النسخة: backup_2025-12-13_03-14-22.sql', 'المدير', '::1', '2025-12-13 03:21:17');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('84', NULL, 'حذف نسخة احتياطية', 'تم حذف النسخة: backup_2025-12-13_03-20-54.sql', 'المدير', '::1', '2025-12-13 03:21:20');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('85', NULL, 'نسخ احتياطي', 'تم إنشاء نسخة احتياطية: backup_2025-12-13_03-21-22.sql', 'المدير', '::1', '2025-12-13 03:21:22');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('86', NULL, 'نسخ احتياطي', 'تم إنشاء نسخة احتياطية: backup_2025-12-13_03-21-29.sql', 'المدير', '::1', '2025-12-13 03:21:29');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('87', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2026-01-06 16:50:54');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('88', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2026-01-06 16:52:08');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('89', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2026-04-20 16:21:42');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('90', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00019', 'المدير', '::1', '2026-09-01 23:21:03');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('91', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00011', 'المدير', '::1', '2026-09-01 23:21:45');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('92', NULL, 'دفع مستحقات عميل', 'تم تسجيل دفعة من العميل أحمد محمد بمبلغ 70', 'المدير', '::1', '2026-09-01 23:22:18');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('93', NULL, 'دفع مستحقات مورد', 'تم تسجيل دفعة للمورد مصنع الاتحاد بمبلغ 30', 'المدير', '::1', '2026-09-01 23:22:35');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('94', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00020', 'المدير', '::1', '2026-09-01 23:28:33');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('95', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00012', 'المدير', '::1', '2026-09-01 23:29:10');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('96', NULL, 'دفع مستحقات عميل', 'تم تسجيل دفعة من العميل حسام بمبلغ 45', 'المدير', '::1', '2026-09-01 23:29:25');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('97', NULL, 'دفع مستحقات مورد', 'تم تسجيل دفعة للمورد شركة الإلكترونيات بمبلغ 35', 'المدير', '::1', '2026-09-01 23:29:34');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('98', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00021', 'المدير', '::1', '2026-09-01 23:58:04');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('99', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00022', 'المدير', '::1', '2026-09-01 23:58:29');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('100', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00013', 'المدير', '::1', '2026-09-01 23:59:13');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('101', NULL, 'إنشاء فاتورة شراء', 'تم إنشاء فاتورة شراء رقم PUR-00014', 'المدير', '::1', '2026-09-01 23:59:52');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('102', NULL, 'دفع قسط', 'تم دفع 50 من أقساط العميل حسام', 'المدير', '::1', '2026-09-02 00:00:15');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('103', NULL, 'دفع قسط', 'تم دفع 50 من أقساط العميل حسام', 'المدير', '::1', '2026-09-02 00:01:25');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('104', NULL, 'دفع قسط', 'تم دفع 50 من أقساط العميل حسام', 'المدير', '::1', '2026-09-02 00:11:04');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('105', NULL, 'دفع قسط', 'تم دفع 50 من أقساط العميل حسام', 'المدير', '::1', '2026-09-02 00:12:47');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('106', NULL, 'إنشاء خطة تقسيط', 'تم إنشاء خطة تقسيط لـالعميل أحمد محمد بمبلغ 1000 على 5 قسط', 'المدير', '::1', '2026-09-02 00:15:03');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('107', NULL, 'دفع قسط', 'تم دفع 500 من أقساط العميل أحمد محمد', 'المدير', '::1', '2026-09-02 00:15:20');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('108', NULL, 'دفع قسط', 'تم دفع 400 من أقساط العميل أحمد محمد', 'المدير', '::1', '2026-09-02 00:20:58');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('109', NULL, 'دفع قسط', 'تم دفع 55 من أقساط العميل حسام', 'المدير', '::1', '2026-09-02 00:44:16');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('110', NULL, 'إنشاء خطة تقسيط', 'تم إنشاء خطة تقسيط لـالعميل أحمد محمد بمبلغ 2000 على 5 قسط', 'المدير', '::1', '2026-09-02 00:46:08');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('111', NULL, 'دفع قسط', 'تم دفع 500 من أقساط العميل أحمد محمد', 'المدير', '::1', '2026-09-02 00:46:25');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('112', NULL, 'دفع قسط', 'تم دفع 500 من أقساط العميل أحمد محمد', 'المدير', '::1', '2026-09-02 00:47:13');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('113', NULL, 'دفع قسط', 'تم دفع 300 من أقساط العميل أحمد محمد', 'المدير', '::1', '2026-09-02 00:55:27');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('114', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2026-09-02 01:35:02');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('115', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2026-09-02 01:35:13');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('116', NULL, 'تحديث الإعدادات', 'تم تحديث إعدادات النظام', 'المدير', '::1', '2026-09-02 01:35:21');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('117', NULL, 'تعديل فاتورة', 'تم تعديل فاتورة شراء رقم PUR-00014', 'المدير', '::1', '2026-09-02 01:38:46');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('118', NULL, 'تعديل فاتورة', 'تم تعديل فاتورة شراء رقم PUR-00014', 'المدير', '::1', '2026-09-02 01:40:20');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('119', NULL, 'تعديل فاتورة', 'تم تعديل فاتورة شراء رقم PUR-00014', 'المدير', '::1', '2026-09-02 02:01:36');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('120', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00010 بقيمة 65', 'المدير', '::1', '2026-09-02 03:08:33');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('121', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00011 بقيمة 65', 'المدير', '::1', '2026-09-02 03:08:58');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('122', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00012 بقيمة 65', 'المدير', '::1', '2026-09-02 03:10:05');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('123', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00013 بقيمة 130', 'المدير', '::1', '2026-09-02 03:24:56');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('124', NULL, 'تعديل أقساط', 'تم خصم 700.00 من خطة التقسيط #6 بسبب مرتجع', 'المدير', '::1', '2026-09-02 03:31:40');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('125', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00014 بقيمة 5000', 'المدير', '::1', '2026-09-02 03:31:40');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('126', NULL, 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00023', 'المدير', '::1', '2026-09-02 03:39:50');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('127', NULL, 'إنشاء مرتجع', 'تم إنشاء مرتجع رقم RET-00015 بقيمة 50000', 'المدير', '::1', '2026-09-02 03:41:52');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('128', NULL, 'دفع مستحقات عميل', 'تم تسجيل دفعة من العميل أحمد محمد بمبلغ 500', 'المدير', '::1', '2026-09-02 22:44:02');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('129', '1', 'تسجيل دخول', 'تسجيل دخول بنجاح', 'Ïº┘ä┘àÏ»┘èÏ▒', '::1', '2026-09-05 22:50:47');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('130', '1', 'تعديل الملف الشخصي', 'تم تعديل بيانات الملف الشخصي', 'حسام الطحان', '::1', '2026-09-05 22:51:58');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('131', '1', 'إضافة مستخدم', 'تم إضافة المستخدم: محمد احمد (mohamed_ahmed)', 'حسام الطحان', '::1', '2026-09-05 22:57:21');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('132', '1', 'تسجيل خروج', 'تسجيل خروج من النظام', 'حسام الطحان', '::1', '2026-09-05 22:58:08');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('133', '2', 'تسجيل دخول', 'تسجيل دخول بنجاح', 'محمد احمد', '::1', '2026-09-05 22:58:19');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('134', '2', 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00024', 'محمد احمد', '::1', '2026-09-05 23:29:47');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('135', '1', 'تسجيل دخول', 'تسجيل دخول بنجاح', 'حسام الطحان', '::1', '2026-09-06 03:12:59');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('136', '2', 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00025', 'محمد احمد', '::1', '2026-09-06 03:15:47');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('137', '2', 'دفع مستحقات عميل', 'تم تسجيل دفعة من العميل أحمد محمد بمبلغ 100', 'المدير', '::1', '2026-09-06 03:18:24');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('138', '1', 'تعطيل مستخدم', 'تم تعطيل المستخدم: محمد احمد', 'حسام الطحان', '::1', '2026-09-06 03:21:25');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('139', '1', 'تفعيل مستخدم', 'تم تفعيل المستخدم: محمد احمد', 'حسام الطحان', '::1', '2026-09-06 03:22:02');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('140', '2', 'تسجيل دخول', 'تسجيل دخول بنجاح', 'محمد احمد', '::1', '2026-09-06 03:22:09');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('141', '1', 'تعديل مستخدم', 'تم تعديل بيانات المستخدم: محمد احمد (mohamed_ahmed)', 'حسام الطحان', '::1', '2026-09-06 03:23:30');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('142', '1', 'تعديل مستخدم', 'تم تعديل بيانات المستخدم: محمد احمد (mohamed_ahmed)', 'حسام الطحان', '::1', '2026-09-06 03:41:09');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('143', '1', 'تعديل مستخدم', 'تم تعديل بيانات المستخدم: محمد احمد (mohamed_ahmed)', 'حسام الطحان', '::1', '2026-09-06 03:43:18');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('144', '1', 'تعديل مستخدم', 'تم تعديل بيانات المستخدم: محمد احمد (mohamed_ahmed)', 'حسام الطحان', '::1', '2026-09-06 04:00:21');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('145', '1', 'إضافة مخزن', 'تم إضافة مخزن جديد: مخزن فيصل (كود: WH-02)', 'حسام الطحان', '::1', '2026-09-06 05:42:30');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('146', '1', 'تحويل مخزون', 'تم تحويل TRF-00001 بعدد 1 صنف من [المخزن الرئيسي] إلى [مخزن فيصل]', 'حسام الطحان', '::1', '2026-09-06 05:43:51');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('147', '1', 'إنشاء فاتورة', 'تم إنشاء فاتورة بيع رقم INV-00026', 'حسام الطحان', '::1', '2026-09-06 05:45:23');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('148', '1', 'تعديل مستخدم', 'تم تعديل بيانات المستخدم: محمد احمد (mohamed_ahmed)', 'حسام الطحان', '::1', '2026-09-06 05:46:31');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('149', '1', 'تعديل مخزن', 'تم تعديل بيانات المخزن: مخزن فيصل (كود: WH-02)', 'حسام الطحان', '::1', '2026-09-06 05:49:44');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('150', '1', 'تعديل مخزن', 'تم تعديل بيانات المخزن: مخزن فيصل (كود: WH-02)', 'حسام الطحان', '::1', '2026-09-06 05:50:02');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('151', '1', 'تعديل مخزن', 'تم تعديل بيانات المخزن: المخزن الرئيسي (كود: WH-MAIN)', 'حسام الطحان', '::1', '2026-09-06 05:53:12');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('152', '1', 'تعديل مخزن', 'تم تعديل بيانات المخزن: مخزن فيصل (كود: WH-02)', 'حسام الطحان', '::1', '2026-09-06 05:53:22');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `user`, `ip_address`, `created_at`) VALUES ('153', '1', 'تعديل مستخدم', 'تم تعديل بيانات المستخدم: محمد احمد (mohamed_ahmed)', 'حسام الطحان', '::1', '2026-09-06 06:02:42');

DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `size` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `backups` (`id`, `filename`, `file_path`, `size`, `created_at`) VALUES ('3', 'backup_2025-12-13_03-21-03.sql', 'C:\\xampp\\htdocs\\سيستم اجهزه منزليه\\config/../backups/backup_2025-12-13_03-21-03.sql', '75343', '2025-12-13 03:21:03');
INSERT INTO `backups` (`id`, `filename`, `file_path`, `size`, `created_at`) VALUES ('4', 'backup_2025-12-13_03-21-22.sql', 'C:\\xampp\\htdocs\\سيستم اجهزه منزليه\\config/../backups/backup_2025-12-13_03-21-22.sql', '75842', '2025-12-13 03:21:22');
INSERT INTO `backups` (`id`, `filename`, `file_path`, `size`, `created_at`) VALUES ('5', 'backup_2025-12-13_03-21-29.sql', 'C:\\xampp\\htdocs\\سيستم اجهزه منزليه\\config/../backups/backup_2025-12-13_03-21-29.sql', '76363', '2025-12-13 03:21:29');

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00 COMMENT 'Ï▒ÏÁ┘èÏ» Ïº┘äÏ╣┘à┘è┘ä - Ï│Ïº┘äÏ¿ ┘èÏ╣┘å┘è ┘àÏ»┘è┘ê┘å ┘ä┘åÏº',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `balance`, `notes`, `created_at`, `updated_at`) VALUES ('1', 'أحمد محمد', '01222222222', 'القاهرة - شارع الهرم', '0.00', NULL, '2025-12-06 23:04:13', '2026-09-06 03:18:24');
INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `balance`, `notes`, `created_at`, `updated_at`) VALUES ('2', 'محمود علي', '01555555555', 'الجيزة - الدقي', '150.00', NULL, '2025-12-06 23:04:13', '2025-12-06 23:04:13');
INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `balance`, `notes`, `created_at`, `updated_at`) VALUES ('3', 'أحمد محمد', '01234567890', 'القاهرة', '-5000.00', '', '2025-12-07 00:04:33', '2026-09-02 22:44:02');
INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `balance`, `notes`, `created_at`, `updated_at`) VALUES ('4', 'حسام', '0123456789', NULL, '255.00', NULL, '2025-12-11 17:21:53', '2026-09-02 00:44:16');

DROP TABLE IF EXISTS `installment_payments`;
CREATE TABLE `installment_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `due_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `handled_by` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('pending','paid','overdue','partial') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `installment_payments_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `installment_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('1', '1', 'INS-00001-1', '1', '45.00', '0.00', '2025-12-12', '2026-09-01', NULL, NULL, '1', 'paid', ' - دفعة جزئية: 45', '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('2', '1', 'INS-00001-2', '2', '50.00', '0.00', '2026-01-12', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('3', '1', 'INS-00001-3', '3', '50.00', '0.00', '2026-02-12', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('4', '1', 'INS-00001-4', '4', '50.00', '0.00', '2026-03-12', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('5', '2', 'INS-00002-1', '1', '50.00', '0.00', '2025-12-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('6', '2', 'INS-00002-2', '2', '50.00', '0.00', '2026-01-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('7', '2', 'INS-00002-3', '3', '50.00', '0.00', '2026-02-12', '2026-09-02', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('8', '2', 'INS-00002-4', '4', '50.00', '0.00', '2026-03-12', '2026-09-02', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('9', '3', 'INS-00003-1', '1', '50.00', '0.00', '2025-12-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('10', '3', 'INS-00003-2', '2', '50.00', '0.00', '2026-01-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('11', '3', 'INS-00003-3', '3', '50.00', '0.00', '2026-02-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('12', '3', 'INS-00003-4', '4', '50.00', '0.00', '2026-03-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('13', '4', 'INS-00004-1', '1', '200.00', '0.00', '2025-12-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('14', '4', 'INS-00004-2', '2', '200.00', '0.00', '2026-01-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('15', '4', 'INS-00004-3', '3', '200.00', '0.00', '2026-02-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('16', '4', 'INS-00004-4', '4', '200.00', '0.00', '2026-03-12', '2025-12-12', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('17', '4', 'INS-00004-5', '5', '120.00', '0.00', '2026-04-12', '2026-09-02', NULL, NULL, '1', 'paid', NULL, '2025-12-12 23:54:26');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('18', '5', 'INS-00005-1', '1', '200.00', '0.00', '2026-09-02', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2026-09-02 00:15:03');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('19', '5', 'INS-00005-2', '2', '200.00', '0.00', '2026-10-02', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2026-09-02 00:15:03');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('20', '5', 'INS-00005-3', '3', '100.00', '100.00', '2026-11-02', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2026-09-02 00:15:03');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('21', '5', 'INS-00005-4', '4', '200.00', '0.00', '2026-12-02', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2026-09-02 00:15:03');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('22', '5', 'INS-00005-5', '5', '100.00', '100.00', '2027-01-02', NULL, NULL, NULL, '1', 'partial', ' - دفعة جزئية: 100', '2026-09-02 00:15:03');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('23', '6', 'INS-00006-1', '1', '400.00', '0.00', '2026-09-02', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2026-09-02 00:46:08');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('24', '6', 'INS-00006-2', '2', '300.00', '100.00', '2026-10-02', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2026-09-02 00:46:08');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('25', '6', 'INS-00006-3', '3', '200.00', '200.00', '2026-11-02', '2026-09-02', 'كاش', 'المدير', '1', 'paid', '', '2026-09-02 00:46:08');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('26', '6', 'INS-00006-4', '4', '300.00', '100.00', '2026-12-02', NULL, NULL, NULL, '1', 'partial', ' - دفعة جزئية: 100', '2026-09-02 00:46:08');
INSERT INTO `installment_payments` (`id`, `plan_id`, `payment_number`, `installment_number`, `amount`, `paid_amount`, `due_date`, `paid_date`, `payment_method`, `handled_by`, `user_id`, `status`, `notes`, `created_at`) VALUES ('27', '6', 'INS-00006-5', '5', '400.00', '0.00', '2027-01-02', '2026-09-02', NULL, NULL, '1', 'paid', ' - مرتجع', '2026-09-02 00:46:08');

DROP TABLE IF EXISTS `installment_plans`;
CREATE TABLE `installment_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('customer','supplier') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL COMMENT 'NULL for manual installments from old balance',
  `total_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `remaining_amount` decimal(12,2) NOT NULL,
  `number_of_installments` int(11) NOT NULL,
  `installment_amount` decimal(12,2) NOT NULL,
  `start_date` date NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_entity` (`entity_id`),
  KEY `idx_status` (`status`),
  KEY `idx_invoice` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `installment_plans` (`id`, `type`, `entity_id`, `entity_name`, `invoice_id`, `total_amount`, `paid_amount`, `remaining_amount`, `number_of_installments`, `installment_amount`, `start_date`, `status`, `notes`, `created_at`, `user_id`) VALUES ('1', 'customer', '4', 'حسام', NULL, '200.00', '200.00', '0.00', '4', '50.00', '2025-12-12', 'completed', '', '2025-12-12 22:20:25', '1');
INSERT INTO `installment_plans` (`id`, `type`, `entity_id`, `entity_name`, `invoice_id`, `total_amount`, `paid_amount`, `remaining_amount`, `number_of_installments`, `installment_amount`, `start_date`, `status`, `notes`, `created_at`, `user_id`) VALUES ('2', 'customer', '4', 'حسام', NULL, '200.00', '200.00', '0.00', '4', '50.00', '2025-12-12', 'completed', '', '2025-12-12 22:23:42', '1');
INSERT INTO `installment_plans` (`id`, `type`, `entity_id`, `entity_name`, `invoice_id`, `total_amount`, `paid_amount`, `remaining_amount`, `number_of_installments`, `installment_amount`, `start_date`, `status`, `notes`, `created_at`, `user_id`) VALUES ('3', 'customer', '4', 'حسام', NULL, '200.00', '200.00', '0.00', '4', '50.00', '2025-12-12', 'completed', '', '2025-12-12 22:24:06', '1');
INSERT INTO `installment_plans` (`id`, `type`, `entity_id`, `entity_name`, `invoice_id`, `total_amount`, `paid_amount`, `remaining_amount`, `number_of_installments`, `installment_amount`, `start_date`, `status`, `notes`, `created_at`, `user_id`) VALUES ('4', 'supplier', '1', 'شركة النور', NULL, '1000.00', '880.00', '120.00', '5', '200.00', '2025-12-12', 'active', '', '2025-12-12 23:32:19', '1');
INSERT INTO `installment_plans` (`id`, `type`, `entity_id`, `entity_name`, `invoice_id`, `total_amount`, `paid_amount`, `remaining_amount`, `number_of_installments`, `installment_amount`, `start_date`, `status`, `notes`, `created_at`, `user_id`) VALUES ('5', 'customer', '1', 'أحمد محمد', NULL, '1000.00', '1000.00', '0.00', '5', '200.00', '2026-09-02', 'completed', '', '2026-09-02 00:15:03', '1');
INSERT INTO `installment_plans` (`id`, `type`, `entity_id`, `entity_name`, `invoice_id`, `total_amount`, `paid_amount`, `remaining_amount`, `number_of_installments`, `installment_amount`, `start_date`, `status`, `notes`, `created_at`, `user_id`) VALUES ('6', 'customer', '3', 'أحمد محمد', NULL, '2000.00', '2000.00', '0.00', '5', '400.00', '2026-09-02', 'completed', '', '2026-09-02 00:46:08', '1');

DROP TABLE IF EXISTS `installments`;
CREATE TABLE `installments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `down_payment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(10,2) NOT NULL,
  `num_installments` int(11) NOT NULL,
  `installment_amount` decimal(10,2) NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `installments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `installments_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Ïº┘äÏ│Ï╣Ï▒ ┘ê┘éÏ¬ Ïº┘äÏ¿┘èÏ╣',
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('1', '1', '4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '11', '5000.00', '55000.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('2', '2', '4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '1', '5500.00', '5500.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('3', '2', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('4', '3', '4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '1', '5500.00', '5500.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('5', '4', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('6', '5', '2', 'BK001', 'باسكت قمامة', 'قطعة', '2', '65.00', '130.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('7', '5', '1', 'TR001', 'ترابيزة مكواة', 'قطعة', '1', '145.00', '145.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('8', '5', '4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '1', '5500.00', '5500.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('9', '6', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('10', '7', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '40.00', '40.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('11', '8', '2', 'BK001', 'باسكت قمامة', 'قطعة', '4', '40.00', '160.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('12', '9', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '45.00', '45.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('13', '10', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('14', '11', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('15', '12', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('16', '13', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('17', '14', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('18', '15', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '40.00', '40.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('19', '16', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '40.00', '40.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('20', '17', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '40.00', '40.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('21', '18', '4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '1', '5500.00', '5500.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('22', '19', '2', 'BK001', 'باسكت قمامة', 'قطعة', '20', '65.00', '1300.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('23', '20', '1', 'TR001', 'ترابيزة مكواة', 'قطعة', '1', '145.00', '145.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('24', '21', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('25', '22', '2', 'BK001', 'باسكت قمامة', 'قطعة', '50', '20.00', '1000.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('26', '23', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('27', '24', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '0.00', '0.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('28', '25', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '20.00', '20.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('29', '26', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '30.00', '30.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('30', '27', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('31', '28', '2', 'BK001', 'باسكت قمامة', 'قطعة', '20', '40.00', '800.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('32', '29', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('33', '30', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '30.00', '30.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('34', '31', '1', 'TR001', 'ترابيزة مكواة', 'قطعة', '1', '145.00', '145.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('35', '32', '1', 'TR001', 'ترابيزة مكواة', 'قطعة', '1', '500.00', '500.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('36', '33', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('37', '34', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('38', '35', '1', 'TR001', 'ترابيزة مكواة', 'قطعة', '1', '500.00', '500.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('42', '36', '1', 'BK001', 'باسكت قمامة', 'قطعة', '1', '60.00', '60.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('43', '37', '4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '1', '5500.00', '5500.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('44', '38', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('45', '39', '1', 'TR001', 'ترابيزة مكواة', 'قطعة', '1', '145.00', '145.00');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('48', '44', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `type` enum('sale','purchase') NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL COMMENT 'ÏºÏ│┘à Ïº┘äÏ╣┘à┘è┘ä ÏÑÏ░Ïº ┘âÏº┘å Ï║┘èÏ▒ ┘àÏ│Ï¼┘ä',
  `customer_phone` varchar(20) DEFAULT NULL COMMENT 'Ï▒┘é┘à Ïº┘äÏ╣┘à┘è┘ä ÏÑÏ░Ïº ┘âÏº┘å Ï║┘èÏ▒ ┘àÏ│Ï¼┘ä',
  `supplier_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `remaining_amount` decimal(10,2) DEFAULT 0.00,
  `old_balance` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL COMMENT '┘âÏºÏ┤Ïî Ïú┘éÏ│ÏºÏÀÏî Ïº┘åÏ│Ï¬ÏºÏ¿Ïº┘èÏî ┘ü┘êÏ»Ïº┘ü┘ê┘å ┘âÏºÏ┤Ïî ┘ü┘èÏ▓Ïº',
  `payment_status` enum('paid','partial','unpaid') DEFAULT 'unpaid',
  `handled_by` varchar(100) NOT NULL COMMENT '┘à┘å ┘éÏº┘à Ï¿Ïº┘äÏ¿┘èÏ╣/Ïº┘äÏºÏ│Ï¬┘äÏº┘à',
  `user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `warehouse_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`date`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `fk_invoice_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_invoice_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('1', 'INV-00001', 'sale', '3', 'أحمد محمد', '01234567890', NULL, '2025-12-07', '55000.00', '0.00', '50000.00', '5000.00', '0.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-07 00:16:51', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('2', 'INV-00002', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2025-12-11', '5565.00', '0.00', '0.00', '5565.00', '0.00', 'كاش', 'unpaid', 'المدير', '1', '', '2025-12-11 01:21:07', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('3', 'INV-00003', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-11', '5500.00', '0.00', '5500.00', '0.00', '0.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 17:21:53', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('4', 'INV-00004', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2025-12-11', '65.00', '0.00', '65.00', '0.00', '-5565.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 17:41:25', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('5', 'INV-00005', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-11', '5775.00', '0.00', '5770.00', '5.00', '0.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-11 17:44:39', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('6', 'INV-00006', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-11', '65.00', '0.00', '40.00', '25.00', '-5.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-11 20:17:33', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('7', 'INV-00007', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2025-12-11', '40.00', '0.00', '20.00', '20.00', '-5565.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-11 20:36:19', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('8', 'PUR-00001', 'purchase', NULL, 'شركة الإلكترونيات', '0111111111', '3', '2025-12-11', '160.00', '0.00', '100.00', '60.00', '0.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-11 21:08:12', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('9', 'PUR-00002', 'purchase', NULL, 'شركة الإلكترونيات', '0111111111', '3', '2025-12-11', '45.00', '0.00', '100.00', '-55.00', '60.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 21:48:44', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('10', 'INV-00008', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-11', '65.00', '0.00', '0.00', '65.00', '-30.00', 'كاش', 'unpaid', 'المدير', '1', '', '2025-12-11 21:50:40', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('11', 'INV-00009', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-11', '65.00', '0.00', '120.00', '-55.00', '-95.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 21:52:33', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('12', 'INV-00010', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-11', '65.00', '0.00', '120.00', '-55.00', '-40.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 21:58:14', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('13', 'INV-00011', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-11', '65.00', '0.00', '120.00', '-55.00', '15.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 22:00:08', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('14', 'INV-00012', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-11', '65.00', '0.00', '120.00', '-55.00', '70.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 22:07:25', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('15', 'PUR-00003', 'purchase', NULL, 'شركة الإلكترونيات', '0111111111', '3', '2025-12-11', '40.00', '0.00', '90.00', '-50.00', '5.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 22:09:01', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('16', 'PUR-00004', 'purchase', NULL, 'شركة الإلكترونيات', '0111111111', '3', '2025-12-11', '40.00', '0.00', '90.00', '-50.00', '-45.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-11 22:23:52', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('17', 'PUR-00005', 'purchase', NULL, 'مصنع الاتحاد', '01111111111', '2', '2025-12-11', '40.00', '0.00', '0.00', '40.00', '0.00', 'كاش', 'unpaid', 'المدير', '1', '', '2025-12-11 22:29:24', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('18', 'INV-00013', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2025-12-12', '5500.00', '0.00', '0.00', '5500.00', '-5585.00', 'كاش', 'unpaid', 'المدير', '1', '', '2025-12-12 18:52:01', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('19', 'INV-00014', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-12', '1300.00', '0.00', '300.00', '1000.00', '125.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-12 21:24:44', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('20', 'INV-00015', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-12', '145.00', '0.00', '0.00', '145.00', '-875.00', 'كاش', 'unpaid', 'المدير', '1', '', '2025-12-12 21:43:27', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('21', 'INV-00016', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-12', '65.00', '0.00', '10.00', '55.00', '-1020.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-12 21:59:29', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('22', 'PUR-00006', 'purchase', NULL, 'شركة النور', '01000000001', '1', '2025-12-12', '1000.00', '0.00', '500.00', '500.00', '0.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-12 22:07:51', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('23', 'INV-00017', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-12', '65.00', '0.00', '65.00', '0.00', '-1075.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-12 22:10:12', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('24', 'PUR-00007', 'purchase', NULL, 'شركة الإلكترونيات', '0111111111', '3', '2025-12-12', '0.00', '0.00', '0.00', '0.00', '-95.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-12 22:11:13', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('25', 'PUR-00008', 'purchase', NULL, 'شركة الإلكترونيات', '0111111111', '3', '2025-12-12', '20.00', '0.00', '20.00', '0.00', '-95.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-12 22:11:52', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('26', 'PUR-00009', 'purchase', NULL, 'شركة الإلكترونيات', '0111111111', '3', '2025-12-12', '30.00', '0.00', '5.00', '25.00', '-95.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-12 22:18:12', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('27', 'INV-00018', 'sale', '4', 'حسام', '0123456789', NULL, '2025-12-12', '65.00', '0.00', '65.00', '0.00', '-1075.00', 'كاش', 'paid', 'المدير', '1', '', '2025-12-12 22:19:48', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('28', 'PUR-00010', 'purchase', NULL, 'شركة النور', '01000000001', '1', '2025-12-12', '800.00', '0.00', '300.00', '500.00', '500.00', 'كاش', 'partial', 'المدير', '1', '', '2025-12-12 23:31:54', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('29', 'INV-00019', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2026-09-01', '65.00', '0.00', '0.00', '65.00', '-11085.00', 'كاش', 'unpaid', 'المدير', '1', '', '2026-09-01 23:21:03', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('30', 'PUR-00011', 'purchase', NULL, 'مصنع الاتحاد', '01111111111', '2', '2026-09-01', '30.00', '0.00', '0.00', '30.00', '40.00', 'كاش', 'unpaid', 'المدير', '1', '', '2026-09-01 23:21:45', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('31', 'INV-00020', 'sale', '4', 'حسام', '0123456789', NULL, '2026-09-01', '145.00', '0.00', '0.00', '145.00', '-1075.00', 'كاش', 'unpaid', 'المدير', '1', '', '2026-09-01 23:28:33', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('32', 'PUR-00012', 'purchase', NULL, 'مصنع الاتحاد', '01111111111', '2', '2026-09-01', '500.00', '0.00', '0.00', '500.00', '70.00', 'كاش', 'unpaid', 'المدير', '1', '', '2026-09-01 23:29:10', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('33', 'INV-00021', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2026-09-01', '65.00', '0.00', '65.00', '0.00', '-11150.00', 'كاش', 'paid', 'المدير', '1', '', '2026-09-01 23:58:04', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('34', 'INV-00022', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2026-09-01', '65.00', '0.00', '65.00', '0.00', '-11150.00', 'كاش', 'paid', 'المدير', '1', '', '2026-09-01 23:58:29', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('35', 'PUR-00013', 'purchase', NULL, 'مصنع الاتحاد', '01111111111', '2', '2026-09-01', '500.00', '0.00', '500.00', '0.00', '570.00', 'كاش', 'paid', 'المدير', '1', '', '2026-09-01 23:59:13', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('36', 'PUR-00014', 'purchase', NULL, 'مصنع الاتحاد', '01111111111', '2', '2026-09-01', '60.00', '0.00', '0.00', '60.00', '540.00', 'كاش', 'unpaid', 'المدير', '1', '', '2026-09-01 23:59:52', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('37', 'INV-00023', 'sale', '3', 'أحمد محمد', '01234567890', NULL, '2026-09-02', '5500.00', '0.00', '0.00', '5500.00', '0.00', 'كاش', 'unpaid', 'المدير', '1', '', '2026-09-02 03:39:50', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('38', 'INV-00024', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2026-09-05', '65.00', '0.00', '65.00', '0.00', '-100.00', 'كاش', 'paid', 'محمد احمد', '2', '', '2026-09-05 23:29:47', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('39', 'INV-00025', 'sale', '4', 'حسام', '0123456789', NULL, '2026-09-06', '145.00', '0.00', '145.00', '0.00', '255.00', 'كاش', 'paid', 'محمد احمد', '2', '', '2026-09-06 03:15:47', '2026-09-06 04:41:54', '1');
INSERT INTO `invoices` (`id`, `invoice_number`, `type`, `customer_id`, `customer_name`, `customer_phone`, `supplier_id`, `date`, `total_amount`, `discount`, `paid_amount`, `remaining_amount`, `old_balance`, `payment_method`, `payment_status`, `handled_by`, `user_id`, `notes`, `created_at`, `updated_at`, `warehouse_id`) VALUES ('44', 'INV-00026', 'sale', '1', 'أحمد محمد', '01222222222', NULL, '2026-09-06', '65.00', '0.00', '65.00', '0.00', '0.00', 'كاش', 'paid', 'حسام الطحان', '1', '', '2026-09-06 05:45:23', '2026-09-06 05:45:23', '5');

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL,
  `type` enum('customer','supplier') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `old_balance` decimal(10,2) DEFAULT 0.00,
  `new_balance` decimal(10,2) DEFAULT 0.00,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT 'كاش',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `handled_by` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('1', 'PAY-00001', 'customer', '1', 'أحمد محمد', '5000.00', '-5505.00', '-505.00', '2025-12-12', 'كاش', '', '', 'المدير', '1', '2025-12-12 18:28:08');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('2', 'PAY-00002', 'customer', '1', 'أحمد محمد', '5000.00', '-505.00', '4495.00', '2025-12-12', 'كاش', '', '', 'المدير', '1', '2025-12-12 18:29:34');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('3', 'PAY-00003', 'customer', '4', 'حسام', '1000.00', '-1000.00', '0.00', '2025-12-12', 'كاش', '', '', 'المدير', '1', '2025-12-12 21:39:55');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('4', 'PAY-00004', 'supplier', '1', 'شركة النور', '300.00', '500.00', '200.00', '2025-12-12', 'كاش', '', '', 'المدير', '1', '2025-12-12 23:35:52');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('5', 'PAY-00005', 'customer', '1', 'أحمد محمد', '70.00', '-1070.00', '-1000.00', '2026-09-01', 'كاش', '', '', 'المدير', '1', '2026-09-01 23:22:18');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('6', 'PAY-00006', 'supplier', '2', 'مصنع الاتحاد', '30.00', '30.00', '0.00', '2026-09-01', 'كاش', '', '', 'المدير', '1', '2026-09-01 23:22:35');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('7', 'PAY-00007', 'customer', '4', 'حسام', '45.00', '-45.00', '0.00', '2026-09-01', 'كاش', '', '', 'المدير', '1', '2026-09-01 23:29:25');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('8', 'PAY-00008', 'supplier', '3', 'شركة الإلكترونيات', '35.00', '35.00', '0.00', '2026-09-01', 'كاش', '', '', 'المدير', '1', '2026-09-01 23:29:34');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('9', 'PAY-00009', 'customer', '4', 'حسام', '200.00', '0.00', '0.00', '2025-12-12', 'كاش', 'تسوية قديمة', 'دفعة قسط (مسترجعة من السجل)', 'المدير', '1', '2025-12-12 22:41:26');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('10', 'PAY-00010', 'customer', '4', 'حسام', '50.00', '0.00', '0.00', '2025-12-12', 'كاش', 'تسوية قديمة', 'دفعة قسط (مسترجعة من السجل)', 'المدير', '1', '2025-12-12 22:45:48');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('11', 'PAY-00011', 'supplier', '1', 'شركة النور', '200.00', '0.00', '0.00', '2025-12-12', 'كاش', 'تسوية قديمة', 'دفعة قسط (مسترجعة من السجل)', 'المدير', '1', '2025-12-12 23:32:50');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('12', 'PAY-00012', 'customer', '4', 'حسام', '50.00', '0.00', '0.00', '2026-09-02', 'كاش', 'تسوية قديمة', 'دفعة قسط (مسترجعة من السجل)', 'المدير', '1', '2026-09-02 00:00:15');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('13', 'PAY-00013', 'customer', '1', 'أحمد محمد', '500.00', '0.00', '0.00', '2026-09-02', 'كاش', 'تسوية قديمة', 'دفعة قسط (مسترجعة من السجل)', 'المدير', '1', '2026-09-02 00:15:20');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('14', 'PAY-00014', 'customer', '1', 'أحمد محمد', '400.00', '0.00', '0.00', '2026-09-02', 'كاش', 'تسوية قديمة', 'دفعة قسط (مسترجعة من السجل)', 'المدير', '1', '2026-09-02 00:20:58');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('15', 'PAY-00015', 'customer', '4', 'حسام', '55.00', '0.00', '0.00', '2026-09-02', 'كاش', 'تسوية قديمة', 'دفعة قسط (مسترجعة من السجل)', 'المدير', '1', '2026-09-02 00:44:16');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('16', 'PAY-00016', 'customer', '3', 'أحمد محمد', '300.00', '-4000.00', '-3700.00', '2026-09-02', 'كاش', 'قسط-6', '', 'المدير', '1', '2026-09-02 00:55:27');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('17', 'PAY-00017', 'customer', '3', 'أحمد محمد', '500.00', '-5500.00', '-5000.00', '2026-09-02', 'كاش', '', '', 'المدير', '1', '2026-09-02 22:44:02');
INSERT INTO `payments` (`id`, `payment_number`, `type`, `entity_id`, `entity_name`, `amount`, `old_balance`, `new_balance`, `payment_date`, `payment_method`, `reference`, `notes`, `handled_by`, `user_id`, `created_at`) VALUES ('18', 'PAY-00018', 'customer', '1', 'أحمد محمد', '100.00', '-100.00', '0.00', '2026-09-06', 'كاش', '', '', 'المدير', '1', '2026-09-06 03:18:24');

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT '┘éÏÀÏ╣Ï®',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL COMMENT 'Ï│Ï╣Ï▒ Ïº┘äÏ┤Ï▒ÏºÏí ┘äÏ¡Ï│ÏºÏ¿ Ïº┘äÏúÏ▒Ï¿ÏºÏ¡',
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) NOT NULL DEFAULT 5,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_name` (`name`),
  KEY `idx_stock` (`stock_quantity`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (`id`, `code`, `name`, `unit`, `price`, `wholesale_price`, `cost_price`, `stock_quantity`, `min_stock_level`, `description`, `image`, `created_at`, `updated_at`) VALUES ('1', 'TR001', 'ترابيزة مكواة', 'قطعة', '145.00', '130.00', '100.00', '10', '5', 'ترابيزة مكواة خشب زان', NULL, '2025-12-06 23:04:13', '2026-09-06 03:15:47');
INSERT INTO `products` (`id`, `code`, `name`, `unit`, `price`, `wholesale_price`, `cost_price`, `stock_quantity`, `min_stock_level`, `description`, `image`, `created_at`, `updated_at`) VALUES ('2', 'BK001', 'باسكت قمامة', 'قطعة', '65.00', '55.00', '40.00', '69', '10', 'باسكت بلاستيك وسط', 'products/693a98aba2989_1765447851.jpg', '2025-12-06 23:04:13', '2026-09-06 05:45:23');
INSERT INTO `products` (`id`, `code`, `name`, `unit`, `price`, `wholesale_price`, `cost_price`, `stock_quantity`, `min_stock_level`, `description`, `image`, `created_at`, `updated_at`) VALUES ('4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '5500.00', NULL, NULL, '95', '25', '', NULL, '2025-12-06 23:59:11', '2026-09-02 03:41:52');
INSERT INTO `products` (`id`, `code`, `name`, `unit`, `price`, `wholesale_price`, `cost_price`, `stock_quantity`, `min_stock_level`, `description`, `image`, `created_at`, `updated_at`) VALUES ('7', 'PRD0001', 'بلالين', 'قطعة', '15.00', NULL, NULL, '0', '5', NULL, NULL, '2025-12-11 23:28:38', '2025-12-11 23:28:38');

DROP TABLE IF EXISTS `return_items`;
CREATE TABLE `return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `product_name` varchar(200) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('1', '1', '1', 'TR001', 'ترابيزة مكواة', 'قطعة', '1', '145.00', '145.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('2', '2', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('3', '3', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '40.00', '40.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('4', '4', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('5', '5', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '40.00', '40.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('6', '6', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '40.00', '40.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('7', '7', '2', 'BK001', 'باسكت قمامة', 'قطعة', '4', '40.00', '160.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('8', '8', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '40.00', '40.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('9', '9', '2', 'BK001', 'باسكت قمامة', 'قطعة', '2', '40.00', '80.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('10', '10', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('11', '11', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('12', '12', '2', 'BK001', 'باسكت قمامة', 'قطعة', '1', '65.00', '65.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('13', '13', '2', 'BK001', 'باسكت قمامة', 'قطعة', '2', '65.00', '130.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('14', '14', '4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '1', '5000.00', '5000.00');
INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_code`, `product_name`, `unit`, `quantity`, `unit_price`, `total`) VALUES ('15', '15', '4', 'TV001', 'تلفزيون سامسونج', 'قطعة', '10', '5000.00', '50000.00');

DROP TABLE IF EXISTS `returns`;
CREATE TABLE `returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_number` varchar(20) NOT NULL,
  `type` enum('customer','supplier') DEFAULT 'customer',
  `original_invoice_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `old_balance` decimal(10,2) DEFAULT 0.00,
  `deducted_from_balance` decimal(10,2) DEFAULT 0.00,
  `cash_refund` decimal(10,2) DEFAULT 0.00,
  `refund_method` enum('deducted','cash','mixed') DEFAULT 'cash',
  `handled_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`original_invoice_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_date` (`return_date`),
  KEY `fk_return_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_return_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('1', 'RET-00001', 'customer', '5', '4', 'حسام', NULL, NULL, '2025-12-11', '145.00', '0.00', '5.00', '0.00', '', 'المدير', '', '2025-12-11 20:04:16', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('2', 'RET-00002', 'customer', '6', '4', 'حسام', NULL, NULL, '2025-12-11', '65.00', '0.00', '25.00', '0.00', 'mixed', 'المدير', '', '2025-12-11 20:19:38', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('3', 'RET-00003', 'customer', '7', '1', 'أحمد محمد', NULL, NULL, '2025-12-11', '40.00', '0.00', '40.00', '0.00', 'deducted', 'المدير', '', '2025-12-11 20:39:24', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('4', 'RET-00004', 'customer', '14', '4', 'حسام', NULL, NULL, '2025-12-11', '65.00', '0.00', '10.00', '0.00', 'mixed', 'المدير', '', '2025-12-11 23:09:11', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('5', 'RET-00005', 'supplier', '17', NULL, NULL, '2', 'مصنع الاتحاد', '2025-12-12', '40.00', '40.00', '40.00', '0.00', 'deducted', 'المدير', '', '2025-12-12 16:26:49', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('6', 'RET-00006', 'supplier', '17', NULL, NULL, '2', 'مصنع الاتحاد', '2025-12-12', '40.00', '0.00', '0.00', '40.00', 'cash', 'المدير', '', '2025-12-12 16:43:16', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('7', 'RET-00007', 'supplier', '8', NULL, NULL, '3', 'شركة الإلكترونيات', '2025-12-12', '160.00', '10.00', '0.00', '160.00', 'cash', 'المدير', '', '2025-12-12 16:43:37', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('8', 'RET-00008', 'customer', '7', '1', 'أحمد محمد', NULL, NULL, '2025-12-12', '40.00', '-5545.00', '40.00', '0.00', 'deducted', 'المدير', '', '2025-12-12 16:46:04', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('9', 'RET-00009', 'supplier', '28', NULL, NULL, '1', 'شركة النور', '2025-12-13', '80.00', '200.00', '80.00', '0.00', 'deducted', 'المدير', '', '2025-12-13 00:40:56', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('10', 'RET-00010', 'customer', '19', '4', 'حسام', NULL, NULL, '2026-09-02', '65.00', '255.00', '0.00', '65.00', 'cash', 'المدير', '', '2026-09-02 03:08:33', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('11', 'RET-00011', 'customer', '19', '4', 'حسام', NULL, NULL, '2026-09-02', '65.00', '255.00', '0.00', '65.00', 'cash', 'المدير', '', '2026-09-02 03:08:58', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('12', 'RET-00012', 'customer', '19', '4', 'حسام', NULL, NULL, '2026-09-02', '65.00', '255.00', '0.00', '65.00', 'cash', 'المدير', '', '2026-09-02 03:10:05', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('13', 'RET-00013', 'customer', '19', '4', 'حسام', NULL, NULL, '2026-09-02', '130.00', '255.00', '0.00', '130.00', 'cash', 'المدير', '', '2026-09-02 03:24:56', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('14', 'RET-00014', 'customer', '1', '3', 'أحمد محمد', NULL, NULL, '2026-09-02', '5000.00', '-3700.00', '3700.00', '1300.00', 'mixed', 'المدير', '', '2026-09-02 03:31:40', '1', '1');
INSERT INTO `returns` (`id`, `return_number`, `type`, `original_invoice_id`, `customer_id`, `customer_name`, `supplier_id`, `supplier_name`, `return_date`, `total_amount`, `old_balance`, `deducted_from_balance`, `cash_refund`, `refund_method`, `handled_by`, `notes`, `created_at`, `user_id`, `warehouse_id`) VALUES ('15', 'RET-00015', 'customer', '1', '3', 'أحمد محمد', NULL, NULL, '2026-09-02', '50000.00', '-5500.00', '0.00', '50000.00', 'cash', 'المدير', '', '2026-09-02 03:41:52', '1', '1');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('1', 'store_name', 'الطحان', 'ÏºÏ│┘à Ïº┘ä┘àÏ¡┘ä', '2026-04-20 16:21:42');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('2', 'store_address', 'ابوصير', 'Ï╣┘å┘êÏº┘å Ïº┘ä┘àÏ¡┘ä', '2025-12-11 17:27:27');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('3', 'store_phone', '01271191616', 'Ï▒┘é┘à Ï¬┘ä┘è┘ü┘ê┘å Ïº┘ä┘àÏ¡┘ä', '2025-12-11 17:27:27');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('4', 'store_logo', 'logo/695d21984ceae_1767711128.png', '┘àÏ│ÏºÏ▒ Ï┤Ï╣ÏºÏ▒ Ïº┘ä┘àÏ¡┘ä', '2026-01-06 16:52:08');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('5', 'enable_stock_alerts', '1', 'Ï¬┘üÏ╣┘è┘ä Ï¬┘åÏ¿┘è┘çÏºÏ¬ Ïº┘ä┘àÏ«Ï▓┘ê┘å', '2025-12-06 22:59:17');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('6', 'enable_sound_alerts', '1', 'Ï¬┘üÏ╣┘è┘ä Ïº┘äÏ¬┘åÏ¿┘è┘çÏºÏ¬ Ïº┘äÏÁ┘êÏ¬┘èÏ®', '2025-12-06 22:59:17');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('7', 'backup_enabled', '1', 'Ï¬┘üÏ╣┘è┘ä Ïº┘ä┘åÏ│Ï« Ïº┘äÏºÏ¡Ï¬┘èÏºÏÀ┘è Ïº┘äÏ¬┘ä┘éÏºÏª┘è', '2025-12-06 22:59:17');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('8', 'backup_frequency', 'daily', 'Ï¬┘âÏ▒ÏºÏ▒ Ïº┘ä┘åÏ│Ï« Ïº┘äÏºÏ¡Ï¬┘èÏºÏÀ┘è', '2025-12-06 22:59:17');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('9', 'default_user', 'Ïº┘ä┘àÏ»┘èÏ▒', 'Ïº┘ä┘àÏ│Ï¬Ï«Ï»┘à Ïº┘äÏº┘üÏ¬Ï▒ÏºÏÂ┘è', '2025-12-06 22:59:17');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('10', 'invoice_prefix_sale', 'INV-', 'Ï¿ÏºÏ»ÏªÏ® Ï▒┘é┘à ┘üÏºÏ¬┘êÏ▒Ï® Ïº┘äÏ¿┘èÏ╣', '2025-12-06 22:59:17');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('11', 'invoice_prefix_purchase', 'PUR-', 'Ï¿ÏºÏ»ÏªÏ® Ï▒┘é┘à ┘üÏºÏ¬┘êÏ▒Ï® Ïº┘äÏ┤Ï▒ÏºÏí', '2025-12-06 22:59:17');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('12', 'currency', 'جنيه', 'Ïº┘äÏ╣┘à┘äÏ® Ïº┘ä┘àÏ│Ï¬Ï«Ï»┘àÏ®', '2025-12-06 23:31:55');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('13', 'store_phone2', '01271901076', NULL, '2025-12-13 03:06:04');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('14', 'last_backup_date', '2025-12-13', NULL, '2025-12-13 03:20:54');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('15', 'allow_edit_invoices', '1', NULL, '2026-09-02 01:35:21');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES ('16', 'allow_delete_invoices', '0', NULL, '2026-09-02 01:35:13');

DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transfer_id` (`transfer_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_transfer_items` (`id`, `transfer_id`, `product_id`, `quantity`, `notes`) VALUES ('3', '2', '2', '20', '');

DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(50) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `transfer_date` date NOT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `from_warehouse_id` (`from_warehouse_id`),
  KEY `to_warehouse_id` (`to_warehouse_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_transfers` (`id`, `transfer_number`, `from_warehouse_id`, `to_warehouse_id`, `transfer_date`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES ('2', 'TRF-00001', '1', '5', '2026-09-06', 'completed', 'تغذيه فرع', '1', '2026-09-06 05:43:51', '2026-09-06 05:43:51');

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `visit_days` varchar(200) DEFAULT NULL COMMENT 'JSON array of weekdays',
  `expected_products` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00 COMMENT 'Ïº┘ä┘àÏ¿┘äÏ║ Ïº┘ä┘àÏ│Ï¬Ï¡┘é ┘ä┘ä┘à┘êÏ▒Ï»',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`id`, `name`, `phone`, `address`, `company_name`, `visit_days`, `expected_products`, `notes`, `balance`, `created_at`, `updated_at`) VALUES ('1', 'شركة النور', '01000000001', NULL, NULL, '[\"السبت\",\"الثلاثاء\"]', 'أدوات منزلية', NULL, '500.00', '2025-12-06 23:04:13', '2026-09-02 01:49:57');
INSERT INTO `suppliers` (`id`, `name`, `phone`, `address`, `company_name`, `visit_days`, `expected_products`, `notes`, `balance`, `created_at`, `updated_at`) VALUES ('2', 'مصنع الاتحاد', '01111111111', NULL, NULL, '[\"الخميس\"]', 'بلاستيكات', NULL, '600.00', '2025-12-06 23:04:13', '2026-09-02 02:01:36');
INSERT INTO `suppliers` (`id`, `name`, `phone`, `address`, `company_name`, `visit_days`, `expected_products`, `notes`, `balance`, `created_at`, `updated_at`) VALUES ('3', 'شركة الإلكترونيات', '0111111111', 'سمنود', 'البدر', '[\"السبت\"]', 'مقشات بلح', '', '-105.00', '2025-12-07 00:10:06', '2026-09-02 01:49:57');

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_perm` (`user_id`,`permission_key`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('105', '2', 'customers.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('103', '2', 'dashboard.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('115', '2', 'installments.create');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('116', '2', 'installments.pay');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('114', '2', 'installments.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('110', '2', 'invoices.purchase.create');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('109', '2', 'invoices.purchase.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('108', '2', 'invoices.sale.create');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('107', '2', 'invoices.sale.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('112', '2', 'payments.customer');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('113', '2', 'payments.supplier');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('111', '2', 'payments.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('104', '2', 'products.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('121', '2', 'reports.statement');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('120', '2', 'reports.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('118', '2', 'returns.create_customer');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('119', '2', 'returns.create_supplier');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('117', '2', 'returns.view');
INSERT INTO `user_permissions` (`id`, `user_id`, `permission_key`) VALUES ('106', '2', 'suppliers.view');

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `login_at`, `logout_at`, `is_active`) VALUES ('1', '1', 'd8f305331d082d5a29a478c7bd440d488b2ddb4bac63c57570d608fd5fe5f8de', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 22:50:47', '2026-09-05 22:58:08', '0');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `login_at`, `logout_at`, `is_active`) VALUES ('2', '2', 'a8da370baff420a628ad5099eaf90f59b8bf046d3980812555b3b0aac2c59483', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 22:58:19', '2026-09-06 03:21:33', '0');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `login_at`, `logout_at`, `is_active`) VALUES ('3', '1', '6156b0c3b08feea77b3653de6322d56d231b7b73dfa6acf20278140989177367', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 03:12:59', NULL, '1');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `login_at`, `logout_at`, `is_active`) VALUES ('4', '2', '0c2b4b48b6afe6be8e6a336e4733330b2b2b55f31e428aedf875e95bd07421ec', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 03:22:09', NULL, '1');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `default_warehouse_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_user_warehouse` (`default_warehouse_id`),
  CONSTRAINT `fk_user_warehouse` FOREIGN KEY (`default_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `phone`, `email`, `avatar`, `role`, `is_active`, `last_login`, `last_login_ip`, `created_by`, `created_at`, `updated_at`, `default_warehouse_id`) VALUES ('1', 'admin', '$2y$10$FVxC20yF5AI837GbP73V..3aJE./h3CDDanGkhFnKOWi0zP00/Bt2', 'حسام الطحان', '01271191616', 'hossameltahan2004@gmail.com', 'avatars/6a9c72dedadb0_1788637918.png', 'admin', '1', '2026-09-06 03:12:59', '::1', NULL, '2026-09-05 22:38:50', '2026-09-06 04:41:54', '1');
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `phone`, `email`, `avatar`, `role`, `is_active`, `last_login`, `last_login_ip`, `created_by`, `created_at`, `updated_at`, `default_warehouse_id`) VALUES ('2', 'mohamed_ahmed', '$2y$10$78jDpwxBIisVYLEGiPhKv.1VSyo8RguifBBgN.0fO17Zrl4/RypUW', 'محمد احمد', '0123456788', NULL, 'avatars/avatar_3.png', 'employee', '1', '2026-09-06 03:22:09', '::1', '1', '2026-09-05 22:57:21', '2026-09-06 05:46:31', '5');

DROP TABLE IF EXISTS `warehouse_stock`;
CREATE TABLE `warehouse_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_warehouse_product` (`warehouse_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `warehouse_stock_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warehouse_stock_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `warehouse_stock` (`id`, `warehouse_id`, `product_id`, `quantity`, `min_stock_level`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '10', '5', '2026-09-06 04:41:54', '2026-09-06 04:41:54');
INSERT INTO `warehouse_stock` (`id`, `warehouse_id`, `product_id`, `quantity`, `min_stock_level`, `created_at`, `updated_at`) VALUES ('2', '1', '2', '50', '10', '2026-09-06 04:41:54', '2026-09-06 05:43:51');
INSERT INTO `warehouse_stock` (`id`, `warehouse_id`, `product_id`, `quantity`, `min_stock_level`, `created_at`, `updated_at`) VALUES ('3', '1', '4', '95', '25', '2026-09-06 04:41:54', '2026-09-06 04:41:54');
INSERT INTO `warehouse_stock` (`id`, `warehouse_id`, `product_id`, `quantity`, `min_stock_level`, `created_at`, `updated_at`) VALUES ('4', '1', '7', '0', '5', '2026-09-06 04:41:54', '2026-09-06 04:41:54');
INSERT INTO `warehouse_stock` (`id`, `warehouse_id`, `product_id`, `quantity`, `min_stock_level`, `created_at`, `updated_at`) VALUES ('26', '5', '7', '0', NULL, '2026-09-06 05:42:30', '2026-09-06 05:42:30');
INSERT INTO `warehouse_stock` (`id`, `warehouse_id`, `product_id`, `quantity`, `min_stock_level`, `created_at`, `updated_at`) VALUES ('27', '5', '1', '0', NULL, '2026-09-06 05:42:30', '2026-09-06 05:42:30');
INSERT INTO `warehouse_stock` (`id`, `warehouse_id`, `product_id`, `quantity`, `min_stock_level`, `created_at`, `updated_at`) VALUES ('28', '5', '2', '19', NULL, '2026-09-06 05:42:30', '2026-09-06 05:45:23');
INSERT INTO `warehouse_stock` (`id`, `warehouse_id`, `product_id`, `quantity`, `min_stock_level`, `created_at`, `updated_at`) VALUES ('29', '5', '4', '0', NULL, '2026-09-06 05:42:30', '2026-09-06 05:42:30');

DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `warehouses` (`id`, `name`, `code`, `location`, `phone`, `manager_name`, `is_default`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES ('1', 'المخزن الرئيسي', 'WH-MAIN', 'المقر الرئيسي', '04029011', 'امين مخزن 1', '1', '1', 'المخزن الرئيسي الافتراضي للنظام', '2026-09-06 04:41:54', '2026-09-06 05:53:12');
INSERT INTO `warehouses` (`id`, `name`, `code`, `location`, `phone`, `manager_name`, `is_default`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES ('5', 'مخزن فيصل', 'WH-02', '15 شارع الهرم', '04029012', 'امين مخزن 2', '0', '1', '', '2026-09-06 05:42:30', '2026-09-06 05:53:22');

SET FOREIGN_KEY_CHECKS=1;
