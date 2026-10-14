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
$perPage = 12;
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
            <a href="../../index.php" class="btn btn-secondary" style="border-radius: 8px;">
                🏠 الرئيسية
            </a>
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

    <style>
        .menu-category-tabs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 4px 2px 14px;
            margin-bottom: 15px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .menu-category-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex: 0 0 auto;
            min-height: 42px;
            padding: 8px 13px;
            border: 1px solid #dbe3ec;
            border-radius: 12px;
            background: #fff;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(15, 23, 42, 0.05);
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease, color 0.18s ease;
        }
        .menu-category-tab:hover {
            transform: translateY(-2px);
            border-color: #a98263;
            color: #6f4e37;
        }
        .menu-category-tab.active {
            background: #6f4e37;
            border-color: #6f4e37;
            color: #fff;
            box-shadow: 0 5px 12px rgba(111, 78, 55, 0.22);
        }
        .menu-category-count {
            min-width: 24px;
            padding: 2px 6px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 11px;
            text-align: center;
        }
        .menu-category-tab.active .menu-category-count {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
    </style>

    <!-- Category Filter Tabs -->
    <div class="menu-category-tabs">
        <a href="index.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" 
           class="menu-category-tab <?php echo ($filterCategory === 0) ? 'active' : ''; ?>">
           <span>✨</span><span>الكل</span><b class="menu-category-count"><?php echo $totalAll; ?></b>
        </a>
        <?php foreach ($allCategories as $c): ?>
            <a href="index.php?category_id=<?php echo $c['id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
               class="menu-category-tab <?php echo ($filterCategory == $c['id']) ? 'active' : ''; ?>">
               <span><?php echo htmlspecialchars($c['icon'] ?? '☕'); ?></span><span><?php echo htmlspecialchars($c['name']); ?></span><b class="menu-category-count"><?php echo $c['products_count']; ?></b>
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
                                            <?php if (hasPermission('products.delete')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="حذف الصنف" onclick='confirmDeleteProduct(<?php echo $prod['id']; ?>, <?php echo json_encode($prod['name']); ?>, <?php echo json_encode($prod['code']); ?>)'>
                                                    🗑️
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sleek Pagination Navigator -->
            <?php if ($totalPages > 1): ?>
                <?php
                $paginationBaseQuery = ($filterCategory ? '&category_id=' . $filterCategory : '') . ($search ? '&search=' . urlencode($search) : '');
                ?>
                <div style="padding: 16px 22px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 12px; background: #fafbfc;">
                    <div style="color: #64748b; font-size: 13.5px; font-weight: 600;">
                        عرض صفحة <span style="color: #0f172a; font-weight: 800;"><?php echo $page; ?></span> من إجمالي <span style="color: #0f172a; font-weight: 800;"><?php echo $totalPages; ?></span> (<?php echo $totalProducts; ?> صنف مسجل)
                    </div>
                    <nav aria-label="تنقل الصفحات">
                        <ul class="pagination" style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: center !important; gap: 5px !important;">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>" style="list-style: none !important; margin: 0 !important; padding: 0 !important;">
                                <a class="page-link" href="<?php echo ($page > 1) ? '?page=' . ($page - 1) . $paginationBaseQuery : '#'; ?>" 
                                   style="display: inline-flex !important; align-items: center !important; justify-content: center !important; height: 36px !important; padding: 0 14px !important; font-size: 13px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important; <?php echo ($page <= 1) ? 'color: #94a3b8 !important; background: #f1f5f9 !important; pointer-events: none !important;' : 'color: #334155 !important; background: #fff !important;'; ?>">
                                    ← السابق
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php 
                            $startPage = max(1, $page - 3);
                            $endPage = min($totalPages, $page + 3);
                            if ($startPage > 1) {
                                echo '<li style="list-style: none !important;"><a class="page-link" href="?page=1' . $paginationBaseQuery . '" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 36px !important; height: 36px !important; padding: 0 10px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important; background: #fff !important; color: #334155 !important;">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li style="list-style: none !important; color: #94a3b8; padding: 0 4px; font-weight: bold;">...</li>';
                                }
                            }

                            for ($i = $startPage; $i <= $endPage; $i++): 
                                $isActive = ($i === $page);
                            ?>
                                <li class="page-item <?php echo $isActive ? 'active' : ''; ?>" style="list-style: none !important; margin: 0 !important; padding: 0 !important;">
                                    <a class="page-link" href="?page=<?php echo $i . $paginationBaseQuery; ?>"
                                       style="display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 36px !important; height: 36px !important; padding: 0 10px !important; font-size: 13.5px !important; font-weight: 700 !important; border-radius: 8px !important; text-decoration: none !important; <?php echo $isActive ? 'background: #ea580c !important; color: #fff !important; border: 1px solid #ea580c !important; box-shadow: 0 2px 6px rgba(234,88,12,0.3) !important;' : 'background: #fff !important; color: #334155 !important; border: 1px solid #cbd5e1 !important;'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php 
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li style="list-style: none !important; color: #94a3b8; padding: 0 4px; font-weight: bold;">...</li>';
                                }
                                echo '<li style="list-style: none !important;"><a class="page-link" href="?page=' . $totalPages . $paginationBaseQuery . '" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 36px !important; height: 36px !important; padding: 0 10px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important; background: #fff !important; color: #334155 !important;">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <!-- Next Page -->
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>" style="list-style: none !important; margin: 0 !important; padding: 0 !important;">
                                <a class="page-link" href="<?php echo ($page < $totalPages) ? '?page=' . ($page + 1) . $paginationBaseQuery : '#'; ?>"
                                   style="display: inline-flex !important; align-items: center !important; justify-content: center !important; height: 36px !important; padding: 0 14px !important; font-size: 13px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important; <?php echo ($page >= $totalPages) ? 'color: #94a3b8 !important; background: #f1f5f9 !important; pointer-events: none !important;' : 'color: #334155 !important; background: #fff !important;'; ?>">
                                    التالي →
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Delete Product Confirmation Popup -->
<div class="modal" id="deleteProductModal" tabindex="-1" style="display: none !important;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #fee2e2; border-bottom: 1px solid #fecaca;">
                <h5 class="modal-title" style="color: #991b1b; font-weight: 800;">⚠️ تأكيد حذف الصنف</h5>
                <button type="button" class="btn-close" onclick="closeDeleteProductModal()"></button>
            </div>
            <form method="POST" action="delete.php">
                <input type="hidden" name="confirm_delete" value="1">
                <input type="hidden" name="id" id="deleteProductId" value="">
                <div class="modal-body" style="text-align: center; padding: 25px;">
                    <div style="font-size: 44px; margin-bottom: 10px;">🗑️</div>
                    <div style="font-size: 16px; color: #1e293b; margin-bottom: 8px;">
                        هل أنت متأكد من حذف هذا الصنف من المنيو؟
                    </div>
                    <div id="deleteProductName" style="font-size: 18px; font-weight: 800; color: #dc2626; margin-bottom: 4px;"></div>
                    <div id="deleteProductCode" style="font-size: 13px; color: #64748b; margin-bottom: 14px;"></div>
                    <div style="font-size: 12px; color: #b91c1c; background: #fff1f2; padding: 10px 14px; border-radius: 8px; border: 1px solid #fecdd3; line-height: 1.5;">
                        ⚠️ هذا الإجراء نهائي وسيتم حذف الصنف ومقاديره المرتبطة من القائمة.
                    </div>
                </div>
                <div class="modal-footer" style="justify-content: center; gap: 12px; background: #f8fafc;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteProductModal()" style="min-width: 100px;">إلغاء</button>
                    <button type="submit" class="btn btn-danger" style="min-width: 130px; font-weight: 700;">🗑️ نعم، احذف الصنف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDeleteProduct(id, name, code) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteProductName').innerText = name;
    document.getElementById('deleteProductCode').innerText = 'كود الصنف: ' + (code || '---');
    const modal = document.getElementById('deleteProductModal');
    modal.style.setProperty('display', 'flex', 'important');
    modal.classList.add('show');
}

function closeDeleteProductModal() {
    const modal = document.getElementById('deleteProductModal');
    modal.style.setProperty('display', 'none', 'important');
    modal.classList.remove('show');
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('deleteProductModal');
    if (e.target === modal) {
        closeDeleteProductModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
