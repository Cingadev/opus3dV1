<?php
/**
 * Add to Cart Handler
 * Opus3D
 */

require_once 'config.php';

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if (!$product_id || $quantity < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametri non validi']);
    exit;
}

$conn = getDBConnection();

// Get product from database
$stmt = $conn->prepare("SELECT id, name, price, image, stock_quantity, stock_status FROM products WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Prodotto non trovato']);
    exit;
}

// Check stock availability
if ($product['stock_status'] === 'out_of_stock' || ($product['stock_quantity'] < $quantity && $product['stock_quantity'] > 0)) {
    http_response_code(400);
    echo json_encode(['error' => 'Prodotto non disponibile in quantità sufficiente']);
    exit;
}

// Initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if product already in cart
$found = false;
foreach ($_SESSION['cart'] as &$item) {
    if ($item['product_id'] == $product_id) {
        // Update quantity
        $new_quantity = $item['quantity'] + $quantity;
        
        // Check stock limit
        if ($product['stock_quantity'] > 0 && $new_quantity > $product['stock_quantity']) {
            http_response_code(400);
            echo json_encode(['error' => 'Quantità massima disponibile: ' . $product['stock_quantity']]);
            exit;
        }
        
        $item['quantity'] = $new_quantity;
        $found = true;
        break;
    }
}

// Add new item if not found
if (!$found) {
    $_SESSION['cart'][] = [
        'product_id' => $product_id,
        'name' => $product['name'],
        'price' => floatval($product['price']),
        'image' => $product['image'] ?: 'https://placehold.co/150x150/f0f0f0/5FB7BB?text=No+Image',
        'quantity' => $quantity
    ];
}

// Calculate total items in cart
$total_items = 0;
foreach ($_SESSION['cart'] as $item) {
    $total_items += $item['quantity'];
}

echo json_encode([
    'success' => true,
    'message' => 'Prodotto aggiunto al carrello',
    'cart_count' => $total_items
]);

