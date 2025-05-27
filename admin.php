<?php
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Обработка создания категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    $name = trim($_POST['category_name']);
    $slug = trim($_POST['category_slug']);
    
    if (empty($name) || empty($slug)) {
        $error = 'Заполните все поля для категории';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            $success = 'Категория успешно создана!';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Категория с таким URL уже существует';
            } else {
                $error = 'Ошибка при создании категории';
            }
        }
    }
}

// Обработка изменения статуса пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_status'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['new_status'] === '1' ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);
    $success = 'Статус пользователя обновлен!';
}

// Обработка изменения типа пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_user_type'])) {
    $user_id = $_POST['user_id'];
    $new_type = $_POST['new_type'];
    $store_name = trim($_POST['store_name'] ?? '');
    
    if ($new_type === 'store' && empty($store_name)) {
        $error = 'Для магазина необходимо указать название';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET user_type = ?, store_name = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_type, $new_type === 'store' ? $store_name : null, $user_id]);
        $success = 'Тип пользователя обновлен!';
    }
}

// Получаем статистику
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['users'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
$stats['products'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$stats['orders'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
$stats['categories'] = $stmt->fetch()['total'];

// Получаем пользователей
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем категории
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем последние заказы
$stmt = $pdo->query("
    SELECT o.*, u.first_name, u.last_name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем все товары
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name, u.store_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN users u ON p.store_user_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT 20
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель - ClothingStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-tshirt me-2"></i>ClothingStore
            </a>
            
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Сайт</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> Администратор
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Профиль</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выйти</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-cog me-2"></i>Панель администратора</h2>
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
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?= $stats['users'] ?></h4>
                        <p class="mb-0">Пользователей</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <i class="fas fa-box fa-2x mb-2"></i>
                        <h4><?= $stats['products'] ?></h4>
                        <p class="mb-0">Товаров</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                        <h4><?= $stats['orders'] ?></h4>
                        <p class="mb-0">Заказов</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <i class="fas fa-tags fa-2x mb-2"></i>
                        <h4><?= $stats['categories'] ?></h4>
                        <p class="mb-0">Категорий</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Табы -->
        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">
                    <i class="fas fa-users me-2"></i>Пользователи
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button">
                    <i class="fas fa-tags me-2"></i>Категории
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button">
                    <i class="fas fa-box me-2"></i>Товары
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button">
                    <i class="fas fa-shopping-cart me-2"></i>Заказы
                </button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabsContent">
            <!-- Пользователи -->
            <div class="tab-pane fade show active" id="users" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Управление пользователями</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Имя</th>
                                        <th>Email</th>
                                        <th>Тип</th>
                                        <th>Магазин</th>
                                        <th>Статус</th>
                                        <th>Дата регистрации</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['id']) ?></td>
                                            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <?php
                                                switch ($user['user_type']) {
                                                    case 'admin': echo '<span class="badge bg-danger">Админ</span>'; break;
                                                    case 'store': echo '<span class="badge bg-success">Магазин</span>'; break;
                                                    default: echo '<span class="badge bg-primary">Покупатель</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($user['store_name'] ?? '-') ?></td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">Активен</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Заблокирован</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editUser('<?= $user['id'] ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="new_status" value="<?= $user['is_active'] ? '0' : '1' ?>">
                                                        <button type="submit" name="toggle_user_status" class="btn btn-sm btn-outline-<?= $user['is_active'] ? 'danger' : 'success' ?>">
                                                            <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Категории -->
            <div class="tab-pane fade" id="categories" role="tabpanel">
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Добавить категорию</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="category_name" class="form-label">Название *</label>
                                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="category_slug" class="form-label">URL (slug) *</label>
                                        <input type="text" class="form-control" id="category_slug" name="category_slug" required>
                                        <small class="form-text text-muted">Только латинские буквы, цифры и дефисы</small>
                                    </div>
                                    <button type="submit" name="create_category" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Добавить
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Существующие категории</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($categories)): ?>
                                    <p class="text-muted">Категории не созданы</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($categories as $category): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($category['name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($category['slug']) ?></small>
                                                </div>
                                                <small class="text-muted"><?= date('d.m.Y', strtotime($category['created_at'])) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Товары -->
            <div class="tab-pane fade" id="products" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Все товары</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Цена</th>
                                        <th>Категория</th>
                                        <th>Магазин</th>
                                        <th>Статус</th>
                                        <th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?= $product['id'] ?></td>
                                            <td><?= htmlspecialchars($product['name']) ?></td>
                                            <td><?= number_format($product['price'], 0, ',', ' ') ?> ₽</td>
                                            <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($product['store_name'] ?? '-') ?></td>
                                            <td>
                                                <?php if ($product['is_active']): ?>
                                                    <span class="badge bg-success">Активен</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Неактивен</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d.m.Y', strtotime($product['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Заказы -->
            <div class="tab-pane fade" id="orders" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Последние заказы</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Покупатель</th>
                                        <th>Сумма</th>
                                        <th>Статус</th>
                                        <th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['id'] ?></td>
                                            <td>
                                                <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                                            </td>
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
                                            <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно редактирования пользователя -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактировать пользователя</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body" id="editUserContent">
                        <!-- Содержимое загружается через AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" name="change_user_type" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Автоматическая генерация slug из названия категории
        document.getElementById('category_name').addEventListener('input', function() {
            const name = this.value;
            const slug = name
                .toLowerCase()
                .replace(/[а-я]/g, function(char) {
                    const map = {
                        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e',
                        'ё': 'yo', 'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k',
                        'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r',
                        'с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'ts',
                        'ч': 'ch', 'ш': 'sh', 'щ': 'sch', 'ы': 'y', 'э': 'e', 'ю': 'yu', 'я': 'ya'
                    };
                    return map[char] || char;
                })
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            
            document.getElementById('category_slug').value = slug;
        });

        function editUser(userId) {
            // Здесь можно загрузить форму редактирования пользователя через AJAX
            // Для простоты покажем базовую форму
            const content = `
                <input type="hidden" name="user_id" value="${userId}">
                <div class="mb-3">
                    <label for="new_type" class="form-label">Тип пользователя</label>
                    <select class="form-select" name="new_type" id="new_type" onchange="toggleStoreField()">
                        <option value="customer">Покупатель</option>
                        <option value="store">Магазин</option>
                        <option value="admin">Администратор</option>
                    </select>
                </div>
                <div class="mb-3" id="storeNameField" style="display: none;">
                    <label for="store_name" class="form-label">Название магазина</label>
                    <input type="text" class="form-control" name="store_name" id="store_name">
                </div>
            `;
            
            document.getElementById('editUserContent').innerHTML = content;
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        function toggleStoreField() {
            const userType = document.getElementById('new_type').value;
            const storeField = document.getElementById('storeNameField');
            
            if (userType === 'store') {
                storeField.style.display = 'block';
                document.getElementById('store_name').required = true;
            } else {
                storeField.style.display = 'none';
                document.getElementById('store_name').required = false;
            }
        }
    </script>
</body>
</html>