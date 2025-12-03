<?php
/**
 * Admin Dashboard
 * Opus3D Admin Panel
 */

require_once 'config.php';
requireAdminLogin();

$conn = getDBConnection();

// Get statistics
$stats = [];

// Total Orders
$result = $conn->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $result->fetch_assoc()['total'];

// Total Revenue
$result = $conn->query("SELECT SUM(total) as revenue FROM orders WHERE payment_status = 'paid'");
$row = $result->fetch_assoc();
$stats['total_revenue'] = $row['revenue'] ?? 0;

// Total Products
$result = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $result->fetch_assoc()['total'];

// Total Customers
$result = $conn->query("SELECT COUNT(*) as total FROM customers");
$stats['total_customers'] = $result->fetch_assoc()['total'];

// Recent Orders
$result = $conn->query("SELECT o.*, c.first_name, c.last_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id ORDER BY o.created_at DESC LIMIT 10");
$recent_orders = $result->fetch_all(MYSQLI_ASSOC);

// Pending Orders
$result = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$stats['pending_orders'] = $result->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Opus3D Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page">
    <?php include 'header.php'; ?>

    <div class="admin-container">
        <?php include 'sidebar.php'; ?>

        <div class="admin-content">
            <div class="page-header">
                <h2>Dashboard</h2>
                <p>Panoramica generale del sistema</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3>€<?php echo number_format($stats['total_revenue'], 2, ',', '.'); ?></h3>
                        <p>Ricavi Totali</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orders">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_orders'], 0, ',', '.'); ?></h3>
                        <p>Ordini Totali</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon products">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_products'], 0, ',', '.'); ?></h3>
                        <p>Prodotti</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon customers">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_customers'], 0, ',', '.'); ?></h3>
                        <p>Clienti</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Ordini Recenti</h3>
                        <a href="orders.php" class="btn-link">Vedi tutti</a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Numero Ordine</th>
                                    <th>Cliente</th>
                                    <th>Totale</th>
                                    <th>Stato</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_orders) > 0): ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td><strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                            <td>
                                                <?php 
                                                if ($order['first_name']) {
                                                    echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']);
                                                } else {
                                                    echo htmlspecialchars($order['customer_email']);
                                                }
                                                ?>
                                            </td>
                                            <td>€<?php echo number_format($order['total'], 2, ',', '.'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo htmlspecialchars($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Nessun ordine trovato</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Azioni Rapide</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="products.php?action=new" class="action-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Nuovo Prodotto
                        </a>
                        <a href="orders.php?status=pending" class="action-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                            </svg>
                            Ordini in Attesa (<?php echo $stats['pending_orders']; ?>)
                        </a>
                        <a href="customers.php" class="action-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Gestisci Clienti
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

