<?php
require_once 'config.php';

session_start();

$conn = getDBConnection();

// Get cart from session
$cart_items = $_SESSION['cart'] ?? [];
$cart_data = [];

// Get full product details from database
foreach ($cart_items as $item) {
    $product_id = $item['product_id'];
    $stmt = $conn->prepare("SELECT id, name, price, image, stock_quantity, stock_status FROM products WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    if ($product) {
        $cart_data[] = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price' => floatval($product['price']),
            'image' => $product['image'] ?: 'https://placehold.co/150x150/f0f0f0/5FB7BB?text=No+Image',
            'quantity' => $item['quantity'],
            'stock_quantity' => $product['stock_quantity'],
            'stock_status' => $product['stock_status']
        ];
    }
}

// Get cross-sell products (featured products not in cart)
$cart_product_ids = array_column($cart_data, 'product_id');
$placeholders = str_repeat('?,', count($cart_product_ids) - 1) . '?';
$cross_sell_stmt = $conn->prepare("
    SELECT id, name, slug, price, image, featured 
    FROM products 
    WHERE status = 'active' 
    AND featured = 1 
    " . (!empty($cart_product_ids) ? "AND id NOT IN ($placeholders)" : "") . "
    ORDER BY created_at DESC 
    LIMIT 3
");

if (!empty($cart_product_ids)) {
    $cross_sell_stmt->bind_param(str_repeat('i', count($cart_product_ids)), ...$cart_product_ids);
}
$cross_sell_stmt->execute();
$cross_sell_result = $cross_sell_stmt->get_result();
$cross_sell_products = $cross_sell_result->fetch_all(MYSQLI_ASSOC);
$cross_sell_stmt->close();

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_data as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

$shipping = 4.99; // Fixed shipping cost
$total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il Tuo Carrello - Opus3D</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="cart.css">
</head>
<body>

    <!-- Countdown Topbar -->
    <div class="topbar-countdown">
        <div class="container countdown-content">
            <span class="countdown-label">Prossimo Drop Esclusivo tra:</span>
            <div id="countdown" class="countdown-timer">
                <div class="time-block"><span id="days">00</span><small>Giorni</small></div>
                <div class="separator">:</div>
                <div class="time-block"><span id="hours">00</span><small>Ore</small></div>
                <div class="separator">:</div>
                <div class="time-block"><span id="minutes">00</span><small>Min</small></div>
                <div class="separator">:</div>
                <div class="time-block"><span id="seconds">00</span><small>Sec</small></div>
            </div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="logo">Opus3D</div>
            <input type="checkbox" id="nav-toggle" class="nav-toggle">
            <label for="nav-toggle" class="nav-toggle-label">
                <span></span>
                <span></span>
                <span></span>
            </label>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#drop">Drop</a></li>
                <li><a href="#">Custom</a></li>
            </ul>
            <a href="cart.php" class="btn-secondary nav-btn active">Carrello (<span id="nav-cart-count"><?php echo $total_items; ?></span>)</a>
        </div>
    </nav>

    <!-- Cart Section -->
    <section class="cart-section">
        <div class="container">
            <h1 class="page-title">Il Tuo Carrello</h1>
            
            <div class="cart-layout" id="cart-container">
                <?php if (empty($cart_data)): ?>
                    <div class="empty-cart">
                        <h3>Il tuo carrello è vuoto</h3>
                        <p>Non hai ancora aggiunto nessun prodotto.</p>
                        <a href="index.php#drop" class="btn-primary">Torna allo Shop</a>
                    </div>
                <?php else: ?>
                    <div class="cart-items">
                        <?php foreach ($cart_data as $item): ?>
                            <div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                                <div class="item-image">
                                    <a href="product.php?id=<?php echo $item['product_id']; ?>">
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    </a>
                                </div>
                                <div class="item-details">
                                    <h3><a href="product.php?id=<?php echo $item['product_id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                                    <div class="item-price">€<?php echo number_format($item['price'], 2, ',', '.'); ?></div>
                                </div>
                                <div class="item-actions">
                                    <div class="qty-control">
                                        <button onclick="updateCartQty(<?php echo $item['product_id']; ?>, -1)">-</button>
                                        <span class="item-quantity"><?php echo $item['quantity']; ?></span>
                                        <button onclick="updateCartQty(<?php echo $item['product_id']; ?>, 1)">+</button>
                                    </div>
                                    <button class="remove-btn" onclick="removeCartItem(<?php echo $item['product_id']; ?>)">Rimuovi</button>
                                </div>
                                <div class="item-total">
                                    €<?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="cart-summary">
                        <h3>Riepilogo Ordine</h3>
                        <div class="summary-row">
                            <span>Sottototale</span>
                            <span id="cart-subtotal">€<?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Spedizione</span>
                            <span>€<?php echo number_format($shipping, 2, ',', '.'); ?></span>
                        </div>
                        
                        <div class="discount-code-section">
                            <div class="discount-input-group">
                                <input type="text" id="discount-input" placeholder="Codice Sconto (es. OPUS20)">
                                <button class="btn-secondary small" onclick="applyDiscount()">Applica</button>
                            </div>
                            <p id="discount-message" class="discount-message"></p>
                        </div>

                        <div class="summary-divider"></div>
                        <div class="summary-row total">
                            <span>Totale</span>
                            <span id="cart-total">€<?php echo number_format($total, 2, ',', '.'); ?></span>
                        </div>
                        <a href="checkout.php" class="btn-primary full-width checkout-btn" style="display: block; text-align: center; text-decoration: none;">Procedi al Checkout</a>
                        
                        <div class="secure-checkout">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <span>Checkout Sicuro SSL</span>
                        </div>
                        <div class="payment-methods">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/4/41/Visa_Logo.png" alt="Visa" height="20">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b7/MasterCard_Logo.svg" alt="Mastercard" height="20">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="PayPal" height="20">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/f/fa/Apple_logo_black.svg" alt="Apple Pay" height="20">
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Cross Selling Section -->
    <section class="cross-sell-section">
        <div class="container">
            <div class="section-header">
                <h2>Completa il Look</h2>
                <p>Prodotti che altri utenti hanno acquistato insieme.</p>
            </div>
            <div class="products-grid" id="cross-sell-grid">
                <!-- Loaded via JS -->
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container footer-content">
            <div class="footer-brand">
                <div class="logo">Opus3D</div>
                <p>Figure personalizzate che raccontano la tua storia. Design unico, qualità premium.</p>
                <div class="social-links">
                    <a href="#" aria-label="Instagram"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
                    <a href="#" aria-label="Facebook"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
                    <a href="#" aria-label="Twitter"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg></a>
                </div>
            </div>
            <div class="footer-links">
                <h4>Shop</h4>
                <ul>
                    <li><a href="#">Nuovi Arrivi</a></li>
                    <li><a href="#">Best Sellers</a></li>
                    <li><a href="#">Customizza</a></li>
                    <li><a href="#">Gift Cards</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Supporto</h4>
                <ul>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Spedizioni</a></li>
                    <li><a href="#">Resi</a></li>
                    <li><a href="#">Contattaci</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Legale</h4>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Termini & Condizioni</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>&copy; 2023 Opus3D. Tutti i diritti riservati. Made with imagination.</p>
            </div>
        </div>
    </footer>

    <script>
        // Countdown Logic (Shared)
        const dropDate = new Date("December 16, 2025 00:00:00").getTime();
        setInterval(() => {
            const now = new Date().getTime();
            const distance = dropDate - now;
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            const elDays = document.getElementById("days");
            if(elDays) {
                elDays.innerText = days < 10 ? "0" + days : days;
                document.getElementById("hours").innerText = hours < 10 ? "0" + hours : hours;
                document.getElementById("minutes").innerText = minutes < 10 ? "0" + minutes : minutes;
                document.getElementById("seconds").innerText = seconds < 10 ? "0" + seconds : seconds;
            }
        }, 1000);

        // Cart data from PHP
        const cartData = <?php echo json_encode($cart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
        const crossSellProducts = <?php 
            $cross_sell_data = [];
            foreach ($cross_sell_products as $prod) {
                $cross_sell_data[] = [
                    'id' => $prod['id'],
                    'name' => $prod['name'],
                    'slug' => $prod['slug'],
                    'price' => floatval($prod['price']),
                    'image' => $prod['image'] ?: 'https://placehold.co/300x300/f0f0f0/5FB7BB?text=No+Image',
                    'tag' => $prod['featured'] ? 'In Evidenza' : null
                ];
            }
            echo json_encode($cross_sell_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        ?>;

        let discountApplied = 0;

        // Update cart quantity
        function updateCartQty(productId, change) {
            const item = cartData.find(i => i.product_id == productId);
            if (!item) return;
            
            const newQty = item.quantity + change;
            
            if (newQty < 1) {
                if(confirm("Rimuovere questo articolo dal carrello?")) {
                    removeCartItem(productId);
                }
                return;
            }
            
            // Check stock limit
            if (item.stock_quantity > 0 && newQty > item.stock_quantity) {
                alert('Quantità massima disponibile: ' + item.stock_quantity);
                return;
            }
            
            // Update via AJAX
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('product_id', productId);
            formData.append('quantity', newQty);
            
            fetch('cart-actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    item.quantity = newQty;
                    location.reload(); // Reload to update totals
                } else {
                    alert(data.error || 'Errore durante l\'aggiornamento');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di connessione');
            });
        }

        function removeCartItem(productId) {
            if (!confirm("Rimuovere questo articolo dal carrello?")) return;
            
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('product_id', productId);
            
            fetch('cart-actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Errore durante la rimozione');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di connessione');
            });
        }

        function applyDiscount() {
            const code = document.getElementById('discount-input').value.trim().toUpperCase();
            const msg = document.getElementById('discount-message');
            
            if (code === 'OPUS20') {
                discountApplied = 0.20; // 20%
                updateCartTotals();
                msg.innerText = "Codice applicato con successo!";
                msg.className = "discount-message success";
            } else {
                msg.innerText = "Codice non valido";
                msg.className = "discount-message error";
            }
        }

        function updateCartTotals() {
            const subtotal = <?php echo $subtotal; ?>;
            const shipping = <?php echo $shipping; ?>;
            let discountAmount = 0;
            
            if (discountApplied > 0) {
                discountAmount = subtotal * discountApplied;
            }
            
            const total = subtotal + shipping - discountAmount;
            
            // Update discount row if exists
            let discountRow = document.querySelector('.summary-row.discount');
            if (discountApplied > 0) {
                if (!discountRow) {
                    const summaryDiv = document.querySelector('.cart-summary');
                    const shippingRow = document.querySelector('.summary-row:nth-of-type(2)');
                    discountRow = document.createElement('div');
                    discountRow.className = 'summary-row discount';
                    shippingRow.after(discountRow);
                }
                discountRow.innerHTML = `
                    <span>Sconto (${(discountApplied * 100).toFixed(0)}%)</span>
                    <span>-€${discountAmount.toFixed(2).replace('.', ',')}</span>
                `;
            } else if (discountRow) {
                discountRow.remove();
            }
            
            // Update total
            document.getElementById('cart-total').innerText = '€' + total.toFixed(2).replace('.', ',');
        }

        // Render Cross Sell
        function renderCrossSell() {
            const grid = document.getElementById('cross-sell-grid');
            if (!grid) return;
            
            let html = '';
            
            crossSellProducts.forEach(prod => {
                html += `
                    <div class="product-card">
                        <div class="product-image">
                            <a href="product.php?slug=${prod.slug}">
                                <img src="${prod.image}" alt="${prod.name}">
                            </a>
                            ${prod.tag ? `<span class="badge">${prod.tag}</span>` : ''}
                        </div>
                        <div class="product-info">
                            <h3><a href="product.php?slug=${prod.slug}" style="text-decoration: none; color: inherit;">${prod.name}</a></h3>
                            <p class="price-small">€${prod.price.toFixed(2)}</p>
                            <a href="product.php?slug=${prod.slug}" class="btn-mini">Vedi Dettagli</a>
                        </div>
                    </div>
                `;
            });
            
            grid.innerHTML = html || '<p style="grid-column: 1/-1; text-align: center; color: var(--text-light);">Nessun prodotto suggerito disponibile</p>';
        }

        // Initialize
        window.addEventListener('load', () => {
            renderCrossSell();
        });
    </script>
</body>
</html>

