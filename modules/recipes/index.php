<?php
/**
 * Recipes & Raw Materials Inventory (إدارة الوصفات وتكاليف المواد الخام)
 * نظام كافيه عروسة النيل
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('recipes.view');

$pageTitle = 'المواد الخام والوصفات (Recipes)';

$currentTab = $_GET['tab'] ?? 'ingredients';

// Handle Add / Edit / Delete Ingredient & Stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requirePermission('recipes.manage');
    $action = $_POST['action'];

    if ($action === 'save_ingredient') {
        $id = (int)($_POST['ingredient_id'] ?? 0);
        $name = sanitize(trim($_POST['name'] ?? ''));
        $unit = sanitize(trim($_POST['base_unit'] ?? 'g'));
        $category = sanitize(trim($_POST['category'] ?? 'حبوب وبودرة'));
        $currentStock = floatval($_POST['current_stock'] ?? 0);
        $minStock = floatval($_POST['min_stock_level'] ?? 10);
        $costPerUnit = floatval($_POST['cost_per_unit'] ?? 0);

        if (empty($name)) {
            setError('يرجى كتابة اسم المادة الخام');
        } else {
            if ($id > 0) {
                execute("
                    UPDATE ingredients 
                    SET name = ?, base_unit = ?, category = ?, min_stock_level = ?, weighted_avg_cost = ?
                    WHERE id = ?
                ", [$name, $unit, $category, $minStock, $costPerUnit, $id]);
                logActivity('تعديل مادة خام', "تم تعديل المادة الخام: $name");
                setSuccess('تم تحديث بيانات المادة الخام بنجاح');
            } else {
                $sku = 'ING-' . str_pad(rand(10, 999), 3, '0', STR_PAD_LEFT);
                $newId = insert("
                    INSERT INTO ingredients (name, sku, base_unit, category, current_stock, min_stock_level, weighted_avg_cost)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [$name, $sku, $unit, $category, $currentStock, $minStock, $costPerUnit]);

                if ($newId && $currentStock > 0) {
                    insert("
                        INSERT INTO ingredient_stock_movements (ingredient_id, movement_type, quantity, unit_cost, balance_after, reason, created_by)
                        VALUES (?, 'opening', ?, ?, ?, 'رصيد افتتاحي', ?)
                    ", [$newId, $currentStock, $costPerUnit, $currentStock, getCurrentUserId()]);
                }
                logActivity('إضافة مادة خام', "تم إضافة مادة خام جديدة: $name");
                setSuccess('تم إضافة المادة الخام بنجاح');
            }
        }
        redirect('index.php?tab=ingredients');
    }

    if ($action === 'delete_ingredient') {
        $id = (int)($_POST['ingredient_id'] ?? 0);
        if ($id > 0) {
            $isUsedInRecipe = getRow("SELECT id FROM recipe_items WHERE ingredient_id = ? LIMIT 1", [$id]);
            if ($isUsedInRecipe) {
                setError('لا يمكن حذف المادة الخام لأنها مستخدمة داخل مقادير وصفات مشروبات مسجلة.');
            } else {
                execute("DELETE FROM ingredient_stock_movements WHERE ingredient_id = ?", [$id]);
                execute("DELETE FROM ingredients WHERE id = ?", [$id]);
                logActivity('حذف مادة خام', "تم حذف مادة خام رقم #$id");
                setSuccess('تم حذف المادة الخام وسجل حركاتها بنجاح');
            }
        }
        redirect('index.php?tab=ingredients');
    }

    if ($action === 'adjust_stock') {
        $ingId = (int)$_POST['ingredient_id'];
        $qty = floatval($_POST['adjustment_quantity'] ?? 0);
        $type = sanitize(trim($_POST['movement_type'] ?? 'adjustment'));
        $reason = sanitize(trim($_POST['reason'] ?? ''));
        $price = max(0, floatval($_POST['unit_price'] ?? 0));
        $userId = getCurrentUserId();

        $ing = getRow("SELECT * FROM ingredients WHERE id = ?", [$ingId]);
        if ($ing && $qty > 0) {
            $newBalance = $ing['current_stock'];
            $moveCost = floatval($ing['weighted_avg_cost'] ?? 0);
            if ($type === 'purchase' || $type === 'opening') {
                $newBalance += $qty;
                // Direct-supply valuation: weighted average cost
                if ($price > 0) {
                    $oldStock = floatval($ing['current_stock']);
                    $denom = $oldStock + $qty;
                    $newAvg = $denom > 0 ? (($oldStock * $moveCost + $qty * $price) / $denom) : $price;
                    execute("UPDATE ingredients SET weighted_avg_cost = ? WHERE id = ?", [$newAvg, $ingId]);
                    $moveCost = $price;
                }
            } elseif ($type === 'waste' || $type === 'consumption') {
                $newBalance = max(0, $newBalance - $qty);
            } elseif ($type === 'adjustment') {
                $newBalance = $qty; // Set exact balance
            }

            execute("UPDATE ingredients SET current_stock = ? WHERE id = ?", [$newBalance, $ingId]);
            insert("
                INSERT INTO ingredient_stock_movements (ingredient_id, movement_type, quantity, unit_cost, balance_after, reason, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$ingId, $type, $qty, $moveCost, $newBalance, $reason, $userId]);

            setSuccess('تم تسجيل حركة المخزون وتحديث الرصيد بنجاح');
        }
        redirect('index.php?tab=ingredients');
    }

    if ($action === 'save_recipe') {
        $productId = (int)$_POST['product_id'];
        $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
        $notes = sanitize(trim($_POST['notes'] ?? ''));

        // Delete existing recipe for this product/variant
        if ($variantId) {
            execute("DELETE FROM recipes WHERE product_id = ? AND variant_id = ?", [$productId, $variantId]);
        } else {
            execute("DELETE FROM recipes WHERE product_id = ? AND variant_id IS NULL", [$productId]);
        }

        $recipeId = insert("
            INSERT INTO recipes (product_id, variant_id, notes)
            VALUES (?, ?, ?)
        ", [$productId, $variantId, $notes]);

        if ($recipeId && isset($_POST['items_ingredient_id']) && is_array($_POST['items_ingredient_id'])) {
            $ingIds = $_POST['items_ingredient_id'];
            $quantities = $_POST['items_quantity'];
            $units = $_POST['items_unit'];

            $calculatedCost = 0;
            foreach ($ingIds as $i => $ingId) {
                $ingId = (int)$ingId;
                $q = floatval($quantities[$i] ?? 0);
                $u = sanitize(trim($units[$i] ?? 'g'));

                if ($ingId > 0 && $q > 0) {
                    insert("
                        INSERT INTO recipe_items (recipe_id, ingredient_id, quantity, unit)
                        VALUES (?, ?, ?, ?)
                    ", [$recipeId, $ingId, $q, $u]);

                    $ingCost = getRow("SELECT weighted_avg_cost FROM ingredients WHERE id = ?", [$ingId])['weighted_avg_cost'] ?? 0;
                    $calculatedCost += ($ingCost * $q);
                }
            }

            // Automatically update product cost_price
            if ($calculatedCost > 0) {
                execute("UPDATE products SET cost_price = ? WHERE id = ?", [$calculatedCost, $productId]);
            }

            setSuccess('تم حفظ مقادير الوصفة وحساب التكلفة التقديرية بنجاح');
        }
        redirect('index.php?tab=recipes');
    }

    if ($action === 'delete_recipe') {
        $recipeId = (int)($_POST['recipe_id'] ?? 0);
        if ($recipeId > 0) {
            execute("DELETE FROM recipes WHERE id = ?", [$recipeId]);
            setSuccess('تم حذف وصفة المشروب بنجاح');
        }
        redirect('index.php?tab=recipes');
    }
}

// Search and filter handling
$search = trim($_GET['search'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$paginationBaseQuery = '&tab=' . urlencode($currentTab)
    . ($search !== '' ? '&search=' . urlencode($search) : '')
    . ($filterCategory !== '' ? '&category=' . urlencode($filterCategory) : '');

// Reusable pagination renderer (matches the sleek style used across the system)
$renderPagination = function ($currentPage, $totalPages, $totalItems, $baseQuery, $itemLabel = 'عنصر') {
    if ($totalPages <= 1) return;
    $activeStyle = 'display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 36px !important; height: 36px !important; padding: 0 10px !important; border-radius: 8px !important; text-decoration: none !important; border: 1px solid #ea580c !important; background: #ea580c !important; color: #fff !important; font-weight: 800 !important;';
    $normalStyle = 'display: inline-flex !important; align-items: center !important; justify-content: center !important; min-width: 36px !important; height: 36px !important; padding: 0 10px !important; border-radius: 8px !important; text-decoration: none !important; border: 1px solid #cbd5e1 !important; background: #fff !important; color: #334155 !important; font-weight: 700 !important;';
    $arrowStyle = 'display: inline-flex !important; align-items: center !important; justify-content: center !important; height: 36px !important; padding: 0 14px !important; font-size: 13px !important; font-weight: 700 !important; border-radius: 8px !important; border: 1px solid #cbd5e1 !important; text-decoration: none !important;';
    $startPage = max(1, $currentPage - 3);
    $endPage = min($totalPages, $currentPage + 3);
    ?>
    <div style="padding: 16px 22px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 12px; background: #fafbfc;">
        <div style="color: #64748b; font-size: 13.5px; font-weight: 600;">
            عرض صفحة <span style="color: #0f172a; font-weight: 800;"><?php echo $currentPage; ?></span> من إجمالي <span style="color: #0f172a; font-weight: 800;"><?php echo $totalPages; ?></span> (<?php echo $totalItems; ?> <?php echo $itemLabel; ?>)
        </div>
        <nav aria-label="تنقل الصفحات">
            <ul class="pagination" style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: center !important; gap: 5px !important;">
                <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>" style="list-style: none !important; margin: 0 !important; padding: 0 !important;">
                    <a class="page-link" href="<?php echo ($currentPage > 1) ? '?page=' . ($currentPage - 1) . $baseQuery : '#'; ?>" style="<?php echo $arrowStyle; ?> <?php echo ($currentPage <= 1) ? 'color: #94a3b8 !important; background: #f1f5f9 !important; pointer-events: none !important;' : 'color: #334155 !important; background: #fff !important;'; ?>">← السابق</a>
                </li>
                <?php
                if ($startPage > 1) {
                    echo '<li style="list-style: none !important;"><a class="page-link" href="?page=1' . $baseQuery . '" style="' . $normalStyle . '">1</a></li>';
                    if ($startPage > 2) echo '<li style="list-style: none !important; color: #94a3b8; padding: 0 4px;">…</li>';
                }
                for ($i = $startPage; $i <= $endPage; $i++) {
                    echo '<li style="list-style: none !important;"><a class="page-link" href="?page=' . $i . $baseQuery . '" style="' . ($i == $currentPage ? $activeStyle : $normalStyle) . '">' . $i . '</a></li>';
                }
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) echo '<li style="list-style: none !important; color: #94a3b8; padding: 0 4px;">…</li>';
                    echo '<li style="list-style: none !important;"><a class="page-link" href="?page=' . $totalPages . $baseQuery . '" style="' . $normalStyle . '">' . $totalPages . '</a></li>';
                }
                ?>
                <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>" style="list-style: none !important; margin: 0 !important; padding: 0 !important;">
                    <a class="page-link" href="<?php echo ($currentPage < $totalPages) ? '?page=' . ($currentPage + 1) . $baseQuery : '#'; ?>" style="<?php echo $arrowStyle; ?> <?php echo ($currentPage >= $totalPages) ? 'color: #94a3b8 !important; background: #f1f5f9 !important; pointer-events: none !important;' : 'color: #334155 !important; background: #fff !important;'; ?>">التالي →</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php
};

$ingWhere = "WHERE 1=1";
$ingParams = [];
if ($search !== '') {
    $ingWhere .= " AND name LIKE ?";
    $ingParams[] = "%$search%";
}
if ($filterCategory !== '') {
    $ingWhere .= " AND category = ?";
    $ingParams[] = $filterCategory;
}

// Fetch Ingredients
$ingredients = getRows("SELECT id, name, sku, base_unit, category, current_stock, min_stock_level, weighted_avg_cost, base_unit as unit, weighted_avg_cost as cost_per_unit FROM ingredients $ingWhere ORDER BY category ASC, name ASC", $ingParams);
$allCategories = getRows("SELECT DISTINCT category FROM ingredients WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");

// Paginate ingredients for table display (full list $ingredients is kept for the recipe modal dropdown)
$totalIngredients = count($ingredients);
$ingTotalPages = max(1, (int) ceil($totalIngredients / $perPage));
$ingPage = min($page, $ingTotalPages);
$ingOffset = ($ingPage - 1) * $perPage;
$pagedIngredients = array_slice($ingredients, $ingOffset, $perPage);

// Fetch Recipes with Product & Variant info
$recipesWhere = "";
$recipesParams = [];
if ($search !== '') {
    $recipesWhere = "WHERE p.name LIKE ? OR pv.variant_name LIKE ?";
    $recipesParams = ["%$search%", "%$search%"];
}

$recipesFrom = "FROM recipes r JOIN products p ON p.id = r.product_id LEFT JOIN product_variants pv ON pv.id = r.variant_id $recipesWhere";
$totalRecipes = (int) (getRow("SELECT COUNT(*) as cnt $recipesFrom", $recipesParams)['cnt'] ?? 0);
$recipesTotalPages = max(1, (int) ceil($totalRecipes / $perPage));
$recipesPage = min($page, $recipesTotalPages);
$recipesOffset = ($recipesPage - 1) * $perPage;

$recipes = getRows("
    SELECT r.*, p.name as product_name, p.price as product_price, p.code as product_code,
           pv.variant_name, pv.final_price as variant_price,
           (SELECT COUNT(*) FROM recipe_items ri WHERE ri.recipe_id = r.id) as items_count,
           (SELECT SUM(ri.quantity * ing.weighted_avg_cost) 
            FROM recipe_items ri 
            JOIN ingredients ing ON ing.id = ri.ingredient_id 
            WHERE ri.recipe_id = r.id) as recipe_cost
    FROM recipes r
    JOIN products p ON p.id = r.product_id
    LEFT JOIN product_variants pv ON pv.id = r.variant_id
    $recipesWhere
    ORDER BY p.name ASC
    LIMIT $perPage OFFSET $recipesOffset
", $recipesParams);

// Fetch Products for Recipe Modal
$allProducts = getRows("SELECT id, name, code, has_variants, price FROM products ORDER BY name ASC");

// Fetch Movements Log
$movementsWhere = "";
$movementsParams = [];
if ($search !== '') {
    $movementsWhere = "WHERE ing.name LIKE ? OR m.reason LIKE ?";
    $movementsParams = ["%$search%", "%$search%"];
}

$movFrom = "FROM ingredient_stock_movements m JOIN ingredients ing ON ing.id = m.ingredient_id $movementsWhere";
$totalMovements = (int) (getRow("SELECT COUNT(*) as cnt $movFrom", $movementsParams)['cnt'] ?? 0);
$movTotalPages = max(1, (int) ceil($totalMovements / $perPage));
$movPage = min($page, $movTotalPages);
$movOffset = ($movPage - 1) * $perPage;

$movements = getRows("
    SELECT m.*, ing.name as ingredient_name, ing.base_unit as ingredient_unit, u.full_name as user_name
    FROM ingredient_stock_movements m
    JOIN ingredients ing ON ing.id = m.ingredient_id
    LEFT JOIN users u ON u.id = m.created_by
    $movementsWhere
    ORDER BY m.id DESC LIMIT $perPage OFFSET $movOffset
", $movementsParams);

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container-fluid" style="padding: 15px 25px;">
    <!-- Top Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
                <span>🧪</span> مخزون المواد الخام والوصفات (Recipes)
            </h2>
            <p style="color: #64748b; margin: 4px 0 0;">إدارة خامات القهوة والألبان والمقادير لكل مشروب وحساب التكلفة الدقيقة للكوب</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="../../index.php" class="btn btn-secondary" style="border-radius: 8px;">
                🏠 الرئيسية
            </a>
            <button class="btn btn-primary" onclick="openIngredientModal()" style="border-radius: 8px; font-weight: 700; background: #ea580c; border-color: #ea580c;">
                ➕ إضافة مادة خام جديدة
            </button>
            <button class="btn btn-outline-primary" onclick="openRecipeModal()" style="border-radius: 8px; font-weight: 700;">
                📜 إنشاء وصفة لمشروب
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div style="margin-bottom: 20px;">
        <div style="background: white; padding: 6px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: inline-flex; gap: 6px;">
            <a class="nav-link" href="?tab=ingredients" style="<?php echo $currentTab === 'ingredients' ? 'background: #ea580c; color: white;' : 'color: #475569;'; ?> font-weight: 700; border-radius: 8px;">
                📦 المواد الخام والمخزون (<?php echo count($ingredients); ?>)
            </a>
            <a class="nav-link" href="?tab=recipes" style="<?php echo $currentTab === 'recipes' ? 'background: #ea580c; color: white;' : 'color: #475569;'; ?> font-weight: 700; border-radius: 8px;">
                📖 مقادير المشروبات (<?php echo $totalRecipes; ?>)
            </a>
            <a class="nav-link" href="?tab=movements" style="<?php echo $currentTab === 'movements' ? 'background: #ea580c; color: white;' : 'color: #475569;'; ?> font-weight: 700; border-radius: 8px;">
                📊 سجل حركات المخزون والهالك
            </a>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px;">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($currentTab); ?>">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="🔍 بحث سريع في هذه الصفحة..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <?php if ($currentTab === 'ingredients'): ?>
                <div style="width: 200px;">
                    <select name="category" class="form-select">
                        <option value="">كل التصنيفات</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $filterCategory === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <button type="submit" class="btn btn-secondary" style="border-radius: 8px;">تصفية</button>
                    <?php if ($search || $filterCategory): ?>
                        <a href="?tab=<?php echo htmlspecialchars($currentTab); ?>" class="btn btn-outline-danger" style="border-radius: 8px;">إلغاء الفلتر</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab 1: Ingredients & Stock -->
    <?php if ($currentTab === 'ingredients'): ?>
        <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table table-hover" style="margin: 0;">
                        <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <tr>
                                <th style="padding: 14px 20px;">المادة الخام</th>
                                <th>التصنيف</th>
                                <th>الرصيد الحالي</th>
                                <th>وحدة القياس</th>
                                <th>تكلفة الوحدة</th>
                                <th>حالة المخزون</th>
                                <th style="text-align: left; padding-left: 20px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pagedIngredients)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        لا توجد مواد خام تطابق معايير البحث. اضغط "➕ إضافة مادة خام جديدة" لبدء تسجيل البن والحليب والمواد المستهلكة.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pagedIngredients as $ing): ?>
                                    <?php 
                                        $isLow = ($ing['current_stock'] <= $ing['min_stock_level']);
                                        $isOut = ($ing['current_stock'] <= 0);
                                    ?>
                                    <tr style="vertical-align: middle;">
                                        <td style="padding: 14px 20px; font-weight: 700; color: #1e293b;">
                                            🌾 <?php echo htmlspecialchars($ing['name']); ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: #f1f5f9; color: #475569; padding: 5px 10px; border-radius: 6px;">
                                                <?php echo htmlspecialchars($ing['category'] ?? 'عام'); ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 800; font-size: 16px; color: <?php echo $isOut ? '#dc2626' : ($isLow ? '#d97706' : '#1e293b'); ?>;">
                                            <?php echo number_format($ing['current_stock'], 2); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($ing['base_unit']); ?></td>
                                        <td style="font-weight: 600; color: #059669;">
                                            <?php echo formatCurrency($ing['weighted_avg_cost']); ?> / <?php echo htmlspecialchars($ing['base_unit']); ?>
                                        </td>
                                        <td>
                                            <?php if ($isOut): ?>
                                                <span class="badge" style="background: #fee2e2; color: #991b1b; padding: 5px 10px; border-radius: 6px;">🚫 نفذ من المخزن</span>
                                            <?php elseif ($isLow): ?>
                                                <span class="badge" style="background: #fef3c7; color: #92400e; padding: 5px 10px; border-radius: 6px;">⚠️ مخزون منخفض</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #dcfce7; color: #166534; padding: 5px 10px; border-radius: 6px;">✅ متوفر وآمن</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: left; padding-left: 20px;">
                                            <button class="btn btn-sm btn-outline-success" onclick='openStockModal(<?php echo json_encode($ing); ?>)' style="border-radius: 6px;">
                                                🚚 توريد مباشر
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" onclick='editIngredient(<?php echo json_encode($ing); ?>)' style="border-radius: 6px;">
                                                ✏️ تعديل
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick='confirmDeleteIngredient(<?php echo $ing['id']; ?>, <?php echo json_encode($ing['name']); ?>)' style="border-radius: 6px;">
                                                🗑️ حذف
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $renderPagination($ingPage, $ingTotalPages, $totalIngredients, $paginationBaseQuery, 'مادة خام'); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 2: Recipes -->
    <?php if ($currentTab === 'recipes'): ?>
        <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table table-hover" style="margin: 0;">
                        <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <tr>
                                <th style="padding: 14px 20px;">مشروب المنيو</th>
                                <th>الحجم / الخيار</th>
                                <th>عدد المكونات</th>
                                <th>تكلفة الكوب المقدرة</th>
                                <th>سعر البيع للزبون</th>
                                <th>هامش الربح</th>
                                <th style="text-align: left; padding-left: 20px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recipes)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        لم يتم تسجيل مقادير بعد تطابق البحث. اضغط "📜 إنشاء وصفة لمشروب" لربط المشروبات بالمواد الخام وحساب تكلفة التحضير.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recipes as $rcp): ?>
                                    <?php 
                                        $salePrice = $rcp['variant_price'] ?? $rcp['product_price'];
                                        $cost = floatval($rcp['recipe_cost'] ?? 0);
                                        $profit = $salePrice - $cost;
                                        $profitMargin = $salePrice > 0 ? round(($profit / $salePrice) * 100) : 0;
                                    ?>
                                    <tr style="vertical-align: middle;">
                                        <td style="padding: 14px 20px; font-weight: 700; color: #1e293b;">
                                            ☕ <?php echo htmlspecialchars($rcp['product_name']); ?>
                                        </td>
                                        <td>
                                            <?php if ($rcp['variant_name']): ?>
                                                <span class="badge" style="background: #ede9fe; color: #6d28d9; padding: 4px 8px; border-radius: 6px;">
                                                    <?php echo htmlspecialchars($rcp['variant_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #64748b; font-size: 13px;">حجم قياسي</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: #f1f5f9; color: #334155; padding: 4px 8px; border-radius: 6px;">
                                                <?php echo $rcp['items_count']; ?> خامات
                                            </span>
                                        </td>
                                        <td style="font-weight: 700; color: #d97706;">
                                            <?php echo formatCurrency($cost); ?>
                                        </td>
                                        <td style="font-weight: 800; color: #1e293b;">
                                            <?php echo formatCurrency($salePrice); ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: #dcfce7; color: #166534; padding: 5px 10px; border-radius: 6px; font-weight: 700;">
                                                <?php echo $profitMargin; ?>% ربح (+<?php echo formatCurrency($profit); ?>)
                                            </span>
                                        </td>
                                        <td style="text-align: left; padding-left: 20px;">
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick='confirmDeleteRecipe(<?php echo $rcp['id']; ?>, <?php echo json_encode($rcp['product_name']); ?>)' style="border-radius: 6px;">
                                                🗑️ حذف الوصفة
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $renderPagination($recipesPage, $recipesTotalPages, $totalRecipes, $paginationBaseQuery, 'وصفة'); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 3: Stock Movements Log -->
    <?php if ($currentTab === 'movements'): ?>
        <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table table-hover" style="margin: 0;">
                        <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <tr>
                                <th style="padding: 14px 20px;">التاريخ والوقت</th>
                                <th>المادة الخام</th>
                                <th>نوع الحركة</th>
                                <th>الكمية</th>
                                <th>الرصيد بعدها</th>
                                <th>البيان / السبب</th>
                                <th>المسؤول</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movements)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">لا توجد حركات مسجلة.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($movements as $m): ?>
                                    <tr style="vertical-align: middle;">
                                        <td style="padding: 14px 20px; font-size: 13px; color: #64748b;">
                                            <?php echo date('Y-m-d H:i', strtotime($m['created_at'])); ?>
                                        </td>
                                        <td style="font-weight: 700; color: #1e293b;">
                                            <?php echo htmlspecialchars($m['ingredient_name']); ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $badges = [
                                                    'purchase' => ['توريد مشتريات', '#dcfce7', '#166534'],
                                                    'waste' => ['هالك وتالف', '#fee2e2', '#991b1b'],
                                                    'adjustment' => ['تسوية وجرد', '#e0e7ff', '#3730a3'],
                                                    'consumption' => ['استهلاك طلبات', '#fef3c7', '#92400e'],
                                                    'opening' => ['رصيد افتتاحي', '#f1f5f9', '#475569'],
                                                ];
                                                $b = $badges[$m['movement_type']] ?? [$m['movement_type'], '#f1f5f9', '#334155'];
                                            ?>
                                            <span class="badge" style="background: <?php echo $b[1]; ?>; color: <?php echo $b[2]; ?>; padding: 4px 8px; border-radius: 6px;">
                                                <?php echo $b[0]; ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 800;">
                                            <?php echo number_format($m['quantity'], 2); ?> <?php echo htmlspecialchars($m['ingredient_unit']); ?>
                                        </td>
                                        <td style="font-weight: 700; color: #059669;">
                                            <?php echo number_format($m['balance_after'], 2); ?>
                                        </td>
                                        <td style="color: #64748b; font-size: 13px;">
                                            <?php echo htmlspecialchars($m['reason'] ?? '—'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($m['user_name'] ?? 'النظام'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $renderPagination($movPage, $movTotalPages, $totalMovements, $paginationBaseQuery, 'حركة'); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal 1: Add/Edit Ingredient -->
<div class="modal" id="ingredientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ingModalTitle">➕ إضافة مادة خام جديدة</h5>
                <button type="button" class="btn-close" onclick="closeModals()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save_ingredient">
                <input type="hidden" name="ingredient_id" id="ingModalId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">اسم المادة الخام *</label>
                        <input type="text" name="name" id="ingModalName" class="form-control" placeholder="مثال: بن اسبريسو برازيلي، حليب مراعي كامل الدسم" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">التصنيف</label>
                            <select name="category" id="ingModalCategory" class="form-select">
                                <option value="حبوب وبودرة">حبوب وبودرة (بن، كاكاو، سكر)</option>
                                <option value="ألبان وسوائل">ألبان وسوائل (حليب، سيرب)</option>
                                <option value="صوصات وتوبينج">صوصات وتوبينج</option>
                                <option value="تعبئة وتغليف">تعبئة وتغليف (أكواب، شاليموه)</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">وحدة القياس *</label>
                            <select name="base_unit" id="ingModalUnit" class="form-select">
                                <option value="g">جرام (g)</option>
                                <option value="kg">كيلوجرام (kg)</option>
                                <option value="ml">مليلتر (ml)</option>
                                <option value="l">لتر (L)</option>
                                <option value="piece">قطعة / كوب (piece)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">تكلفة الوحدة (ج.م) *</label>
                            <input type="number" step="0.001" name="cost_per_unit" id="ingModalCost" class="form-control" placeholder="0.50" required>
                            <small style="color: #64748b;">سعر الجرام أو الملي أو القطعة</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">حد الطلب (تنبيه النقص)</label>
                            <input type="number" step="0.1" name="min_stock_level" id="ingModalMin" class="form-control" value="10">
                        </div>
                    </div>
                    <div class="mb-3" id="initialStockDiv">
                        <label class="form-label" style="font-weight: 600;">الرصيد الافتتاحي الأولي</label>
                        <input type="number" step="0.1" name="current_stock" id="ingModalStock" class="form-control" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModals()">إلغاء</button>
                    <button type="submit" class="btn btn-primary" style="background: #ea580c; border-color: #ea580c;">💾 حفظ المادة الخام</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Adjust Stock -->
<div class="modal" id="stockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🚚 توريد مباشر: <span id="stockIngName"></span></h5>
                <button type="button" class="btn-close" onclick="closeModals()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="ingredient_id" id="stockIngId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">نوع الحركة *</label>
                        <select name="movement_type" class="form-select" required>
                            <option value="purchase">📥 توريد مشتريات جديدة (+ إضافة للرصيد)</option>
                            <option value="waste">🗑️ هالك أو مسكوب (- خصم من الرصيد)</option>
                            <option value="adjustment">⚖️ جرد فعلي (تعديل الرصيد إلى القيمة المدخلة)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">الكمية * (<span id="stockIngUnit"></span>)</label>
                        <input type="number" step="0.1" name="adjustment_quantity" class="form-control" required placeholder="أدخل الكمية">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">سعر الوحدة (اختياري — يحدّث متوسط التكلفة)</label>
                        <input type="number" step="0.01" min="0" name="unit_price" class="form-control" placeholder="مثال: 12.50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">البيان / ملاحظات</label>
                        <input type="text" name="reason" class="form-control" placeholder="فاتورة مشتريات رقم...، أو سبب الهالك">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModals()">إلغاء</button>
                    <button type="submit" class="btn btn-success">✅ تأكيد الحركة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Create Recipe -->
<div class="modal" id="recipeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📜 إنشاء / تعديل مقادير مشروب (Recipe)</h5>
                <button type="button" class="btn-close" onclick="closeModals()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save_recipe">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label" style="font-weight: 600;">اختر المشروب من المنيو *</label>
                            <select name="product_id" id="recipeProductSelect" class="form-select" required onchange="loadProductVariants(this.value)">
                                <option value="">-- اختر المشروب --</option>
                                <?php foreach ($allProducts as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" data-has-variants="<?php echo $p['has_variants']; ?>">
                                        <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['code']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label" style="font-weight: 600;">الحجم (اختياري)</label>
                            <select name="variant_id" id="recipeVariantSelect" class="form-select">
                                <option value="">لكل الأحجام / حجم موحد</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <label class="form-label" style="font-weight: 700;">المكونات والمقادير لكل كوب/طلب:</label>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th>المادة الخام</th>
                                        <th>الكمية المستهلكة</th>
                                        <th>الوحدة</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="recipeItemsList">
                                    <tr>
                                        <td>
                                            <select name="items_ingredient_id[]" class="form-select" required>
                                                <option value="">-- اختر المادة --</option>
                                                <?php foreach ($ingredients as $ing): ?>
                                                    <option value="<?php echo $ing['id']; ?>"><?php echo htmlspecialchars($ing['name']); ?> (<?php echo $ing['base_unit']; ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" step="0.01" name="items_quantity[]" class="form-control" placeholder="مثال: 18" required></td>
                                        <td>
                                            <select name="items_unit[]" class="form-select">
                                                <option value="g">جرام</option>
                                                <option value="ml">مليلتر</option>
                                                <option value="piece">قطعة/كوب</option>
                                            </select>
                                        </td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">✕</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRecipeItemRow()">
                            ➕ إضافة مكون آخر
                        </button>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" style="font-weight: 600;">ملاحظات التحضير</label>
                        <input type="text" name="notes" class="form-control" placeholder="طريقة الاستخلاص أو درجة الطحن">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModals()">إلغاء</button>
                    <button type="submit" class="btn btn-primary" style="background: #ea580c; border-color: #ea580c;">💾 حفظ الوصفة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Delete Confirmation Modal -->
<div class="modal" id="deleteIngredientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #fee2e2; border-bottom: 1px solid #fecaca;">
                <h5 class="modal-title" style="color: #991b1b;">⚠️ تأكيد الحذف</h5>
                <button type="button" class="btn-close" onclick="closeModals()"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="deleteFormAction" value="delete_ingredient">
                <input type="hidden" name="ingredient_id" id="deleteIngId" value="">
                <input type="hidden" name="recipe_id" id="deleteRecipeId" value="">
                <div class="modal-body" style="text-align: center; padding: 25px;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🗑️</div>
                    <div style="font-size: 15px; color: #1e293b; margin-bottom: 8px;" id="deletePromptText">
                        هل أنت متأكد من الحذف؟
                    </div>
                    <div id="deleteItemTitle" style="font-size: 17px; font-weight: 800; color: #dc2626; margin-bottom: 12px;"></div>
                    <div style="font-size: 12px; color: #64748b;">
                        لا يمكن التراجع عن هذه الخطوة.
                    </div>
                </div>
                <div class="modal-footer" style="justify-content: center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModals()">إلغاء</button>
                    <button type="submit" class="btn btn-danger">🗑️ نعم، احذف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openIngredientModal() {
    document.getElementById('ingModalTitle').innerText = '➕ إضافة مادة خام جديدة';
    document.getElementById('ingModalId').value = '';
    document.getElementById('ingModalName').value = '';
    document.getElementById('ingModalCost').value = '';
    document.getElementById('ingModalMin').value = '10';
    document.getElementById('ingModalStock').value = '0';
    document.getElementById('initialStockDiv').style.display = 'block';
    document.getElementById('ingredientModal').style.display = 'flex';
}

function editIngredient(ing) {
    document.getElementById('ingModalTitle').innerText = '✏️ تعديل مادة خام: ' + ing.name;
    document.getElementById('ingModalId').value = ing.id;
    document.getElementById('ingModalName').value = ing.name;
    document.getElementById('ingModalCategory').value = ing.category || 'حبوب وبودرة';
    document.getElementById('ingModalUnit').value = ing.base_unit || 'g';
    document.getElementById('ingModalCost').value = ing.weighted_avg_cost;
    document.getElementById('ingModalMin').value = ing.min_stock_level;
    document.getElementById('initialStockDiv').style.display = 'none';
    document.getElementById('ingredientModal').style.display = 'flex';
}

function openStockModal(ing) {
    document.getElementById('stockIngId').value = ing.id;
    document.getElementById('stockIngName').innerText = ing.name;
    document.getElementById('stockIngUnit').innerText = ing.base_unit;
    document.getElementById('stockModal').style.display = 'flex';
}

function openRecipeModal() {
    document.getElementById('recipeModal').style.display = 'flex';
}

function confirmDeleteIngredient(id, name) {
    document.getElementById('deleteFormAction').value = 'delete_ingredient';
    document.getElementById('deleteIngId').value = id;
    document.getElementById('deleteRecipeId').value = '';
    document.getElementById('deletePromptText').innerText = 'هل أنت متأكد من حذف المادة الخام:';
    document.getElementById('deleteItemTitle').innerText = name;
    document.getElementById('deleteIngredientModal').style.display = 'flex';
}

function confirmDeleteRecipe(id, name) {
    document.getElementById('deleteFormAction').value = 'delete_recipe';
    document.getElementById('deleteIngId').value = '';
    document.getElementById('deleteRecipeId').value = id;
    document.getElementById('deletePromptText').innerText = 'هل أنت متأكد من حذف مقادير المشروب:';
    document.getElementById('deleteItemTitle').innerText = name;
    document.getElementById('deleteIngredientModal').style.display = 'flex';
}

function closeModals() {
    document.getElementById('ingredientModal').style.display = 'none';
    document.getElementById('stockModal').style.display = 'none';
    document.getElementById('recipeModal').style.display = 'none';
    const del = document.getElementById('deleteIngredientModal');
    if (del) del.style.display = 'none';
}

function loadProductVariants(productId) {
    const sel = document.getElementById('recipeVariantSelect');
    sel.innerHTML = '<option value="">لكل الأحجام / حجم موحد</option>';
    if (!productId) return;

    fetch('../pos/ajax.php?action=get_product_details&product_id=' + productId)
        .then(res => res.json())
        .then(data => {
            if (data.variants && data.variants.length > 0) {
                data.variants.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.innerText = v.variant_name + ' (' + v.final_price + ' ج.م)';
                    sel.appendChild(opt);
                });
            }
        });
}

function addRecipeItemRow() {
    const tbody = document.getElementById('recipeItemsList');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="items_ingredient_id[]" class="form-select" required>
                <option value="">-- اختر المادة --</option>
                <?php foreach ($ingredients as $ing): ?>
                    <option value="<?php echo $ing['id']; ?>"><?php echo htmlspecialchars($ing['name']); ?> (<?php echo $ing['base_unit']; ?>)</option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" step="0.01" name="items_quantity[]" class="form-control" placeholder="الكمية" required></td>
        <td>
            <select name="items_unit[]" class="form-select">
                <option value="g">جرام</option>
                <option value="ml">مليلتر</option>
                <option value="piece">قطعة/كوب</option>
            </select>
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">✕</button></td>
    `;
    tbody.appendChild(tr);
}

window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        closeModals();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
