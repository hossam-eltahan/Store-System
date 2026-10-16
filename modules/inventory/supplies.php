<?php
/**
 * Quick Supplies Log — merged into inventory/index.php?tab=supplies
 * Kept for old bookmarks and in-app links.
 */
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireLogin();
requirePermission('inventory.view');

$params = ['tab' => 'supplies'];
foreach (['date_from', 'date_to', 'supplier_id', 'linked'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') {
        $params[$k] = $_GET[$k];
    }
}
if (isset($_GET['page'])) {
    $params['sup_page'] = (int)$_GET['page'];
}
redirect('index.php?' . http_build_query($params));
