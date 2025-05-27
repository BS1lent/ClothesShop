<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_to_cart':
                $product_id = (int)$_POST['product_id'];
                $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
                
                // Проверяем существование товара
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    throw new Exception('Товар не найден');
                }
                
                // Проверяем, есть ли уже товар в корзине
                $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$_SESSION['user_id'], $product_id]);
                $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_item) {
                    // Обновляем количество
                    $new_quantity = $existing_item['quantity'] + $quantity;
                    $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_quantity, $existing_item['id']]);
                } else {
                    // Добавляем новый товар
                    $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Товар добавлен в корзину']);
                exit;
                
            case 'update_quantity':
                $item_id = (int)$_POST['item_id'];
                $quantity = (int)$_POST['quantity'];
                
                if ($quantity <= 0) {
                    throw new Exception('Количество должно быть больше 0');
                }
                
                $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$quantity, $item_id, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Количество обновлено']);
                exit;
                
            case 'remove_item':
                $item_id = (int)$_POST['item_id'];
                
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                $stmt->execute([$item_id, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Товар удален']);
                exit;
                
            case 'clear_cart':
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Корзина очищена']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Обработка оформления заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_address = trim($_POST['customer_address']);
    
    if (empty($customer_name) || empty($customer_phone) || empty($customer_address)) {
        $error = 'Пожалуйста, заполните все поля';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Получаем товары в корзине
            $stmt = $pdo->prepare("
                SELECT ci.*, p.name, p.price, p.store_user_id 
                FROM cart_items ci 
                JOIN products p ON ci.product_id = p.id 
                WHERE ci.user_id = ? AND p.is_active = 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($cart_items)) {
                throw new Exception('Корзина пуста');
            }
            
            // Группируем товары по магазинам
            $orders_by_store = [];
            foreach ($cart_items as $item) {
                $store_id = $item['store_user_id'] ?? 'admin';
                if (!isset($orders_by_store[$store_id])) {
                    $orders_by_store[$store_id] = [];
                }
                $orders_by_store[$store_id][] = $item;
            }
            
            // Создаем отдельные заказы для каждого магазина
            foreach ($orders_by_store as $store_id => $items) {
                $total_amount = 0;
                foreach ($items as $item) {
                    $total_amount += $item['price'] * $item['quantity'];
                }
                
                // Создаем заказ
                $stmt = $pdo->prepare("
                    INSERT INTO orders (user_id, store_user_id, total_amount, customer_name, customer_phone, customer_address) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $store_id === 'admin' ? null : $store_id,
                    $total_amount,
                    $customer_name,
                    $customer_phone,
                    $customer_address
                ]);
                
                $order_id = $pdo->lastInsertId();
                
                // Добавляем товары в заказ
                foreach ($items as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, price, product_name) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $order_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price'],
                        $item['name']
                    ]);
                }
            }
            
            // Очищаем корзину
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            $pdo->commit();
            $success = 'Заказ успешно оформлен!';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при оформлении заказа: ' . $e->getMessage();
        }
    }
}

// Получаем товары в корзине
$stmt = $pdo->prepare("
    SELECT ci.*, p.name, p.description, p.price, p.image_url, p.store_user_id, u.store_name 
    FROM cart_items ci 
    JOIN products p ON ci.product_id = p.id 
    LEFT JOIN users u ON p.store_user_id = u.id 
    WHERE ci.user_id = ? AND p.is_active = 1
    ORDER BY ci.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

// Получаем количество товаров в корзине для навигации
$cart_count = array_sum(array_column($cart_items, 'quantity'));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина - ClothingStore</title>
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
                    <li class="nav-item">
                        <a class="nav-link position-relative active" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Корзина
                            <?php if ($cart_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $cart_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> 
                            <?= htmlspecialchars($_SESSION['first_name'] ?? 'Пользователь') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Профиль</a></li>
                            <li><a class="dropdown-item" href="orders.php"><i class="fas fa-box me-2"></i>Заказы</a></li>
                            <?php if (isStore()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="store.php"><i class="fas fa-store me-2"></i>Мой магазин</a></li>
                            <?php endif; ?>
                            <?php if (isAdmin()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="admin.php"><i class="fas fa-cog me-2"></i>Админ панель</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выйти</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h2 class="mb-4"><i class="fas fa-shopping-cart me-2"></i>Корзина</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <div class="mt-2">
                    <a href="orders.php" class="btn btn-success btn-sm me-2">Мои заказы</a>
                    <a href="catalog.php" class="btn btn-primary btn-sm">Продолжить покупки</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h4>Ваша корзина пуста</h4>
                <p>Добавьте товары из каталога, чтобы сделать заказ</p>
                <a href="catalog.php" class="btn btn-primary">Перейти в каталог</a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Список товаров -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Товары в корзине (<?= count($cart_items) ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item p-3 border-bottom" data-item-id="<?= $item['id'] ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            <div class="bg-light d-flex align-items-center justify-content-center" style="height: 80px; border-radius: 8px;">
                                                <?php if ($item['image_url']): ?>
                                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                                         class="img-fluid" style="max-height: 100%; max-width: 100%;">
                                                <?php else: ?>
                                                    <i class="fas fa-image text-muted"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                            <?php if ($item['store_name']): ?>
                                                <small class="text-primary">
                                                    <i class="fas fa-store me-1"></i><?= htmlspecialchars($item['store_name']) ?>
                                                </small>
                                            <?php endif; ?>
                                            <div class="text-muted small">
                                                <?= number_format($item['price'], 0, ',', ' ') ?> ₽ за штуку
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="quantity-controls">
                                                <button class="btn btn-outline-secondary btn-sm" onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)">-</button>
                                                <input type="number" class="form-control form-control-sm text-center mx-2" 
                                                       value="<?= $item['quantity'] ?>" min="1" style="width: 60px;"
                                                       onchange="updateQuantity(<?= $item['id'] ?>, this.value)">
                                                <button class="btn btn-outline-secondary btn-sm" onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)">+</button>
                                            </div>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <div class="fw-bold text-primary">
                                                <?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> ₽
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <button class="btn btn-outline-danger btn-sm" onclick="removeItem(<?= $item['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-outline-secondary" onclick="clearCart()">
                                <i class="fas fa-trash me-2"></i>Очистить корзину
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Оформление заказа -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Итого</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Товары (<?= count($cart_items) ?>):</span>
                                <span><?= number_format($total_amount, 0, ',', ' ') ?> ₽</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Итого:</span>
                                <span class="text-primary"><?= number_format($total_amount, 0, ',', ' ') ?> ₽</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Форма оформления заказа -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Оформление заказа</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="customer_name" class="form-label">Имя получателя *</label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                           value="<?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="customer_phone" class="form-label">Телефон *</label>
                                    <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="customer_address" class="form-label">Адрес доставки *</label>
                                    <textarea class="form-control" id="customer_address" name="customer_address" rows="3" required></textarea>
                                </div>
                                
                                <button type="submit" name="checkout" class="btn btn-primary w-100">
                                    <i class="fas fa-check me-2"></i>Оформить заказ
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateQuantity(itemId, quantity) {
            if (quantity < 1) {
                removeItem(itemId);
                return;
            }
            
            fetch('cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=update_quantity&item_id=${itemId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }
        
        function removeItem(itemId) {
            if (confirm('Удалить товар из корзины?')) {
                fetch('cart.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=remove_item&item_id=${itemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
        
        function clearCart() {
            if (confirm('Очистить всю корзину?')) {
                fetch('cart.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=clear_cart'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
    </script>
    <script src="script.js"></script>
</body>
</html>