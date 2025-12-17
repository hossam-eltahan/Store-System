<?php
/**
 * AJAX Handler for Customers
 * نظام إدارة محل أجهزة منزلية
 */

require_once '../../config/database.php';
require_once '../../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_balance':
        getCustomerBalance();
        break;
        
    case 'search':
        searchCustomers();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getCustomerBalance() {
    $customerId = $_GET['customer_id'] ?? 0;
    
    $customer = getRow("SELECT balance FROM customers WHERE id = ?", [$customerId]);
    
    if ($customer) {
        echo json_encode([
            'success' => true,
            'balance' => floatval($customer['balance'])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found'
        ]);
    }
}

function searchCustomers() {
    $query = $_GET['q'] ?? '';
    
    if (strlen($query) < 1) {
        echo json_encode(['success' => false, 'message' => 'Query too short']);
        return;
    }
    
    $searchTerm = "%$query%";
    $customers = getRows(
        "SELECT id, name, phone, balance FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name LIMIT 20",
        [$searchTerm, $searchTerm]
    );
    
    echo json_encode([
        'success' => true,
        'customers' => $customers
    ]);
}
