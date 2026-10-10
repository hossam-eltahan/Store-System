<?php
/**
 * Products & Menu Management (إدارة منيو وأصناف الكافيه)
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
require_once '../../config/categories.php';
requirePermission('products.view');

$pageTitle = 'منيو وقائمة أصناف الكافيه';

// Handle quick toggle availability
if (isset($_GET['toggle_availability']) && hasPermission('products.edit')) {
    $id = (int)$_GET['toggle_availability'];
    $prod = getRow("SELECT id, is_available FROM products WHERE id = ?", [$id]);
    if ($prod) {
        $newStatus = $prod['is_available'] ? 0 : 1;
        execute("UPDATE products SET is_available = ? WHERE id = ?", [$newStatus, $id]);
        setSuccess('تم تغيير حالة توفر الصنف بنجاح');
    }
    redirect('index.php' . (isset($_GET['category_id']) ? '?category_id=' . (int)$_GET['category_id'] : ''));
}

// Handle delete
if (isset($_GET['delete'])) {
    requirePermission('products.delete');
    $id = (int)$_GET['delete'];
    $product = getRow("SELECT * FROM products WHERE id = ?", [$id]);

    if ($product) {
        if ($product['image'] && file_exists('../../' . $product['image'])) {
            @unlink('../../' . $product['image']);
        }

        execute("DELETE FROM products WHERE id = ?", [$id]);
        logActivity('حذف صنف', "تم حذف الصنف: {$product['name']}");
        setSuccess('تم حذف الصنف من المنيو بنجاح');
    }

    redirect('index.php');
}

// Filters
$search = trim($_GET['search'] ?? '');
$filterCategory = (int)($_GET['category_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(p.code LIKE ? OR p.name LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($filterCategory > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $filterCategory;
}

if ($filterStatus === 'available') {
    $where[] = "p.is_available = 1";
} elseif ($filterStatus === 'unavailable') {
    $where[] = "p.is_available = 0";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$perPage = 25;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalCountRow = getRow("SELECT COUNT(*) as count FROM products p $whereClause", $params);
$totalProducts = (int)($totalCountRow['count'] ?? 0);
$totalPages = ceil($totalProducts / $perPage);

// Fetch products with category details and variants count
$products = getRows("
    SELECT p.*, c.name as category_name, c.icon as category_icon, c.color as category_color,
           (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) as variants_count,
           (SELECT MIN(final_price) FROM product_variants pv WHERE pv.product_id = p.id) as min_variant_price,
           (SELECT MAX(final_price) FROM product_variants pv WHERE pv.product_id = p.id) as max_variant_price
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $whereClause
    ORDER BY p.category_id ASC, p.display_order ASC, p.id DESC
    LIMIT $perPage OFFSET $offset
", $params);

$allCategories = getRows("
    SELECT c.*, COUNT(p.id) as products_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id 
    GROUP BY c.id 
    ORDER BY c.sort_order ASC, c.name ASC
");

$totalAll = getRow("SELECT COUNT(*) as cnt FROM products")['cnt'] ?? 0;
$totalAvail = getRow("SELECT COUNT(*) as cnt FROM products WHERE is_available = 1")['cnt'] ?? 0;
$totalVariants = getRow("SELECT COUNT(*) as cnt FROM product_variants")['cnt'] ?? 0;

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container-fluid" style="padding: 15px 25px;">
    <!-- Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
                ☕ منيو وقائمة أصناف الكافيه
            </h2>
            <p style="color: #64748b; margin: 4px 0 0;">إدارة المنتجات والمشروبات، الأسعار، الأحجام والإضافات الخاصة بكافيه عروسة النيل</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="modifiers.php" class="btn btn-outline-secondary" style="border-radius: 8px;">
                🍯 الإضافات والصوصات
            </a>
            <a href="../categories/index.php" class="btn btn-outline-secondary" style="border-radius: 8px;">
                🏷️ إدارة الأقسام
            </a>
            <a href="add.php" class="btn btn-primary" style="border-radius: 8px; font-weight: 700; background: #6F4E37; border-color: #6F4E37;">
                ➕ إضافة صنف جديد للمنيو
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 15px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 24px;">📋</div>
            <div>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">إجمالي الأصناف</div>
                <div style="font-size: 22px; font-weight: 800; color: #1e293b;"><?php echo number_format($totalAll); ?></div>
            </div>
        </div>
        <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 15px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 24px;">✅</div>
            <div>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">أصناف متوفرة للبيع</div>
                <div style="font-size: 22px; font-weight: 800; color: #1e293b;"><?php echo number_format($totalAvail); ?></div>
            </div>
        </div>
        <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 15px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 24px;">📏</div>
            <div>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">أحجام وخيارات مسجلة</div>
                <div style="font-size: 22px; font-weight: 800; color: #1e293b;"><?php echo number_format($totalVariants); ?></div>
            </div>
        </div>
        <div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 15px;">
            <div style="width: 48px; height: 48px; border-radius: 10px; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 24px;">🏷️</div>
            <div>
                <div style="font-size: 13px; color: #64748b; font-weight: 600;">أقسام المنيو</div>
                <div style="font-size: 22px; font-weight: 800; color: #1e293b;"><?php echo count($allCategories); ?></div>
            </div>
        </div>
    </div>

    <!-- Category Filter Tabs (Horizontal Scrollable) -->
    <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 15px; margin-bottom: 15px;">
        <a href="index.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" 
           class="badge" 
           style="text-decoration: none; padding: 10px 16px; font-size: 14px; border-radius: 20px; font-weight: 700; transition: all 0.2s; <?php echo ($filterCategory === 0) ? 'background: #6F4E37; color: white;' : 'background: #f1f5f9; color: #475569;'; ?>">
           ✨ الكل (<?php echo $totalAll; ?>)
        </a>
        <?php foreach ($allCategories as $c): ?>
            <a href="index.php?category_id=<?php echo $c['id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
               class="badge" 
               style="text-decoration: none; padding: 10px 16px; font-size: 14px; border-radius: 20px; font-weight: 700; transition: all 0.2s; white-space: nowrap; <?php echo ($filterCategory == $c['id']) ? 'background: #6F4E37; color: white;' : 'background: #f1f5f9; color: #475569;'; ?>">
               <?php echo htmlspecialchars($c['icon'] ?? '☕'); ?> <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['products_count']; ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filters & Search Form -->
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <?php if ($filterCategory > 0): ?>
                    <input type="hidden" name="category_id" value="<?php echo $filterCategory; ?>">
                <?php endif; ?>
                <div style="flex: 1; min-width: 250px;">
                    <div class="input-group">
                        <span class="input-group-text" style="background: #f8fafc; border-radius: 8px 0 0 8px;">🔍</span>
                        <input type="text" name="search" class="form-control" placeholder="بحث بالاسم أو الكود أو الوصف..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 0 8px 8px 0;">
                    </div>
                </div>
                <div style="width: 180px;">
                    <select name="status" class="form-select" style="border-radius: 8px;">
                        <option value="">كل الحالات</option>
                        <option value="available" <?php echo $filterStatus === 'available' ? 'selected' : ''; ?>>متوفر فقط</option>
                        <option value="unavailable" <?php echo $filterStatus === 'unavailable' ? 'selected' : ''; ?>>غير متوفر</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary" style="border-radius: 8px;">تصفية</button>
                    <?php if ($search || $filterCategory || $filterStatus): ?>
                        <a href="index.php" class="btn btn-outline-danger" style="border-radius: 8px;">إلغاء الفلاتر</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-hover" style="margin: 0;">
                    <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <th style="padding: 14px 20px;">الصنف</th>
                            <th>القسم</th>
                            <th>السعر</th>
                            <th>الأحجام / الخيارات</th>
                            <th>الحالة في الكاشير</th>
                            <th style="text-align: left; padding-left: 20px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 50px; color: #94a3b8;">
                                    <div style="font-size: 32px; margin-bottom: 10px;">🔍</div>
                                    <div>لا توجد أصناف تطابق معايير البحث.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $prod): ?>
                                <tr style="vertical-align: middle;">
                                    <td style="padding: 14px 20px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 44px; height: 44px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 20px; overflow: hidden; flex-shrink: 0;">
                                                <?php if (!empty($prod['image']) && file_exists('../../' . $prod['image'])): ?>
                                                    <img src="../../<?php echo htmlspecialchars($prod['image']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($prod['category_icon'] ?? '☕'); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: #1e293b; font-size: 15px;">
                                                    <?php echo htmlspecialchars($prod['name']); ?>
                                                </div>
                                                <div style="font-size: 12px; color: #94a3b8;">
                                                    كود: <?php echo htmlspecialchars($prod['code']); ?> 
                                                    <?php if ($prod['unit']): ?>• الوحدة: <?php echo htmlspecialchars($prod['unit']); ?><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #f1f5f9; color: #334155; padding: 6px 12px; border-radius: 6px; font-weight: 600;">
                                            <?php echo htmlspecialchars($prod['category_icon'] ?? ''); ?> <?php echo htmlspecialchars($prod['category_name'] ?? 'بدون قسم'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($prod['variants_count'] > 0): ?>
                                            <div style="font-weight: 800; color: #059669; font-size: 15px;">
                                                <?php if ($prod['min_variant_price'] == $prod['max_variant_price']): ?>
                                                    <?php echo formatCurrency($prod['min_variant_price']); ?>
                                                <?php else: ?>
                                                    <?php echo formatCurrency($prod['min_variant_price']); ?> - <?php echo formatCurrency($prod['max_variant_price']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <small style="color: #64748b; font-size: 11px;">(حسب الحجم)</small>
                                        <?php else: ?>
                                            <div style="font-weight: 800; color: #059669; font-size: 15px;">
                                                <?php echo formatCurrency($prod['price']); ?>
                                            </div>
                                            <?php if ($prod['cost_price'] > 0): ?>
                                                <small style="color: #94a3b8; font-size: 11px;">التكلفة: <?php echo formatCurrency($prod['cost_price']); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($prod['variants_count'] > 0): ?>
                                            <span class="badge" style="background: #ede9fe; color: #6d28d9; padding: 5px 10px; border-radius: 6px; font-weight: 600;">
                                                📏 <?php echo $prod['variants_count']; ?> أحجام
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 13px;">حجم موحد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (hasPermission('products.edit')): ?>
                                            <a href="?toggle_availability=<?php echo $prod['id']; ?><?php echo $filterCategory ? '&category_id=' . $filterCategory : ''; ?>" 
                                               class="badge" 
                                               style="text-decoration: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; <?php echo $prod['is_available'] ? 'background: #dcfce7; color: #166534;' : 'background: #fee2e2; color: #991b1b;'; ?>"
                                               title="اضغط لتغيير الحالة">
                                                <?php echo $prod['is_available'] ? '🟢 متوفر' : '🔴 غير متوفر'; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge" style="<?php echo $prod['is_available'] ? 'background: #dcfce7; color: #166534;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                                                <?php echo $prod['is_available'] ? 'متوفر' : 'غير متوفر'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: left; padding-left: 20px;">
                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                            <a href="edit.php?id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline-primary" title="تعديل الصنف والأسعار">
                                                ✏️ تعديل
                                            </a>
                                            <a href="delete.php?id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline-danger" title="حذف الصنف">
                                                🗑️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0;">
                    <div style="color: #64748b; font-size: 13px;">
                        عرض صفحة <?php echo $page; ?> من إجمالي <?php echo $totalPages; ?> (<?php echo $totalProducts; ?> صنف)
                    </div>
                    <ul class="pagination mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filterCategory ? '&category_id=' . $filterCategory : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
