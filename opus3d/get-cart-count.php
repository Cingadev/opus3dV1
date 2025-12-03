<?php
/**
 * Get Cart Count
 * Opus3D
 */

session_start();

$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'] ?? 0;
    }
}

echo $cart_count;

