<?php
/**
 * AJAX Handler for Invoices
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'search':
        searchProducts();
        break;
        
    case 'get_product':
        getProduct();
        break;
        
    case 'get_items':
        getInvoiceItems();
        break;
        
    case 'generate_code':
        generateProductCode();
        break;
        
    case 'add_product':
        addProduct();
        break;
        
    case 'search_invoices':
        searchInvoices();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function searchInvoices() {
    requireAnyPermission(['invoices.sale.view', 'invoices.purchase.view']);

    $canViewSales = hasPermission('invoices.sale.view');
    $canViewPurchases = hasPermission('invoices.purchase.view');

    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $paymentStatus = $_GET['payment_status'] ?? '';
    
    // Restrict type according to permissions
    if ($canViewSales && !$canViewPurchases) {
        $type = 'sale';
    } elseif ($canViewPurchases && !$canViewSales) {
        $type = 'purchase';
    }
    
    $where = [];
    $params = [];
    
    if ($type) {
        $where[] = "i.type = ?";
        $params[] = $type;
    }
    
    if ($search) {
        $where[] = "(i.invoice_number LIKE ? OR i.customer_name LIKE ? OR c.name LIKE ? OR s.name LIKE ? OR c.phone LIKE ? OR s.phone LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($dateFrom) {
        $where[] = "i.date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where[] = "i.date <= ?";
        $params[] = $dateTo;
    }
    
    if ($paymentStatus) {
        $where[] = "i.payment_status = ?";
        $params[] = $paymentStatus;
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $invoices = getRows(
        "SELECT i.*, 
                COALESCE(c.name, i.customer_name) as display_name,
                s.name as supplier_name_db
         FROM invoices i
         LEFT JOIN customers c ON i.customer_id = c.id
         LEFT JOIN suppliers s ON i.supplier_id = s.id
         $whereClause
         ORDER BY i.created_at DESC
         LIMIT 100",
        $params
    );
    
    // Format for display
    $results = [];
    foreach ($invoices as $inv) {
        $results[] = [
            'id' => $inv['id'],
            'invoice_number' => $inv['invoice_number'],
            'type' => $inv['type'],
            'type_label' => $inv['type'] === 'sale' ? 'مبيعات' : 'مشتريات',
            'name' => $inv['type'] === 'sale' ? $inv['display_name'] : ($inv['supplier_name_db'] ?: $inv['customer_name']),
            'date' => date('Y/m/d', strtotime($inv['date'])),
            'total' => number_format($inv['total_amount'], 2),
            'paid' => number_format($inv['paid_amount'], 2),
            'remaining' => number_format($inv['remaining_amount'], 2),
            'status' => $inv['payment_status'],
            'status_label' => ['paid' => 'مدفوع', 'partial' => 'جزئي', 'unpaid' => 'غير مدفوع'][$inv['payment_status']],
            'print_url' => ($inv['type'] === 'purchase' ? 'print_purchase.php' : 'print.php') . '?id=' . $inv['id'],
            'return_url' => '../returns/' . ($inv['type'] === 'purchase' ? 'create_supplier.php' : 'create.php') . '?invoice_id=' . $inv['id']
        ];
    }
    
    echo json_encode(['success' => true, 'invoices' => $results, 'count' => count($results)]);
}

function generateProductCode() {
    requirePermission('products.add');
    $lastProduct = getRow("SELECT code FROM products WHERE code LIKE 'PRD%' ORDER BY id DESC LIMIT 1");
    if ($lastProduct && preg_match('/PRD(\d+)/', $lastProduct['code'], $matches)) {
        $nextNum = intval($matches[1]) + 1;
    } else {
        $nextNum = 1;
    }
    $code = 'PRD' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    echo json_encode(['code' => $code]);
}

function addProduct() {
    requirePermission('products.add');
    $name = sanitize($_POST['name'] ?? '');
    $code = sanitize($_POST['code'] ?? '');
    $unit = sanitize($_POST['unit'] ?? 'قطعة');
    $price = floatval($_POST['price'] ?? 0);
    $minStock = intval($_POST['min_stock'] ?? 5);
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'اسم الصنف مطلوب']);
        return;
    }
    
    // Generate code if empty
    if (empty($code)) {
        $lastProduct = getRow("SELECT code FROM products WHERE code LIKE 'PRD%' ORDER BY id DESC LIMIT 1");
        if ($lastProduct && preg_match('/PRD(\d+)/', $lastProduct['code'], $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = 1;
        }
        $code = 'PRD' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
    
    try {
        $productId = insert(
            "INSERT INTO products (code, name, unit, price, stock_quantity, min_stock_level) VALUES (?, ?, ?, ?, 0, ?)",
            [$code, $name, $unit, $price, $minStock]
        );
        
        echo json_encode(['success' => true, 'product_id' => $productId, 'code' => $code]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function searchProducts() {
    requireAnyPermission(['products.view', 'invoices.sale.create', 'invoices.purchase.create', 'invoices.sale.edit', 'invoices.purchase.edit']);
    $query = $_GET['q'] ?? '';
    $warehouseId = (int)($_GET['warehouse_id'] ?? 0);
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Query too short']);
        return;
    }
    
    $searchTerm = "%$query%";
    if ($warehouseId > 0) {
        $products = getRows(
            "SELECT p.id, p.code, p.name, p.unit, p.price, 
                    COALESCE(ws.quantity, 0) as stock_quantity,
                    p.stock_quantity as total_stock_quantity
             FROM products p
             LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = ?
             WHERE p.code LIKE ? OR p.name LIKE ? OR p.description LIKE ?
             ORDER BY p.name
             LIMIT 20",
            [$warehouseId, $searchTerm, $searchTerm, $searchTerm]
        );
    } else {
        $products = getRows(
            "SELECT id, code, name, unit, price, stock_quantity 
             FROM products 
             WHERE code LIKE ? OR name LIKE ? OR description LIKE ?
             ORDER BY name
             LIMIT 20",
            [$searchTerm, $searchTerm, $searchTerm]
        );
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
}

function getProduct() {
    requireAnyPermission(['products.view', 'invoices.sale.create', 'invoices.purchase.create', 'invoices.sale.edit', 'invoices.purchase.edit']);
    $id = $_GET['id'] ?? 0;
    $warehouseId = (int)($_GET['warehouse_id'] ?? 0);
    
    if ($warehouseId > 0) {
        $product = getRow(
            "SELECT p.id, p.code, p.name, p.unit, p.price, 
                    COALESCE(ws.quantity, 0) as stock_quantity,
                    p.stock_quantity as total_stock_quantity
             FROM products p
             LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = ?
             WHERE p.id = ?",
            [$warehouseId, $id]
        );
    } else {
        $product = getRow(
            "SELECT id, code, name, unit, price, stock_quantity 
             FROM products 
             WHERE id = ?",
            [$id]
        );
    }
    
    if ($product) {
        echo json_encode([
            'success' => true,
            'product' => $product
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
    }
}

function getInvoiceItems() {
    requireAnyPermission(['invoices.sale.view', 'invoices.purchase.view', 'returns.create_customer', 'returns.create_supplier']);
    $invoiceId = $_GET['invoice_id'] ?? 0;
    
    $items = getRows(
        "SELECT 
            ii.*,
            (
                SELECT COALESCE(SUM(ri.quantity), 0) 
                FROM return_items ri 
                JOIN returns r ON ri.return_id = r.id 
                WHERE r.original_invoice_id = ii.invoice_id 
                AND ri.product_id = ii.product_id
            ) as returned_quantity
         FROM invoice_items ii 
         WHERE ii.invoice_id = ?",
        [$invoiceId]
    );
    
    $availableItems = [];
    foreach ($items as $item) {
        $available = $item['quantity'] - $item['returned_quantity'];
        $item['available_quantity'] = max(0, $available);
        $availableItems[] = $item;
    }
    
    echo json_encode([
        'success' => true,
        'items' => $availableItems
    ]);
}
