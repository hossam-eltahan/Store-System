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

$tabParam = $_GET['tab'] ?? 'items';
$tab = ($tabParam === 'cats') ? 'cats' : (($tabParam === 'mods') ? 'mods' : 'items');

// Categories tab needs its own permission; deny before any output so the
// denial renders inside the normal app shell (never mid-page).
if ($tab === 'cats' && !hasPermission('categories.view')) {
    requirePermission('categories.view');
}

// ---- Categories tab: create / update / delete (moved from modules/categories) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cat_action'])) {
    requirePermission('categories.manage');
    $catAction = $_POST['cat_action'] ?? '';
    $catId = (int)($_POST['category_id'] ?? 0);

    if ($catAction === 'delete_cat') {
        $productCount = getRow("SELECT COUNT(*) AS count FROM products WHERE category_id = ?", [$catId]);
        $childCount = getRow("SELECT COUNT(*) AS count FROM categories WHERE parent_id = ?", [$catId]);
        if (($productCount['count'] ?? 0) > 0 || ($childCount['count'] ?? 0) > 0) {
            setError('لا يمكن حذف القسم لأنه يحتوي على أصناف بالمنيو. يرجى نقل الأصناف أو تعديلها أولاً.');
        } else {
            execute("DELETE FROM categories WHERE id = ?", [$catId]);
            logActivity('حذف قسم', 'تم حذف قسم من المنيو');
            setSuccess('تم حذف القسم بنجاح');
        }
        redirect('index.php?tab=cats');
    }

    $catName = sanitize(trim($_POST['name'] ?? ''));
    $catIcon = sanitize(trim($_POST['icon'] ?? '☕'));
    $catColor = sanitize(trim($_POST['color'] ?? '#6F4E37'));
    $catParentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $catDesc = sanitize(trim($_POST['description'] ?? ''));
    $catSort = max(0, (int)($_POST['sort_order'] ?? 0));
    $catActive = isset($_POST['is_active']) ? 1 : 0;

    if ($catName === '') {
        setError('اسم القسم مطلوب');
    } elseif ($catParentId && !getRow("SELECT id FROM categories WHERE id = ?", [$catParentId])) {
        setError('القسم الرئيسي المحدد غير موجود');
    } elseif ($catAction === 'update_cat' && ($catParentId === $catId || in_array($catParentId, getCategoryDescendantIds($catId), true))) {
        setError('لا يمكن جعل القسم تابعاً لنفسه');
    } else {
        if ($catAction === 'update_cat') {
            execute(
                "UPDATE categories SET name = ?, icon = ?, color = ?, parent_id = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?",
                [$catName, $catIcon, $catColor, $catParentId, $catDesc, $catSort, $catActive, $catId]
            );
            logActivity('تعديل قسم', "تم تعديل القسم: $catName");
            setSuccess('تم تعديل بيانات القسم بنجاح');
        } else {
            insert(
                "INSERT INTO categories (name, icon, color, parent_id, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$catName, $catIcon, $catColor, $catParentId, $catDesc, $catSort, $catActive]
            );
            logActivity('إضافة قسم', "تم إضافة قسم للمنيو: $catName");
            setSuccess('تم إضافة القسم بنجاح');
        }
        redirect('index.php?tab=cats');
    }
}

// ---- Modifiers tab: create / update / delete (moved from modules/products/modifiers.php) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mod_crud'])) {
    $modAction = $_POST['action'] ?? '';

    if ($modAction === 'create' || $modAction === 'update') {
        $modName = sanitize(trim($_POST['name'] ?? ''));
        $modPrice = floatval($_POST['price'] ?? 0);
        $modGroup = sanitize(trim($_POST['modifier_group'] ?? 'extra'));
        $modCatId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $modAvail = isset($_POST['is_available']) ? 1 : 0;

        if ($modName === '') {
            setError('يرجى إدخال اسم الإضافة.');
        } else {
            if ($modAction === 'create') {
                insert(
                    "INSERT INTO product_modifiers (name, modifier_group, price, applies_to_category_id, is_available) VALUES (?, ?, ?, ?, ?)",
                    [$modName, $modGroup, $modPrice, $modCatId, $modAvail]
                );
                logActivity('إضافة تعديل', "تم إضافة الإضافة: $modName");
                setSuccess('تم إضافة الإضافة/الصوص بنجاح');
            } else {
                $modId = (int)($_POST['modifier_id'] ?? 0);
                execute(
                    "UPDATE product_modifiers SET name = ?, modifier_group = ?, price = ?, applies_to_category_id = ?, is_available = ? WHERE id = ?",
                    [$modName, $modGroup, $modPrice, $modCatId, $modAvail, $modId]
                );
                logActivity('تعديل إضافة', "تم تعديل الإضافة: $modName");
                setSuccess('تم تحديث الإضافة بنجاح');
            }
        }
    } elseif ($modAction === 'delete') {
        $modId = (int)($_POST['modifier_id'] ?? 0);
        if ($modId > 0) {
            // If referenced in previous orders, soft-disable it instead of foreign key failure
            $isUsed = getRow("SELECT id FROM order_item_modifiers WHERE modifier_id = ? LIMIT 1", [$modId]);
            if ($isUsed) {
                execute("UPDATE product_modifiers SET is_available = 0 WHERE id = ?", [$modId]);
                setSuccess('تم إلغاء تفعيل الإضافة وحجبها من الكاشير لوجود فواتير سابقة مرتبطة بها.');
            } else {
                execute("DELETE FROM product_modifiers WHERE id = ?", [$modId]);
                setSuccess('تم حذف الإضافة بنجاح');
            }
        }
    }
    redirect('index.php?tab=mods');
}

$editCatId = (int)($_GET['edit_cat'] ?? 0);
$editingCategory = ($tab === 'cats' && $editCatId) ? getRow("SELECT * FROM categories WHERE id = ?", [$editCatId]) : null;
$canManageCats = hasPermission('categories.manage');

// Categories list for the cats tab
$catSearch = trim($_GET['cat_search'] ?? '');
$catWhere = "";
$catParams = [];
if ($catSearch !== '') {
    $catWhere = "WHERE c.name LIKE ?";
    $catParams[] = "%$catSearch%";
}
$catPerPage = 8;
$catPage = max(1, (int)($_GET['cpage'] ?? 1));
$catTotal = (int)(getRow("SELECT COUNT(*) AS total FROM categories c $catWhere", $catParams)['total'] ?? 0);
$catPages = max(1, (int)ceil($catTotal / $catPerPage));
$catPage = min($catPage, $catPages);
$catOffset = ($catPage - 1) * $catPerPage;
$categoriesList = getRows("
    SELECT c.*, COUNT(p.id) AS products_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    $catWhere
    ORDER BY c.sort_order ASC, c.name ASC
    LIMIT $catPerPage OFFSET $catOffset
", $catParams);
$allParents = getRows("SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC");

// Modifiers tab data (sauces/extras master list, same table the cashier window reads)
$totalMods = (int)(getRow("SELECT COUNT(*) AS total FROM product_modifiers")['total'] ?? 0);
$modifiersList = [];
if ($tab === 'mods') {
    $modSearch = trim($_GET['search'] ?? '');
    $modGroup = trim($_GET['group'] ?? '');
    $modCat = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $modWhere = "WHERE 1=1";
    $modParams = [];
    if ($modSearch !== '') { $modWhere .= " AND m.name LIKE ?"; $modParams[] = "%$modSearch%"; }
    if ($modGroup !== '') { $modWhere .= " AND m.modifier_group = ?"; $modParams[] = $modGroup; }
    if ($modCat > 0) { $modWhere .= " AND (m.applies_to_category_id = ? OR m.applies_to_category_id IS NULL)"; $modParams[] = $modCat; }
    $modifiersList = getRows(
        "SELECT m.*, c.name as category_name
         FROM product_modifiers m
         LEFT JOIN categories c ON c.id = m.applies_to_category_id
         $modWhere
         ORDER BY m.display_order ASC, m.id DESC",
        $modParams
    );
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
            <a href="index.php?tab=mods" class="btn btn-outline-secondary" style="border-radius: 8px;">
                🍯 الإضافات والصوصات
            </a>
            <a href="index.php?tab=cats" class="btn btn-outline-secondary" style="border-radius: 8px;">
                🏷️ إدارة الأقسام
            </a>
            <a href="add.php" class="btn btn-primary" style="border-radius: 8px; font-weight: 700; background: #6F4E37; border-color: #6F4E37;">
                ➕ إضافة صنف جديد للمنيو
            </a>
        </div>
    </div>

    <!-- Items / Categories Tabs -->
    <div style="display: flex; gap: 8px; margin-bottom: 18px;">
        <a href="index.php" style="padding: 9px 22px; border-radius: 10px; font-weight: 800; font-size: 13.5px; text-decoration: none; <?php echo $tab === 'items' ? 'background: #6F4E37; color: #fff; box-shadow: 0 3px 8px rgba(111,78,55,0.3);' : 'background: #fff; color: #475569; border: 1px solid #e2e8f0;'; ?>">
            ☕ الأصناف (<?php echo number_format($totalAll); ?>)
        </a>
        <?php if (hasPermission('categories.view')): ?>
        <a href="index.php?tab=cats" style="padding: 9px 22px; border-radius: 10px; font-weight: 800; font-size: 13.5px; text-decoration: none; <?php echo $tab === 'cats' ? 'background: #6F4E37; color: #fff; box-shadow: 0 3px 8px rgba(111,78,55,0.3);' : 'background: #fff; color: #475569; border: 1px solid #e2e8f0;'; ?>">
            🏷️ الأقسام (<?php echo $catTotal; ?>)
        </a>
        <?php endif; ?>
        <a href="index.php?tab=mods" style="padding: 9px 22px; border-radius: 10px; font-weight: 800; font-size: 13.5px; text-decoration: none; <?php echo $tab === 'mods' ? 'background: #6F4E37; color: #fff; box-shadow: 0 3px 8px rgba(111,78,55,0.3);' : 'background: #fff; color: #475569; border: 1px solid #e2e8f0;'; ?>">
            🍯 الصوصات (<?php echo $totalMods; ?>)
        </a>
    </div>

    <?php if ($tab === 'items'): ?>
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
                                            <?php if (hasPermission('inventory.adjust')): ?>
                                                <a href="../inventory/index.php?supply_product=<?php echo $prod['id']; ?>" class="btn btn-sm btn-outline-success" title="توريد مباشر للمخزون">
                                                    🚚 توريد
                                                </a>
                                            <?php endif; ?>
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
    <?php elseif ($tab === 'mods'): ?>
    <!-- Modifiers Tab: sauces/extras master data (moved from modules/products/modifiers.php).
         The cashier additions window reads this same table, so it stays linked automatically. -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="font-weight: 800; color: #1e293b; margin: 0;">🍯 إدارة الإضافات والنكهات والصوصات</h3>
            <p style="color: #64748b; margin: 4px 0 0; font-size: 13px;">إضافة وتعديل صوصات ونكهات المشروبات والحلويات المتاحة في الكاشير</p>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()" style="border-radius: 8px; font-weight: 700; background: #ea580c; border-color: #ea580c;">
            ➕ إضافة صوص / نكهة جديدة
        </button>
    </div>

    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <input type="hidden" name="tab" value="mods">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="🔍 بحث باسم الصوص أو النكهة..." value="<?php echo htmlspecialchars($modSearch); ?>">
                </div>
                <div style="width: 180px;">
                    <select name="group" class="form-select">
                        <option value="">كل الأنواع</option>
                        <option value="sauce" <?php echo $modGroup === 'sauce' ? 'selected' : ''; ?>>صوص (Sauce)</option>
                        <option value="flavor" <?php echo $modGroup === 'flavor' ? 'selected' : ''; ?>>نكهة سيرب (Flavor)</option>
                        <option value="extra" <?php echo $modGroup === 'extra' ? 'selected' : ''; ?>>إضافة عامة (Extra)</option>
                        <option value="topping" <?php echo $modGroup === 'topping' ? 'selected' : ''; ?>>توبينج (Topping)</option>
                    </select>
                </div>
                <div style="width: 200px;">
                    <select name="category_id" class="form-select">
                        <option value="">كل الأقسام</option>
                        <?php foreach ($allParents as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $modCat == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary" style="border-radius: 8px;">تصفية</button>
                    <?php if ($modSearch || $modGroup || $modCat): ?>
                        <a href="index.php?tab=mods" class="btn btn-outline-danger" style="border-radius: 8px;">إلغاء الفلتر</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-hover" style="margin: 0;">
                    <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <th style="padding: 14px 20px;">اسم الإضافة / الصوص</th>
                            <th>النوع</th>
                            <th>السعر الإضافي</th>
                            <th>مخصص لقسم</th>
                            <th>الحالة</th>
                            <th style="text-align: left; padding-left: 20px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modifiersList)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                    لا توجد إضافات تطابق معايير البحث. اضغط "➕ إضافة صوص / نكهة جديدة" للبدء.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($modifiersList as $mod): ?>
                                <tr style="vertical-align: middle;">
                                    <td style="padding: 14px 20px; font-weight: 700; color: #1e293b;">
                                        ✨ <?php echo htmlspecialchars($mod['name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 6px;">
                                            <?php
                                            $grpNames = ['sauce' => 'صوص', 'flavor' => 'سيرب/نكهة', 'extra' => 'إضافة عامة', 'topping' => 'توبينج'];
                                            echo $grpNames[$mod['modifier_group']] ?? htmlspecialchars($mod['modifier_group']);
                                            ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 800; color: #059669;">
                                        + <?php echo formatCurrency($mod['price']); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($mod['category_name'])): ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 6px;">
                                                <?php echo htmlspecialchars($mod['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 13px;">لكل الأصناف</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($mod['is_available']): ?>
                                            <span class="badge" style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 6px;">متوفر</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 6px;">غير متوفر</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: left; padding-left: 20px;">
                                        <button class="btn btn-sm btn-outline-primary" onclick='editModifier(<?php echo json_encode($mod); ?>)' style="border-radius: 6px;">
                                            ✏️ تعديل
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick='confirmDeleteModifier(<?php echo $mod['id']; ?>, <?php echo json_encode($mod['name']); ?>)' style="border-radius: 6px;">
                                            🗑️ حذف
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Add/Edit Modifier -->
    <div class="modal" id="modifierModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">إضافة صوص / إضافة جديدة</h5>
                    <button type="button" class="btn-close" onclick="closeModal()"></button>
                </div>
                <form method="POST" action="index.php?tab=mods">
                    <input type="hidden" name="mod_crud" value="1">
                    <input type="hidden" name="action" id="modalAction" value="create">
                    <input type="hidden" name="modifier_id" id="modalModifierId" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">اسم الإضافة / النكهة / الصوص *</label>
                            <input type="text" name="name" id="modalName" class="form-control" placeholder="مثال: صوص لوتس، زيادة حليب، نكهة كراميل" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600;">السعر الإضافي (ج.م) *</label>
                                <input type="number" step="0.5" name="price" id="modalPrice" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600;">النوع</label>
                                <select name="modifier_group" id="modalGroup" class="form-select">
                                    <option value="sauce">صوص (Sauce)</option>
                                    <option value="flavor">نكهة سيرب (Flavor)</option>
                                    <option value="extra">إضافة عامة (Extra)</option>
                                    <option value="topping">توبينج (Topping)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">مخصصة لقسم معين (اختياري)</label>
                            <select name="category_id" id="modalCategory" class="form-select">
                                <option value="">كل الأقسام</option>
                                <?php foreach ($allParents as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="is_available" id="modalAvailable" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
                            <label for="modalAvailable" style="margin: 0; font-weight: 600; cursor: pointer;">متوفر للطلب في الكاشير</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">إلغاء</button>
                        <button type="submit" class="btn btn-primary" style="background: #ea580c; border-color: #ea580c;">💾 حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Delete Confirmation -->
    <div class="modal" id="deleteModifierModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content">
                <div class="modal-header" style="background: #fee2e2; border-bottom: 1px solid #fecaca;">
                    <h5 class="modal-title" style="color: #991b1b;">⚠️ تأكيد الحذف</h5>
                    <button type="button" class="btn-close" onclick="closeDeleteModal()"></button>
                </div>
                <form method="POST" action="index.php?tab=mods">
                    <input type="hidden" name="mod_crud" value="1">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="modifier_id" id="deleteModId" value="">
                    <div class="modal-body" style="text-align: center; padding: 25px;">
                        <div style="font-size: 40px; margin-bottom: 10px;">🗑️</div>
                        <div style="font-size: 15px; color: #1e293b; margin-bottom: 8px;">
                            هل أنت متأكد من حذف الصوص / الإضافة:
                        </div>
                        <div id="deleteModName" style="font-size: 17px; font-weight: 800; color: #dc2626; margin-bottom: 12px;"></div>
                        <div style="font-size: 12px; color: #64748b;">
                            إذا كانت هذه الإضافة مستخدمة في فواتير سابقة، سيتم تعطيلها تلقائياً بدلاً من حذفها للحفاظ على سلامة الحسابات.
                        </div>
                    </div>
                    <div class="modal-footer" style="justify-content: center;">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">إلغاء</button>
                        <button type="submit" class="btn btn-danger">🗑️ نعم، احذف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function openAddModal() {
        document.getElementById('modalTitle').innerText = '➕ إضافة صوص / نكهة جديدة';
        document.getElementById('modalAction').value = 'create';
        document.getElementById('modalModifierId').value = '';
        document.getElementById('modalName').value = '';
        document.getElementById('modalPrice').value = '5.00';
        document.getElementById('modalGroup').value = 'sauce';
        document.getElementById('modalCategory').value = '';
        document.getElementById('modalAvailable').checked = true;
        document.getElementById('modifierModal').style.display = 'flex';
    }

    function editModifier(mod) {
        document.getElementById('modalTitle').innerText = '✏️ تعديل الإضافة: ' + mod.name;
        document.getElementById('modalAction').value = 'update';
        document.getElementById('modalModifierId').value = mod.id;
        document.getElementById('modalName').value = mod.name;
        document.getElementById('modalPrice').value = mod.price;
        document.getElementById('modalGroup').value = mod.modifier_group || 'sauce';
        document.getElementById('modalCategory').value = mod.applies_to_category_id || '';
        document.getElementById('modalAvailable').checked = parseInt(mod.is_available) === 1;
        document.getElementById('modifierModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modifierModal').style.display = 'none';
    }

    function confirmDeleteModifier(id, name) {
        document.getElementById('deleteModId').value = id;
        document.getElementById('deleteModName').innerText = name;
        document.getElementById('deleteModifierModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModifierModal').style.display = 'none';
    }
    </script>
    <?php else: ?>
    <!-- Categories Tab (merged from modules/categories) -->
    <div class="row">
        <?php if ($canManageCats): ?>
        <div class="col-lg-4 mb-4">
            <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div class="card-header" style="background: #f8fafc; font-weight: 700; border-bottom: 1px solid #e2e8f0;">
                    <?php echo $editingCategory ? '✏️ تعديل بيانات القسم' : '➕ إضافة قسم جديد للمنيو'; ?>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="POST">
                        <input type="hidden" name="cat_action" value="<?php echo $editingCategory ? 'update_cat' : 'create_cat'; ?>">
                        <?php if ($editingCategory): ?>
                            <input type="hidden" name="category_id" value="<?php echo $editingCategory['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">اسم القسم *</label>
                            <input name="name" class="form-control" required value="<?php echo htmlspecialchars($editingCategory['name'] ?? ''); ?>" placeholder="مثال: قهوة ساخنة، سموزي، وافل...">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600;">الأيقونة (إيموجي)</label>
                                <input type="text" name="icon" id="catIconInput" class="form-control" value="<?php echo htmlspecialchars($editingCategory['icon'] ?? '☕'); ?>" style="font-size: 20px; text-align: center;">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600;">اللون في الكاشير</label>
                                <input type="color" name="color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($editingCategory['color'] ?? '#ea580c'); ?>" style="height: 42px;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">ترتيب العرض</label>
                            <input type="number" min="0" name="sort_order" class="form-control" value="<?php echo (int)($editingCategory['sort_order'] ?? 0); ?>" placeholder="0">
                            <small style="color: #64748b;">الأرقام الأصغر تظهر أولاً في الكاشير</small>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="is_active" class="form-check-input" id="catIsActiveCheck" value="1" <?php echo (!isset($editingCategory) || !empty($editingCategory['is_active'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="catIsActiveCheck" style="font-weight: 600;">نشط ويظهر في الكاشير</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary w-100" type="submit" style="font-weight: 700; background: #ea580c; border-color: #ea580c;">
                                <?php echo $editingCategory ? '💾 حفظ التعديلات' : '➕ إضافة القسم'; ?>
                            </button>
                            <?php if ($editingCategory): ?>
                                <a class="btn btn-secondary" href="index.php?tab=cats">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="<?php echo $canManageCats ? 'col-lg-8' : 'col-12'; ?>">
            <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div class="card-header" style="background: #f8fafc; font-weight: 700; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <span>📋 أقسام المنيو الحالية (<?php echo $catTotal; ?>)</span>
                    <form method="GET" style="display: flex; gap: 6px;">
                        <input type="hidden" name="tab" value="cats">
                        <input type="text" name="cat_search" class="form-control form-control-sm" placeholder="🔍 بحث..." value="<?php echo htmlspecialchars($catSearch); ?>" style="width: 150px;">
                    </form>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table table-hover" style="margin: 0;">
                            <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <tr>
                                    <th style="padding: 14px 20px;">القسم</th>
                                    <th>الأيقونة واللون</th>
                                    <th>الترتيب</th>
                                    <th>عدد الأصناف</th>
                                    <th>الحالة</th>
                                    <th style="text-align: left; padding-left: 20px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categoriesList)): ?>
                                    <tr><td colspan="6" class="text-center" style="padding: 30px;">لا توجد أقسام مطابقة للبحث.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categoriesList as $cat): ?>
                                        <tr style="vertical-align: middle;">
                                            <td style="padding: 14px 20px; font-weight: 700; color: #1e293b;">
                                                <a href="index.php?category_id=<?php echo $cat['id']; ?>" style="text-decoration: none; color: inherit;">
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 22px;"><?php echo htmlspecialchars($cat['icon'] ?? '☕'); ?></span>
                                                    <span style="display: inline-block; width: 18px; height: 18px; border-radius: 50%; background: <?php echo htmlspecialchars($cat['color'] ?? '#ea580c'); ?>; border: 1px solid #cbd5e1;"></span>
                                                </div>
                                            </td>
                                            <td><?php echo (int)$cat['sort_order']; ?></td>
                                            <td>
                                                <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 13px;">
                                                    <?php echo (int)$cat['products_count']; ?> صنف
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge" style="<?php echo $cat['is_active'] ? 'background: #dcfce7; color: #166534;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                                                    <?php echo $cat['is_active'] ? 'نشط' : 'معطل'; ?>
                                                </span>
                                            </td>
                                            <td style="text-align: left; padding-left: 20px;">
                                                <?php if ($canManageCats): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="index.php?tab=cats&edit_cat=<?php echo $cat['id']; ?>" style="border-radius: 6px;">✏️ تعديل</a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick='confirmDeleteCategory(<?php echo $cat['id']; ?>, <?php echo json_encode($cat['name']); ?>, <?php echo (int)$cat['products_count']; ?>)' style="border-radius: 6px;" <?php echo $cat['products_count'] > 0 ? 'disabled title="لا يمكن حذف قسم به أصناف"' : ''; ?>>
                                                        🗑️ حذف
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($catPages > 1): ?>
                    <?php $catQuery = '&tab=cats' . ($catSearch !== '' ? '&cat_search=' . urlencode($catSearch) : ''); ?>
                    <div style="display:flex; justify-content:center; align-items:center; gap:6px; flex-wrap:wrap; padding:14px 10px 4px; direction:rtl;">
                        <?php if ($catPage > 1): ?>
                            <a href="?cpage=<?php echo $catPage - 1 . $catQuery; ?>" class="page-link" style="font-size:12px;">السابق</a>
                        <?php endif; ?>
                        <?php for ($cp = 1; $cp <= $catPages; $cp++): ?>
                            <a href="?cpage=<?php echo $cp . $catQuery; ?>" class="page-link <?php echo $cp === $catPage ? 'active' : ''; ?>" style="min-width:32px; font-size:12px;"><?php echo $cp; ?></a>
                        <?php endfor; ?>
                        <?php if ($catPage < $catPages): ?>
                            <a href="?cpage=<?php echo $catPage + 1 . $catQuery; ?>" class="page-link" style="font-size:12px;">التالي</a>
                        <?php endif; ?>
                    </div>
                    <div style="text-align:center; color:#64748b; font-size:11px; padding:4px 0 10px;">صفحة <?php echo $catPage; ?> من <?php echo $catPages; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Delete Category Confirmation -->
<div class="modal" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #fee2e2; border-bottom: 1px solid #fecaca;">
                <h5 class="modal-title" style="color: #991b1b;">⚠️ تأكيد حذف القسم</h5>
                <button type="button" class="btn-close" onclick="closeDeleteCatModal()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="cat_action" value="delete_cat">
                <input type="hidden" name="category_id" id="deleteCatId" value="">
                <div class="modal-body" style="text-align: center; padding: 25px;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🗑️</div>
                    <div style="font-size: 15px; color: #1e293b; margin-bottom: 8px;">
                        هل أنت متأكد من حذف قسم المنيو:
                    </div>
                    <div id="deleteCatName" style="font-size: 17px; font-weight: 800; color: #dc2626; margin-bottom: 12px;"></div>
                    <div style="font-size: 12px; color: #64748b;">
                        لا يمكن التراجع عن هذا الإجراء.
                    </div>
                </div>
                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteCatModal()">إلغاء</button>
                    <button type="submit" class="btn btn-danger">🗑️ نعم، احذف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDeleteCategory(id, name, count) {
    if (count > 0) {
        appAlert('لا يمكن حذف القسم لأنه يحتوي على ' + count + ' صنف. يرجى نقل الأصناف أو تعديلها أولاً.');
        return;
    }
    document.getElementById('deleteCatId').value = id;
    document.getElementById('deleteCatName').innerText = name;
    document.getElementById('deleteCategoryModal').style.display = 'flex';
}

function closeDeleteCatModal() {
    document.getElementById('deleteCategoryModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    var m = document.getElementById('deleteCategoryModal');
    if (m && e.target == m) {
        closeDeleteCatModal();
    }
});
</script>
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
