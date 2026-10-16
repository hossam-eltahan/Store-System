<?php
/**
 * Categories legacy entry point — merged into products/index.php?tab=cats
 * Kept for old bookmarks and in-app links.
 */
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireLogin();

$editId = (int)($_GET['edit'] ?? 0);
$target = '../products/index.php?tab=cats' . ($editId > 0 ? '&edit_cat=' . $editId : '');
redirect($target);
