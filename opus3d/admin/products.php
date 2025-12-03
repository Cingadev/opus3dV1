<?php
/**
 * Products Management
 * Opus3D Admin Panel
 */

require_once 'config.php';
requireAdminLogin();

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';
$product_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete' && isset($_POST['product_id'])) {
            // Delete product
            $id = intval($_POST['product_id']);
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                logAdminAction('product_deleted', 'products', $id, "Prodotto eliminato");
                header('Location: products.php?deleted=1');
                exit;
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'save') {
            // Save product (new or update)
            $id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
            $name = sanitizeInput($_POST['name'] ?? '');
            $slug = sanitizeInput($_POST['slug'] ?? '');
            $description = $_POST['description'] ?? '';
            $short_description = sanitizeInput($_POST['short_description'] ?? '');
            $sku = sanitizeInput($_POST['sku'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $compare_price = !empty($_POST['compare_price']) ? floatval($_POST['compare_price']) : null;
            $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
            $manage_stock = isset($_POST['manage_stock']) ? 1 : 0;
            $stock_status = sanitizeInput($_POST['stock_status'] ?? 'in_stock');
            $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $image = sanitizeInput($_POST['image'] ?? '');
            $gallery = sanitizeInput($_POST['gallery'] ?? '');
            $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
            $dimensions = sanitizeInput($_POST['dimensions'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'draft');
            $featured = isset($_POST['featured']) ? 1 : 0;
            $is_drop = isset($_POST['is_drop']) ? 1 : 0;
            $drop_start_date = !empty($_POST['drop_start_date']) ? $_POST['drop_start_date'] : null;
            $drop_end_date = !empty($_POST['drop_end_date']) ? $_POST['drop_end_date'] : null;
            
            // Convert NULL values to appropriate types for bind_param
            // For decimal fields that can be NULL, we need to handle them properly
            if ($compare_price === null) $compare_price = null; // Keep as null for SQL
            if ($category_id === null) $category_id = null; // Keep as null for SQL
            if ($weight === null) $weight = null; // Keep as null for SQL
            if ($drop_start_date === null || $drop_start_date === '') $drop_start_date = null;
            if ($drop_end_date === null || $drop_end_date === '') $drop_end_date = null;
            
            // Handle specs (stored as JSON in gallery field or we can add to description)
            // Store specs as JSON in a hidden format or append to description
            $specs = [];
            if (isset($_POST['spec_key']) && isset($_POST['spec_value'])) {
                $spec_keys = $_POST['spec_key'];
                $spec_values = $_POST['spec_value'];
                for ($i = 0; $i < count($spec_keys); $i++) {
                    if (!empty($spec_keys[$i]) && !empty($spec_values[$i])) {
                        $specs[trim($spec_keys[$i])] = trim($spec_values[$i]);
                    }
                }
            }
            // Store specs as JSON in gallery field (temporary solution)
            // Format: images separated by comma, then |SPECS| followed by JSON
            // Clean gallery first (remove any existing specs)
            if (!empty($gallery) && strpos($gallery, '|SPECS|') !== false) {
                $gallery = explode('|SPECS|', $gallery)[0];
            }
            // Add specs if any
            if (!empty($specs)) {
                $specs_json = json_encode($specs);
                if (!empty($gallery)) {
                    $gallery .= "|SPECS|" . $specs_json;
                } else {
                    $gallery = "|SPECS|" . $specs_json;
                }
            }
            
            // Generate slug if empty
            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            }
            
            // Check if slug exists (excluding current product)
            $check_stmt = $conn->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
            $check_id = $id ?? 0;
            $check_stmt->bind_param("si", $slug, $check_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $slug .= '-' . time();
            }
            $check_stmt->close();
            
            if ($id) {
                // Update existing product
                // Parameters: name(s), slug(s), description(s), short_description(s), sku(s), price(d), compare_price(d), stock_quantity(i), manage_stock(i), stock_status(s), category_id(i), image(s), gallery(s), weight(d), dimensions(s), status(s), featured(i), is_drop(i), drop_start_date(s), drop_end_date(s), id(i)
                // Type string: sssssddiissssdssisssi (21 chars)
                $stmt = $conn->prepare("UPDATE products SET name = ?, slug = ?, description = ?, short_description = ?, sku = ?, price = ?, compare_price = ?, stock_quantity = ?, manage_stock = ?, stock_status = ?, category_id = ?, image = ?, gallery = ?, weight = ?, dimensions = ?, status = ?, featured = ?, is_drop = ?, drop_start_date = ?, drop_end_date = ? WHERE id = ?");
                $stmt->bind_param("sssssddiissssdssisssi", $name, $slug, $description, $short_description, $sku, $price, $compare_price, $stock_quantity, $manage_stock, $stock_status, $category_id, $image, $gallery, $weight, $dimensions, $status, $featured, $is_drop, $drop_start_date, $drop_end_date, $id);
                
                if ($stmt->execute()) {
                    logAdminAction('product_updated', 'products', $id, "Prodotto aggiornato: $name");
                    header('Location: products.php?updated=1');
                    exit;
                }
                $stmt->close();
            } else {
                // Insert new product
                // Parameters: name(s), slug(s), description(s), short_description(s), sku(s), price(d), compare_price(d), stock_quantity(i), manage_stock(i), stock_status(s), category_id(i), image(s), gallery(s), weight(d), dimensions(s), status(s), featured(i), is_drop(i), drop_start_date(s), drop_end_date(s)
                // Type string: sssssddiissssdssisss (20 chars)
                $stmt = $conn->prepare("INSERT INTO products (name, slug, description, short_description, sku, price, compare_price, stock_quantity, manage_stock, stock_status, category_id, image, gallery, weight, dimensions, status, featured, is_drop, drop_start_date, drop_end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssddiissssdssisss", $name, $slug, $description, $short_description, $sku, $price, $compare_price, $stock_quantity, $manage_stock, $stock_status, $category_id, $image, $gallery, $weight, $dimensions, $status, $featured, $is_drop, $drop_start_date, $drop_end_date);
                
                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    logAdminAction('product_created', 'products', $new_id, "Nuovo prodotto creato: $name");
                    header('Location: products.php?created=1');
                    exit;
                }
                $stmt->close();
            }
        }
    }
}

// Get product data for edit
$product = null;
$product_specs = [];
if ($action === 'edit' && $product_id) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    if (!$product) {
        header('Location: products.php');
        exit;
    }
    
    // Extract specs from gallery field (format: images|SPECS|json)
    if (!empty($product['gallery']) && strpos($product['gallery'], '|SPECS|') !== false) {
        $parts = explode('|SPECS|', $product['gallery']);
        if (count($parts) > 1) {
            $product['gallery'] = $parts[0]; // Remove specs from gallery
            $specs_json = $parts[1];
            $product_specs = json_decode($specs_json, true) ?: [];
        }
    }
}

// Get categories for dropdown
$categories_result = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Get products list
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $where = "1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where .= " AND (name LIKE ? OR sku LIKE ? OR description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }
    
    if (!empty($status_filter)) {
        $where .= " AND status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM products WHERE $where";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_products = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_products / $per_page);
    $count_stmt->close();
    
    // Get products
    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $params[] = $per_page;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'new' ? 'Nuovo Prodotto' : ($action === 'edit' ? 'Modifica Prodotto' : 'Prodotti'); ?> - Opus3D Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
    <style>
        .form-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .form-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .specs-manager {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
        }
        .spec-item {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 12px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .spec-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .gallery-manager {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .gallery-item {
            position: relative;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 1;
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-item .remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-preview {
            max-width: 200px;
            margin-top: 12px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
        }
        .products-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .search-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .products-table {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .product-image-small {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-color);
        }
        .pagination a:hover {
            background: var(--bg-color);
        }
        .pagination .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* Improved Input Styles */
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="tel"],
        .form-group input[type="number"],
        .form-group input[type="url"],
        .form-group input[type="datetime-local"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            background-color: #fff;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(95, 183, 187, 0.1);
        }
        
        .form-group input[type="file"] {
            padding: 10px;
            cursor: pointer;
        }
        
        .form-group input[type="file"]:hover {
            border-color: var(--primary-color);
        }
        
        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--bg-color);
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            color: var(--text-color);
            width: 100%;
            justify-content: center;
        }
        
        .file-upload-btn:hover {
            border-color: var(--primary-color);
            background: rgba(95, 183, 187, 0.05);
        }
        
        .file-upload-btn svg {
            width: 20px;
            height: 20px;
        }
        
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            top: 0;
            left: 0;
        }
        
        .upload-preview {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .upload-preview-item {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid var(--border-color);
        }
        
        .upload-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-preview-item .remove-preview {
            position: absolute;
            top: 4px;
            right: 4px;
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            line-height: 1;
            font-weight: bold;
        }
        
        .upload-preview-item .remove-preview:hover {
            background: #c0392b;
        }
        
        .upload-progress {
            margin-top: 8px;
            display: none;
        }
        
        .upload-progress.active {
            display: block;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--bg-color);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-color);
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'header.php'; ?>

    <div class="admin-container">
        <?php include 'sidebar.php'; ?>

        <div class="admin-content">
            <?php if ($action === 'list'): ?>
                <!-- Products List -->
                <div class="page-header">
                    <div>
                        <h2>Prodotti</h2>
                        <p>Gestisci il catalogo prodotti</p>
                    </div>
                    <a href="products.php?action=new" class="btn btn-primary">+ Nuovo Prodotto</a>
                </div>

                <?php if (isset($_GET['created'])): ?>
                    <div class="alert alert-success">Prodotto creato con successo!</div>
                <?php endif; ?>
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">Prodotto aggiornato con successo!</div>
                <?php endif; ?>
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">Prodotto eliminato con successo!</div>
                <?php endif; ?>

                <div class="products-toolbar">
                    <div class="search-filters">
                        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <input type="text" name="search" placeholder="Cerca prodotti..." value="<?php echo htmlspecialchars($search ?? ''); ?>" style="padding: 10px 16px; border: 2px solid var(--border-color); border-radius: 8px; min-width: 250px;">
                            <select name="status" style="padding: 10px 16px; border: 2px solid var(--border-color); border-radius: 8px;">
                                <option value="">Tutti gli stati</option>
                                <option value="active" <?php echo ($status_filter ?? '') === 'active' ? 'selected' : ''; ?>>Attivo</option>
                                <option value="inactive" <?php echo ($status_filter ?? '') === 'inactive' ? 'selected' : ''; ?>>Inattivo</option>
                                <option value="draft" <?php echo ($status_filter ?? '') === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                            </select>
                            <button type="submit" class="btn btn-secondary">Filtra</button>
                            <?php if (!empty($search) || !empty($status_filter)): ?>
                                <a href="products.php" class="btn btn-secondary">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="products-table">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Immagine</th>
                                    <th>Nome</th>
                                    <th>SKU</th>
                                    <th>Prezzo</th>
                                    <th>Stock</th>
                                    <th>Categoria</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($products) > 0): ?>
                                    <?php foreach ($products as $prod): ?>
                                        <tr>
                                            <td>
                                                <?php if ($prod['image']): ?>
                                                    <img src="<?php echo htmlspecialchars($prod['image']); ?>" alt="" class="product-image-small">
                                                <?php else: ?>
                                                    <div style="width: 60px; height: 60px; background: var(--bg-color); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 12px;">No img</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($prod['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($prod['sku'] ?: '-'); ?></td>
                                            <td>€<?php echo number_format($prod['price'], 2, ',', '.'); ?></td>
                                            <td><?php echo $prod['stock_quantity']; ?></td>
                                            <td><?php echo htmlspecialchars($prod['category_name'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $prod['status'] === 'active' ? 'success' : ($prod['status'] === 'draft' ? 'warning' : 'error'); ?>">
                                                    <?php echo htmlspecialchars($prod['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" class="btn btn-secondary btn-small">Modifica</a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Sei sicuro di voler eliminare questo prodotto?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                                        <button type="submit" class="btn btn-secondary btn-small" style="background: var(--error-color);">Elimina</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Nessun prodotto trovato</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Product Form (New/Edit) -->
                <div class="page-header">
                    <div>
                        <h2><?php echo $action === 'new' ? 'Nuovo Prodotto' : 'Modifica Prodotto'; ?></h2>
                        <p><?php echo $action === 'new' ? 'Aggiungi un nuovo prodotto al catalogo' : 'Modifica le informazioni del prodotto'; ?></p>
                    </div>
                    <a href="products.php" class="btn btn-secondary">← Torna alla Lista</a>
                </div>

                <form method="POST" action="" id="product-form">
                    <input type="hidden" name="action" value="save">
                    <?php if ($product): ?>
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <?php endif; ?>

                    <!-- Basic Information -->
                    <div class="form-section">
                        <h3>Informazioni Base</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Nome Prodotto *</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="slug">Slug (URL)</label>
                                <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($product['slug'] ?? ''); ?>" placeholder="auto-generato se vuoto">
                                <small>URL-friendly version del nome</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sku">SKU</label>
                                <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>" placeholder="Codice prodotto univoco">
                            </div>
                            <div class="form-group">
                                <label for="category_id">Categoria</label>
                                <select id="category_id" name="category_id">
                                    <option value="">Nessuna categoria</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo (($product['category_id'] ?? null) == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="short_description">Descrizione Breve</label>
                            <input type="text" id="short_description" name="short_description" value="<?php echo htmlspecialchars($product['short_description'] ?? ''); ?>" maxlength="500" placeholder="Breve descrizione per anteprima (max 500 caratteri)">
                        </div>
                        <div class="form-group full-width">
                            <label for="description">Descrizione Completa *</label>
                            <textarea id="description" name="description" rows="6" required><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Pricing & Stock -->
                    <div class="form-section">
                        <h3>Prezzo e Magazzino</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="price">Prezzo *</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $product['price'] ?? '0.00'; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="compare_price">Prezzo di Confronto</label>
                                <input type="number" id="compare_price" name="compare_price" step="0.01" min="0" value="<?php echo $product['compare_price'] ?? ''; ?>" placeholder="Prezzo barrato">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="stock_quantity">Quantità in Stock</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo $product['stock_quantity'] ?? '0'; ?>">
                            </div>
                            <div class="form-group">
                                <label for="stock_status">Stato Stock</label>
                                <select id="stock_status" name="stock_status">
                                    <option value="in_stock" <?php echo (($product['stock_status'] ?? 'in_stock') === 'in_stock') ? 'selected' : ''; ?>>Disponibile</option>
                                    <option value="out_of_stock" <?php echo (($product['stock_status'] ?? '') === 'out_of_stock') ? 'selected' : ''; ?>>Esaurito</option>
                                    <option value="on_backorder" <?php echo (($product['stock_status'] ?? '') === 'on_backorder') ? 'selected' : ''; ?>>In Preordine</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="manage_stock" value="1" <?php echo (($product['manage_stock'] ?? 1) == 1) ? 'checked' : ''; ?>>
                                <span>Gestisci Stock</span>
                            </label>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="form-section">
                        <h3>Immagini</h3>
                        <div class="form-group">
                            <label>Immagine Principale</label>
                            <input type="hidden" id="image" name="image" value="<?php echo htmlspecialchars($product['image'] ?? ''); ?>">
                            <div class="file-upload-wrapper">
                                <div class="file-upload-btn" onclick="document.getElementById('main-image-input').click()">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    <span id="main-image-label">Scegli immagine principale</span>
                                </div>
                                <input type="file" id="main-image-input" accept="image/*" onchange="uploadMainImage(this)">
                            </div>
                            <div class="upload-progress" id="main-image-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="main-image-progress-fill"></div>
                                </div>
                            </div>
                            <div class="upload-preview" id="main-image-preview">
                                <?php if (!empty($product['image'])): ?>
                                    <div class="upload-preview-item">
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Preview">
                                        <button type="button" class="remove-preview" onclick="removeMainImage()">×</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Gallery Immagini</label>
                            <input type="hidden" id="gallery" name="gallery" value="<?php echo htmlspecialchars($product['gallery'] ?? ''); ?>">
                            <div class="file-upload-wrapper">
                                <div class="file-upload-btn" onclick="document.getElementById('gallery-input').click()">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    <span>Aggiungi immagini alla gallery</span>
                                </div>
                                <input type="file" id="gallery-input" accept="image/*" multiple onchange="uploadGalleryImages(this)">
                            </div>
                            <div class="upload-progress" id="gallery-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="gallery-progress-fill"></div>
                                </div>
                            </div>
                            <div class="upload-preview" id="gallery-preview">
                                <?php 
                                if (!empty($product['gallery']) && strpos($product['gallery'], '|SPECS|') === false):
                                    $gallery_images = explode(',', $product['gallery']);
                                    foreach ($gallery_images as $img_url):
                                        $img_url = trim($img_url);
                                        if (!empty($img_url)):
                                ?>
                                    <div class="upload-preview-item" data-url="<?php echo htmlspecialchars($img_url); ?>">
                                        <img src="<?php echo htmlspecialchars($img_url); ?>" alt="Gallery">
                                        <button type="button" class="remove-preview" onclick="removeGalleryImage(this)">×</button>
                                    </div>
                                <?php 
                                        endif;
                                    endforeach;
                                endif; 
                                ?>
                            </div>
                            <small>Puoi selezionare più immagini contemporaneamente</small>
                        </div>
                    </div>

                    <!-- Product Specs -->
                    <div class="form-section">
                        <h3>Specifiche Prodotto</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="weight">Peso (g)</label>
                                <input type="number" id="weight" name="weight" step="0.01" min="0" value="<?php echo $product['weight'] ?? ''; ?>" placeholder="es. 150">
                            </div>
                            <div class="form-group">
                                <label for="dimensions">Dimensioni</label>
                                <input type="text" id="dimensions" name="dimensions" value="<?php echo htmlspecialchars($product['dimensions'] ?? ''); ?>" placeholder="es. 12x10x8 cm">
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Specifiche Aggiuntive</label>
                            <div class="specs-manager" id="specs-manager">
                                <div id="specs-list">
                                    <!-- Specs will be added here via JS -->
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="addSpec()" style="margin-top: 12px;">+ Aggiungi Specifica</button>
                            </div>
                            <small>Le specifiche verranno mostrate nella pagina prodotto (es. Altezza: 12 cm, Materiale: PLA Premium)</small>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="form-section">
                        <h3>Impostazioni</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Stato</label>
                                <select id="status" name="status">
                                    <option value="draft" <?php echo (($product['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Bozza</option>
                                    <option value="active" <?php echo (($product['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Attivo</option>
                                    <option value="inactive" <?php echo (($product['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inattivo</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="featured" value="1" <?php echo (($product['featured'] ?? 0) == 1) ? 'checked' : ''; ?>>
                                    <span>Prodotto in Evidenza</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_drop" value="1" id="is_drop" <?php echo (($product['is_drop'] ?? 0) == 1) ? 'checked' : ''; ?>>
                                <span>È un Drop Esclusivo</span>
                            </label>
                        </div>
                        <div class="form-row" id="drop-dates" style="<?php echo (($product['is_drop'] ?? 0) == 1) ? '' : 'display: none;'; ?>">
                            <div class="form-group">
                                <label for="drop_start_date">Data Inizio Drop</label>
                                <input type="datetime-local" id="drop_start_date" name="drop_start_date" value="<?php echo $product['drop_start_date'] ? date('Y-m-d\TH:i', strtotime($product['drop_start_date'])) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="drop_end_date">Data Fine Drop</label>
                                <input type="datetime-local" id="drop_end_date" name="drop_end_date" value="<?php echo $product['drop_end_date'] ? date('Y-m-d\TH:i', strtotime($product['drop_end_date'])) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="submit" class="btn btn-primary">Salva Prodotto</button>
                        <a href="products.php" class="btn btn-secondary">Annulla</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-generate slug from name
        document.getElementById('name')?.addEventListener('input', function() {
            const slugInput = document.getElementById('slug');
            if (!slugInput.value || slugInput.dataset.autoGenerated === 'true') {
                const slug = this.value.toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9 -]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                slugInput.value = slug;
                slugInput.dataset.autoGenerated = 'true';
            }
        });

        document.getElementById('slug')?.addEventListener('input', function() {
            this.dataset.autoGenerated = 'false';
        });

        // Toggle drop dates
        document.getElementById('is_drop')?.addEventListener('change', function() {
            document.getElementById('drop-dates').style.display = this.checked ? 'grid' : 'none';
        });

        // Specs management
        let specCount = 0;
        const defaultSpecs = <?php echo json_encode([
            'Altezza' => '12 cm',
            'Materiale' => 'PLA Premium',
            'Peso' => '150g',
            'Edizione' => '1/500',
            'Finitura' => 'Matte + Glossy Details'
        ]); ?>;

        function addSpec(key = '', value = '') {
            const list = document.getElementById('specs-list');
            const div = document.createElement('div');
            div.className = 'spec-item';
            div.innerHTML = `
                <input type="text" name="spec_key[]" placeholder="Nome specifica" value="${key}" required>
                <input type="text" name="spec_value[]" placeholder="Valore" value="${value}" required>
                <button type="button" class="btn btn-secondary btn-small" onclick="this.parentElement.remove()">Rimuovi</button>
            `;
            list.appendChild(div);
            specCount++;
        }

        // Load existing specs or initialize defaults
        window.addEventListener('load', function() {
            <?php if ($product && !empty($product_specs)): ?>
                // Load existing specs
                const existingSpecs = <?php echo json_encode($product_specs); ?>;
                Object.entries(existingSpecs).forEach(([key, value]) => {
                    addSpec(key, value);
                });
            <?php else: ?>
                // Initialize default specs for new product
                Object.entries(defaultSpecs).forEach(([key, value]) => {
                    addSpec(key, value);
                });
            <?php endif; ?>
        });
        
        // Image Upload Functions
        function uploadMainImage(input) {
            if (!input.files || !input.files[0]) return;
            
            const file = input.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', 'product');
            
            const progressDiv = document.getElementById('main-image-progress');
            const progressFill = document.getElementById('main-image-progress-fill');
            const previewDiv = document.getElementById('main-image-preview');
            const imageInput = document.getElementById('image');
            const label = document.getElementById('main-image-label');
            
            progressDiv.classList.add('active');
            progressFill.style.width = '0%';
            label.textContent = 'Caricamento...';
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressFill.style.width = percentComplete + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        imageInput.value = response.url;
                        previewDiv.innerHTML = `
                            <div class="upload-preview-item">
                                <img src="${response.url}" alt="Preview">
                                <button type="button" class="remove-preview" onclick="removeMainImage()">×</button>
                            </div>
                        `;
                        label.textContent = 'Immagine caricata';
                        progressDiv.classList.remove('active');
                    } else {
                        alert('Errore: ' + (response.error || 'Caricamento fallito'));
                        label.textContent = 'Scegli immagine principale';
                        progressDiv.classList.remove('active');
                    }
                } else {
                    alert('Errore durante il caricamento');
                    label.textContent = 'Scegli immagine principale';
                    progressDiv.classList.remove('active');
                }
                input.value = '';
            });
            
            xhr.addEventListener('error', function() {
                alert('Errore di connessione');
                label.textContent = 'Scegli immagine principale';
                progressDiv.classList.remove('active');
                input.value = '';
            });
            
            xhr.open('POST', 'upload.php');
            xhr.send(formData);
        }
        
        function removeMainImage() {
            document.getElementById('image').value = '';
            document.getElementById('main-image-preview').innerHTML = '';
            document.getElementById('main-image-label').textContent = 'Scegli immagine principale';
        }
        
        function uploadGalleryImages(input) {
            if (!input.files || input.files.length === 0) return;
            
            const files = Array.from(input.files);
            const galleryInput = document.getElementById('gallery');
            const previewDiv = document.getElementById('gallery-preview');
            const progressDiv = document.getElementById('gallery-progress');
            const progressFill = document.getElementById('gallery-progress-fill');
            
            progressDiv.classList.add('active');
            progressFill.style.width = '0%';
            
            let uploaded = 0;
            const total = files.length;
            const currentUrls = galleryInput.value ? galleryInput.value.split(',').filter(u => u.trim()) : [];
            
            files.forEach((file, index) => {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('type', 'product');
                
                const xhr = new XMLHttpRequest();
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            currentUrls.push(response.url);
                            galleryInput.value = currentUrls.join(',');
                            
                            const previewItem = document.createElement('div');
                            previewItem.className = 'upload-preview-item';
                            previewItem.setAttribute('data-url', response.url);
                            previewItem.innerHTML = `
                                <img src="${response.url}" alt="Gallery">
                                <button type="button" class="remove-preview" onclick="removeGalleryImage(this)">×</button>
                            `;
                            previewDiv.appendChild(previewItem);
                        }
                    }
                    
                    uploaded++;
                    progressFill.style.width = ((uploaded / total) * 100) + '%';
                    
                    if (uploaded === total) {
                        progressDiv.classList.remove('active');
                    }
                });
                
                xhr.addEventListener('error', function() {
                    uploaded++;
                    if (uploaded === total) {
                        progressDiv.classList.remove('active');
                    }
                });
                
                xhr.open('POST', 'upload.php');
                xhr.send(formData);
            });
            
            input.value = '';
        }
        
        function removeGalleryImage(btn) {
            const item = btn.closest('.upload-preview-item');
            const url = item.getAttribute('data-url');
            const galleryInput = document.getElementById('gallery');
            const urls = galleryInput.value ? galleryInput.value.split(',').filter(u => u.trim() !== url.trim()) : [];
            galleryInput.value = urls.join(',');
            item.remove();
        }
    </script>
</body>
</html>

