<?php
require_once 'config.php';

if (!isLoggedIn() || !isStore()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Обработка добавления товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['product_name']);
    $description = trim($_POST['product_description']);
    $price = (float)$_POST['product_price'];
    $category_id = (int)$_POST['category_id'];
    $image_url = trim($_POST['image_url']);
    
    if (empty($name) || $price <= 0 || !$category_id) {
        $error = 'Заполните все обязательные поля';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, store_user_id, image_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $category_id, $_SESSION['user_id'], $image_url]);
            $success = 'Товар успешно добавлен!';
        } catch (Exception $e) {
            $error = 'Ошибка при добавлении товара';
        }
    }
}

// Обработка изменения статуса заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ? AND store_user_id = ?");
        $stmt->execute([$new_status, $order_id, $_SESSION['user_id']]);
        $success = 'Статус заказа обновлен!';
    } catch (Exception $e) {
        $error = 'Ошибка при обновлении статуса заказа';
    }
}

// Обработка удаления товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ? AND store_user_id = ?");
        $stmt->execute([$product_id, $_SESSION['user_id']]);
        $success = 'Товар удален!';
    } catch (Exception $e) {
        $error = 'Ошибка при удалении товара';
    }
}

// Получаем статистику магазина
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE store_user_id = ? AND is_active = 1");
$stmt->execute([$_SESSION['user_id']]);
$total_products = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE store_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_orders = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE store_user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_orders = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM orders WHERE store_user_id = ? AND status = 'delivered'");
$stmt->execute([$_SESSION['user_id']]);
$total_revenue = $stmt->fetch()['total'] ?? 0;

// Получаем товары магазина
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.store_user_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$store_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем заказы магазина
$stmt = $pdo->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email,
           COUNT(oi.id) as items_count
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.store_user_id = ? 
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$store_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем категории для формы
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем информацию о пользователе
$user = getCurrentUser($pdo);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель магазина - ClothingStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <i class="fas fa-tshirt me-2"></i>ClothingStore
            </a>
            
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="catalog.php">Каталог</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-store"></i> 
                            <?= htmlspecialchars($user['store_name'] ?? 'Мой магазин') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Профиль</a></li>
                            <li><a class="dropdown-item" href="orders.php"><i class="fas fa-box me-2"></i>Мои заказы</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item active" href="store.php"><i class="fas fa-store me-2"></i>Панель магазина</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выйти</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-store me-2"></i>Панель магазина</h2>
                <p class="text-muted mb-0">Управление товарами и заказами</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <i class="fas fa-box fa-2x mb-2"></i>
                        <h4><?= $total_products ?></h4>
                        <p class="mb-0">Товаров</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                        <h4><?= $total_orders ?></h4>
                        <p class="mb-0">Заказов</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <h4><?= $pending_orders ?></h4>
                        <p class="mb-0">Ожидают</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <i class="fas fa-ruble-sign fa-2x mb-2"></i>
                        <h4><?= number_format($total_revenue, 0, ',', ' ') ?></h4>
                        <p class="mb-0">Выручка (₽)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Табы -->
        <ul class="nav nav-tabs" id="storeTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="add-product-tab" data-bs-toggle="tab" data-bs-target="#add-product" type="button">
                    <i class="fas fa-plus me-2"></i>Добавить товар
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button">
                    <i class="fas fa-box me-2"></i>Мои товары (<?= count($store_products) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button">
                    <i class="fas fa-shopping-cart me-2"></i>Заказы (<?= count($store_orders) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="storeTabsContent">
            <!-- Добавление товара -->
            <div class="tab-pane fade show active" id="add-product" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Добавить новый товар</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="product_name" class="form-label">Название товара *</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="product_description" class="form-label">Описание</label>
                                        <textarea class="form-control" id="product_description" name="product_description" rows="4"></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="product_price" class="form-label">Цена (₽) *</label>
                                            <input type="number" class="form-control" id="product_price" name="product_price" step="0.01" min="0" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label">Категория *</label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Выберите категорию</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="image_url" class="form-label">Ссылка на изображение</label>
                                        <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg">
                                        <small class="form-text text-muted">Укажите URL изображения товара</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Предварительный просмотр</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <div id="image-preview" class="mb-3" style="height: 200px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-image text-muted fa-2x"></i>
                                            </div>
                                            <h6 id="name-preview" class="text-muted">Название товара</h6>
                                            <p id="price-preview" class="text-primary fw-bold">0 ₽</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_product" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Добавить товар
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Товары -->
            <div class="tab-pane fade" id="products" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Мои товары</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($store_products)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5>Товары не добавлены</h5>
                                <p class="text-muted">Добавьте первый товар в свой магазин</p>
                                <button class="btn btn-primary" onclick="document.getElementById('add-product-tab').click()">
                                    <i class="fas fa-plus me-2"></i>Добавить товар
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($store_products as $product): ?>
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                                <?php if ($product['image_url']): ?>
                                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                         alt="<?= htmlspecialchars($product['name']) ?>" 
                                                         class="img-fluid" style="max-height: 100%; max-width: 100%;">
                                                <?php else: ?>
                                                    <i class="fas fa-image text-muted fa-2x"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                                                <p class="text-muted small mb-2"><?= htmlspecialchars($product['category_name'] ?? '') ?></p>
                                                <p class="card-text small"><?= htmlspecialchars(substr($product['description'] ?? '', 0, 100)) ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h5 class="text-primary mb-0"><?= number_format($product['price'], 0, ',', ' ') ?> ₽</h5>
                                                    <span class="badge <?= $product['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                                        <?= $product['is_active'] ? 'Активен' : 'Скрыт' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-footer">
                                                <div class="btn-group w-100">
                                                    <button class="btn btn-outline-primary btn-sm" onclick="editProduct(<?= $product['id'] ?>)">
                                                        <i class="fas fa-edit"></i> Редактировать
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                        <button type="submit" name="delete_product" class="btn btn-outline-danger btn-sm" onclick="return confirm('Удалить товар?')">
                                                            <i class="fas fa-trash"></i> Удалить
                                                        </button>
                                                    </form>
                                                </div>
                                                <small class="text-muted">Добавлен: <?= date('d.m.Y', strtotime($product['created_at'])) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Заказы -->
            <div class="tab-pane fade" id="orders" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Заказы магазина</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($store_orders)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h5>Заказов пока нет</h5>
                                <p class="text-muted">Когда покупатели оформят заказы, они появятся здесь</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Заказ</th>
                                            <th>Покупатель</th>
                                            <th>Товары</th>
                                            <th>Сумма</th>
                                            <th>Статус</th>
                                            <th>Дата</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($store_orders as $order): ?>
                                            <tr>
                                                <td>#<?= $order['id'] ?></td>
                                                <td>
                                                    <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                                </td>
                                                <td><?= $order['items_count'] ?> шт.</td>
                                                <td><?= number_format($order['total_amount'], 0, ',', ' ') ?> ₽</td>
                                                <td>
                                                    <?php
                                                    switch ($order['status']) {
                                                        case 'pending': echo '<span class="badge status-pending">Ожидает</span>'; break;
                                                        case 'approved': echo '<span class="badge status-approved">Подтвержден</span>'; break;
                                                        case 'delivered': echo '<span class="badge status-delivered">Доставлен</span>'; break;
                                                        case 'rejected': echo '<span class="badge status-rejected">Отклонен</span>'; break;
                                                    }
                                                    ?>
                                                </td>
                                                <td><?= date('d.m.Y', strtotime($order['created_at'])) ?></td>
                                                <td>
                                                    <?php if ($order['status'] === 'pending'): ?>
                                                        <div class="btn-group">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                                <input type="hidden" name="new_status" value="approved">
                                                                <button type="submit" name="update_order_status" class="btn btn-success btn-sm">
                                                                    <i class="fas fa-check"></i> Принять
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                                <input type="hidden" name="new_status" value="rejected">
                                                                <button type="submit" name="update_order_status" class="btn btn-danger btn-sm">
                                                                    <i class="fas fa-times"></i> Отклонить
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php elseif ($order['status'] === 'approved'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                            <input type="hidden" name="new_status" value="delivered">
                                                            <button type="submit" name="update_order_status" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-truck"></i> Доставлен
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-primary btn-sm" onclick="showOrderDetails(<?= $order['id'] ?>)">
                                                            <i class="fas fa-eye"></i> Просмотр
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Предварительный просмотр товара
        document.getElementById('product_name').addEventListener('input', function() {
            document.getElementById('name-preview').textContent = this.value || 'Название товара';
        });
        
        document.getElementById('product_price').addEventListener('input', function() {
            const price = parseFloat(this.value) || 0;
            document.getElementById('price-preview').textContent = price.toLocaleString() + ' ₽';
        });
        
        document.getElementById('image_url').addEventListener('input', function() {
            const imagePreview = document.getElementById('image-preview');
            if (this.value) {
                imagePreview.innerHTML = `<img src="${this.value}" class="img-fluid" style="max-height: 100%; max-width: 100%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                         <div style="display: none; align-items: center; justify-content: center; height: 100%; width: 100%;"><i class="fas fa-exclamation-triangle text-warning fa-2x"></i></div>`;
            } else {
                imagePreview.innerHTML = '<i class="fas fa-image text-muted fa-2x"></i>';
            }
        });

        function editProduct(productId) {
            // Здесь можно добавить модальное окно для редактирования
            alert('Функция редактирования будет добавлена в следующей версии');
        }

        function showOrderDetails(orderId) {
            // Открываем детали заказа
            window.open(`order_details.php?id=${orderId}`, '_blank', 'width=800,height=600');
        }
    </script>
    <script src="script.js"></script>
</body>
</html>