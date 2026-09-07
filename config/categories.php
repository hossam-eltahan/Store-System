<?php
/**
 * Category helpers shared by products, invoices, and reports.
 */

function getCategoriesForSelect($activeOnly = true) {
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $categories = getRows("SELECT id, name, parent_id, is_active, sort_order FROM categories $where ORDER BY sort_order ASC, name ASC");

    return buildCategoryOptions($categories);
}

function buildCategoryOptions($categories, $parentId = null, $prefix = '') {
    $options = [];

    foreach ($categories as $category) {
        $categoryParentId = $category['parent_id'] === null ? null : (int)$category['parent_id'];
        if ($categoryParentId !== $parentId) {
            continue;
        }

        $category['display_name'] = $prefix . $category['name'];
        $options[] = $category;
        $options = array_merge($options, buildCategoryOptions($categories, (int)$category['id'], $prefix . '— '));
    }

    return $options;
}

function getCategoryDescendantIds($categoryId) {
    $allCategories = getRows("SELECT id, parent_id FROM categories");
    $descendants = [];
    $pending = [(int)$categoryId];

    while (!empty($pending)) {
        $parentId = array_pop($pending);
        foreach ($allCategories as $category) {
            if ((int)$category['parent_id'] === $parentId) {
                $childId = (int)$category['id'];
                $descendants[] = $childId;
                $pending[] = $childId;
            }
        }
    }

    return $descendants;
}
