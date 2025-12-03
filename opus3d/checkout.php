<?php
require_once 'config.php';

session_start();

$conn = getDBConnection();

// Redirect if cart is empty
$cart_items = $_SESSION['cart'] ?? [];
if (empty($cart_items)) {
    header('Location: cart.php');
    exit;
}

// Get full product details from database
$cart_data = [];
$subtotal = 0;
$total_items = 0;

foreach ($cart_items as $item) {
    $product_id = $item['product_id'];
    $stmt = $conn->prepare("SELECT id, name, price, image, stock_quantity, stock_status FROM products WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    if ($product) {
        $item_price = floatval($product['price']);
        $item_qty = $item['quantity'];
        $cart_data[] = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price' => $item_price,
            'image' => $product['image'] ?: 'https://placehold.co/100x100/f0f0f0/5FB7BB?text=No+Image',
            'quantity' => $item_qty,
            'stock_quantity' => $product['stock_quantity'],
            'stock_status' => $product['stock_status']
        ];
        $subtotal += $item_price * $item_qty;
        $total_items += $item_qty;
    }
}

// If no valid products, redirect
if (empty($cart_data)) {
    header('Location: cart.php');
    exit;
}

$shipping = 4.99;
$discount_amount = 0;
$discount_code = '';

// Handle order submission
$order_saved = false;
$order_number = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = sanitizeInput($_POST['email'] ?? '');
    $first_name = sanitizeInput($_POST['name'] ?? '');
    $last_name = sanitizeInput($_POST['surname'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    
    // Shipping address
    $shipping_address = sanitizeInput($_POST['address'] ?? '');
    $shipping_apartment = sanitizeInput($_POST['apartment'] ?? '');
    $shipping_city = sanitizeInput($_POST['city'] ?? '');
    $shipping_zip = sanitizeInput($_POST['zip'] ?? '');
    $shipping_country = sanitizeInput($_POST['country'] ?? 'IT');
    
    // Billing (same as shipping for now, or can be different)
    $billing_first_name = sanitizeInput($_POST['billing_name'] ?? $first_name);
    $billing_last_name = sanitizeInput($_POST['billing_surname'] ?? $last_name);
    $billing_address = sanitizeInput($_POST['billing_address'] ?? $shipping_address);
    $billing_city = sanitizeInput($_POST['billing_city'] ?? $shipping_city);
    $billing_zip = sanitizeInput($_POST['billing_zip'] ?? $shipping_zip);
    $billing_country = sanitizeInput($_POST['billing_country'] ?? $shipping_country);
    
    $payment_method = sanitizeInput($_POST['payment_method'] ?? 'card');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    // Validate required fields
    if (empty($email) || empty($first_name) || empty($last_name) || empty($phone) || 
        empty($shipping_address) || empty($shipping_city) || empty($shipping_zip)) {
        $error = 'Compila tutti i campi obbligatori.';
    } else {
        // Check discount code if provided
        $discount_code_input = sanitizeInput($_POST['discount_code'] ?? '');
        if (!empty($discount_code_input)) {
            $discount_stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND status = 'active' AND valid_from <= NOW() AND valid_until >= NOW()");
            $discount_stmt->bind_param("s", $discount_code_input);
            $discount_stmt->execute();
            $discount_result = $discount_stmt->get_result();
            $discount = $discount_result->fetch_assoc();
            $discount_stmt->close();
            
            if ($discount) {
                if ($discount['type'] === 'percentage') {
                    $discount_amount = $subtotal * (floatval($discount['value']) / 100);
                    if ($discount['max_discount'] && $discount_amount > floatval($discount['max_discount'])) {
                        $discount_amount = floatval($discount['max_discount']);
                    }
                } else {
                    $discount_amount = floatval($discount['value']);
                }
                $discount_code = $discount_code_input;
            }
        }
        
        $total = $subtotal + $shipping - $discount_amount;
        
        // Generate unique order number
        $order_number = 'OPUS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Check if order number exists (very unlikely but check anyway)
        $check_stmt = $conn->prepare("SELECT id FROM orders WHERE order_number = ?");
        $check_stmt->bind_param("s", $order_number);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $order_number = 'OPUS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
        }
        $check_stmt->close();
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert order
            // Parameters: order_number(s), payment_method(s), subtotal(d), shipping_cost(d), discount_amount(d), total(d), shipping_first_name(s), shipping_last_name(s), shipping_address(s), shipping_apartment(s), shipping_city(s), shipping_zip(s), shipping_country(s), shipping_phone(s), billing_first_name(s), billing_last_name(s), billing_address(s), billing_city(s), billing_zip(s), billing_country(s), customer_email(s), customer_phone(s), notes(s)
            // Type string: ssddddsssssssssssssssssss (23 chars)
            $stmt = $conn->prepare("INSERT INTO orders (order_number, customer_id, status, payment_status, payment_method, subtotal, shipping_cost, discount_amount, tax_amount, total, currency, shipping_first_name, shipping_last_name, shipping_address, shipping_apartment, shipping_city, shipping_zip, shipping_country, shipping_phone, billing_first_name, billing_last_name, billing_address, billing_city, billing_zip, billing_country, customer_email, customer_phone, notes) VALUES (?, NULL, 'pending', 'pending', ?, ?, ?, ?, 0, ?, 'EUR', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("ssddddsssssssssssssssssss", 
                $order_number, 
                $payment_method,
                $subtotal, 
                $shipping, 
                $discount_amount, 
                $total,
                $first_name, 
                $last_name, 
                $shipping_address, 
                $shipping_apartment, 
                $shipping_city, 
                $shipping_zip, 
                $shipping_country, 
                $phone,
                $billing_first_name, 
                $billing_last_name, 
                $billing_address, 
                $billing_city, 
                $billing_zip, 
                $billing_country,
                $email, 
                $phone, 
                $notes
            );
            
            $stmt->execute();
            $order_id = $conn->insert_id;
            $stmt->close();
            
            // Insert order items and update stock
            foreach ($cart_data as $item) {
                $item_subtotal = $item['price'] * $item['quantity'];
                
                // Insert order item
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, quantity, price, subtotal) VALUES (?, ?, NULL, ?, NULL, ?, ?, ?)");
                $item_stmt->bind_param("iisidd", $order_id, $item['product_id'], $item['name'], $item['quantity'], $item['price'], $item_subtotal);
                $item_stmt->execute();
                $item_stmt->close();
                
                // Update product stock and sales count
                $update_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ?, sales_count = sales_count + ? WHERE id = ?");
                $update_stmt->bind_param("iii", $item['quantity'], $item['quantity'], $item['product_id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            // Update discount code usage if used
            if (!empty($discount_code)) {
                $usage_stmt = $conn->prepare("INSERT INTO discount_code_usage (discount_code_id, order_id, customer_id, discount_amount) VALUES (?, ?, NULL, ?)");
                $discount_id = $discount['id'];
                $usage_stmt->bind_param("iid", $discount_id, $order_id, $discount_amount);
                $usage_stmt->execute();
                $usage_stmt->close();
                
                // Update discount code used count
                $update_discount_stmt = $conn->prepare("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = ?");
                $update_discount_stmt->bind_param("i", $discount_id);
                $update_discount_stmt->execute();
                $update_discount_stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            $order_saved = true;
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = 'Errore durante il salvataggio dell\'ordine: ' . $e->getMessage();
        }
    }
}

// Get cart count for navbar
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Opus3D</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="checkout.css">
</head>
<body>

    <!-- Simple Navbar for Checkout -->
    <nav class="navbar checkout-nav">
        <div class="container">
            <div class="logo">Opus3D</div>
            <div class="secure-badge">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Checkout Sicuro
            </div>
        </div>
    </nav>

    <?php if ($order_saved): ?>
        <!-- Order Success Page -->
        <div class="container checkout-container">
            <div style="max-width: 600px; margin: 0 auto; text-align: center; padding: 60px 20px;">
                <div style="width: 80px; height: 80px; background: #27ae60; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px;">
                    <svg width="40" height="40" fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <h1 style="font-size: 2rem; margin-bottom: 15px; color: var(--text-dark);">Ordine Confermato!</h1>
                <p style="font-size: 1.1rem; color: var(--text-light); margin-bottom: 30px;">
                    Grazie per il tuo ordine. Il numero ordine è:<br>
                    <strong style="color: var(--primary-color); font-size: 1.3rem;"><?php echo htmlspecialchars($order_number); ?></strong>
                </p>
                <p style="color: var(--text-light); margin-bottom: 40px;">
                    Riceverai una email di conferma a breve con tutti i dettagli dell'ordine.
                </p>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <a href="index.php" class="btn-primary">Torna allo Shop</a>
                    <a href="product.php?id=<?php echo $cart_data[0]['product_id'] ?? ''; ?>" class="btn-secondary">Vedi Prodotti</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="container checkout-container">
            <div class="checkout-layout">
                
                <!-- Left Column: Forms -->
                <div class="checkout-forms">
                    <?php if ($error): ?>
                        <div class="alert alert-error" style="margin-bottom: 20px; padding: 14px 16px; background: #fee; color: #e74c3c; border-radius: 8px; border: 1px solid #fcc;">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="checkout-form">
                        <!-- Customer Info -->
                        <div class="form-section">
                            <div class="section-title">
                                <div class="step-number">1</div>
                                <h2>Informazioni Cliente</h2>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" placeholder="latua@email.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Nome *</label>
                                    <input type="text" id="name" name="name" placeholder="Mario" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="surname">Cognome *</label>
                                    <input type="text" id="surname" name="surname" placeholder="Rossi" required value="<?php echo htmlspecialchars($_POST['surname'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="phone">Telefono *</label>
                                <input type="tel" id="phone" name="phone" placeholder="+39 333 1234567" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Shipping Address -->
                        <div class="form-section">
                            <div class="section-title">
                                <div class="step-number">2</div>
                                <h2>Indirizzo di Spedizione</h2>
                            </div>
                            <div class="form-group">
                                <label for="address">Indirizzo *</label>
                                <input type="text" id="address" name="address" placeholder="Via Roma 1" required value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="apartment">Appartamento, interno, ecc. (opzionale)</label>
                                <input type="text" id="apartment" name="apartment" placeholder="" value="<?php echo htmlspecialchars($_POST['apartment'] ?? ''); ?>">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">Città *</label>
                                    <input type="text" id="city" name="city" placeholder="Milano" required value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="zip">CAP *</label>
                                    <input type="text" id="zip" name="zip" placeholder="20100" required value="<?php echo htmlspecialchars($_POST['zip'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="country">Paese *</label>
                                    <select id="country" name="country" required>
                                        <option value="IT" <?php echo (($_POST['country'] ?? 'IT') === 'IT') ? 'selected' : ''; ?>>Italia</option>
                                        <option value="FR" <?php echo (($_POST['country'] ?? '') === 'FR') ? 'selected' : ''; ?>>Francia</option>
                                        <option value="DE" <?php echo (($_POST['country'] ?? '') === 'DE') ? 'selected' : ''; ?>>Germania</option>
                                        <option value="ES" <?php echo (($_POST['country'] ?? '') === 'ES') ? 'selected' : ''; ?>>Spagna</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Payment -->
                        <div class="form-section">
                            <div class="section-title">
                                <div class="step-number">3</div>
                                <h2>Pagamento</h2>
                            </div>
                            <div class="payment-container">
                                <p class="payment-info">Tutte le transazioni sono sicure e crittografate.</p>
                                
                                <div class="form-group">
                                    <label for="payment_method">Metodo di Pagamento *</label>
                                    <select id="payment_method" name="payment_method" required>
                                        <option value="card" <?php echo (($_POST['payment_method'] ?? 'card') === 'card') ? 'selected' : ''; ?>>Carta di Credito</option>
                                        <option value="paypal" <?php echo (($_POST['payment_method'] ?? '') === 'paypal') ? 'selected' : ''; ?>>PayPal</option>
                                        <option value="bank_transfer" <?php echo (($_POST['payment_method'] ?? '') === 'bank_transfer') ? 'selected' : ''; ?>>Bonifico Bancario</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes">Note per l'ordine (opzionale)</label>
                                    <textarea id="notes" name="notes" rows="3" placeholder="Istruzioni speciali, note per la consegna, ecc."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                                </div>

                                <div class="payment-logos">
                                    <span class="powered-by">Pagamenti Sicuri</span>
                                    <div class="logos-group">
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/4/41/Visa_Logo.png" alt="Visa" height="15">
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/b/b7/MasterCard_Logo.svg" alt="Mastercard" height="15">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="submit-order" class="btn-primary full-width large-btn">Conferma Ordine</button>
                    </form>
                </div>

                <!-- Right Column: Order Summary -->
                <div class="checkout-summary-sidebar">
                    <div class="order-summary-card">
                        <h3>Riepilogo Ordine</h3>
                        
                        <div class="summary-items" id="summary-items">
                            <?php foreach ($cart_data as $item): ?>
                                <div class="summary-item">
                                    <div class="summary-img-wrapper">
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <span class="summary-qty"><?php echo $item['quantity']; ?></span>
                                    </div>
                                    <div class="summary-info">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p>€<?php echo number_format($item['price'], 2, ',', '.'); ?></p>
                                    </div>
                                    <div class="summary-price">
                                        €<?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="summary-divider"></div>

                        <div class="summary-totals">
                            <div class="summary-row">
                                <span>Sottototale</span>
                                <span id="summary-subtotal">€<?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Spedizione</span>
                                <span id="summary-shipping">€<?php echo number_format($shipping, 2, ',', '.'); ?></span>
                            </div>
                            <?php if ($discount_amount > 0): ?>
                                <div class="summary-row discount">
                                    <span>Sconto</span>
                                    <span id="summary-discount">-€<?php echo number_format($discount_amount, 2, ',', '.'); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="summary-divider"></div>
                            <div class="summary-row total">
                                <span>Totale</span>
                                <span id="summary-total">€<?php echo number_format($subtotal + $shipping - $discount_amount, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    <?php endif; ?>

    <footer class="main-footer">
        <div class="container footer-content">
            <div class="footer-brand">
                <div class="logo">Opus3D</div>
                <p>&copy; 2023 Opus3D. Tutti i diritti riservati.</p>
            </div>
            <div class="footer-links">
                <h4>Legale</h4>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Termini & Condizioni</a></li>
                </ul>
            </div>
        </div>
    </footer>

    <script>
        // Form validation and submission
        document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('submit-order');
            const originalText = btn.innerText;
            
            // Basic validation
            const required = document.querySelectorAll('input[required], select[required]');
            let valid = true;
            required.forEach(field => {
                if(!field.value.trim()) {
                    field.style.borderColor = '#e74c3c';
                    valid = false;
                } else {
                    field.style.borderColor = '#ddd';
                }
            });

            if(!valid) {
                e.preventDefault();
                alert("Per favore compila tutti i campi obbligatori.");
                return;
            }

            // Show loading state
            btn.innerText = "Elaborazione...";
            btn.disabled = true;
            btn.style.opacity = "0.7";
        });
    </script>

</body>
</html>
