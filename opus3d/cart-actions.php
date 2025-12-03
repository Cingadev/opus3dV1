<?php
/**
 * Cart Actions Handler (Update, Remove)
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

$action = $_POST['action'] ?? '';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$conn = getDBConnection();

switch ($action) {
    case 'update':
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        if (!$product_id || $quantity < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'Parametri non validi']);
            exit;
        }
        
        // Check stock
        $stmt = $conn->prepare("SELECT stock_quantity, stock_status FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();
        
        if ($product && $product['stock_status'] === 'out_of_stock') {
            http_response_code(400);
            echo json_encode(['error' => 'Prodotto non disponibile']);
            exit;
        }
        
        if ($product && $product['stock_quantity'] > 0 && $quantity > $product['stock_quantity']) {
            http_response_code(400);
            echo json_encode(['error' => 'Quantità massima disponibile: ' . $product['stock_quantity']]);
            exit;
        }
        
        // Update quantity in cart
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $product_id) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        
        echo json_encode(['success' => true]);
        break;
        
    case 'remove':
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
        
        if (!$product_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Parametri non validi']);
            exit;
        }
        
        // Remove from cart
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($product_id) {
            return $item['product_id'] != $product_id;
        });
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
        
        echo json_encode(['success' => true]);
        break;
        
    case 'clear':
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
        break;
}

