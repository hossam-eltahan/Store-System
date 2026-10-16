<?php
/**
 * Legacy entry point — modifiers management moved into products/index.php?tab=mods
 * Kept for old bookmarks and in-app links.
 */
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requirePermission('products.view');
redirect('index.php?tab=mods');
