<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Пожалуйста, заполните все обязательные поля';
    } else {
        // Проверяем, не занят ли email другим пользователем
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            $error = 'Этот email уже используется другим пользователем';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $email, $phone, $_SESSION['user_id']]);
                
                // Обновляем данные в сессии
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $_SESSION['email'] = $email;
                
                $success = 'Профиль успешно обновлен!';
            } catch (Exception $e) {
                $error = 'Ошибка при обновлении профиля';
            }
        }
    }
}

// Получаем актуальные данные пользователя
$user = getCurrentUser($pdo);

// Получаем статистику пользователя
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_spent FROM orders WHERE user_id = ? AND status = 'delivered'");
$stmt->execute([$_SESSION['user_id']]);
$total_spent = $stmt->fetch(PDO::FETCH_ASSOC)['total_spent'] ?? 0;

// Получаем количество товаров в корзине для навигации
$stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$cart_count = $result['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль - ClothingStore</title>
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
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $cart_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> 
                            <?= htmlspecialchars($user['first_name'] ?? 'Пользователь') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i>Профиль</a></li>
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
        <div class="row">
            <!-- Боковая панель -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-user fa-2x"></i>
                            </div>
                        </div>
                        <h5 class="card-title"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                        <p class="card-text text-muted"><?= htmlspecialchars($user['email']) ?></p>
                        
                        <?php
                        $type_text = '';
                        $type_class = '';
                        switch ($user['user_type']) {
                            case 'admin':
                                $type_text = 'Администратор';
                                $type_class = 'bg-danger';
                                break;
                            case 'store':
                                $type_text = 'Магазин';
                                $type_class = 'bg-success';
                                break;
                            default:
                                $type_text = 'Покупатель';
                                $type_class = 'bg-primary';
                        }
                        ?>
                        <span class="badge <?= $type_class ?>"><?= $type_text ?></span>
                        
                        <?php if ($user['store_name']): ?>
                            <div class="mt-2">
                                <small class="text-muted">Магазин:</small><br>
                                <strong><?= htmlspecialchars($user['store_name']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Быстрая навигация -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Навигация</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="orders.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-box me-2"></i>Мои заказы
                        </a>
                        <a href="cart.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-shopping-cart me-2"></i>Корзина
                        </a>
                        <?php if (isStore()): ?>
                            <a href="store.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-store me-2"></i>Мой магазин
                            </a>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                            <a href="admin.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog me-2"></i>Админ панель
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Основной контент -->
            <div class="col-lg-9">
                <!-- Статистика -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-shopping-bag text-primary fa-2x mb-2"></i>
                                <h4 class="text-primary"><?= $user_stats['total_orders'] ?></h4>
                                <p class="card-text">Всего заказов</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-ruble-sign text-success fa-2x mb-2"></i>
                                <h4 class="text-success"><?= number_format($total_spent, 0, ',', ' ') ?> ₽</h4>
                                <p class="card-text">Потрачено</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Форма редактирования профиля -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Редактировать профиль</h5>
                    </div>
                    <div class="card-body">
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
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Имя *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Фамилия *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Телефон</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Тип аккаунта</label>
                                <input type="text" class="form-control" value="<?= $type_text ?>" readonly>
                                <small class="form-text text-muted">Тип аккаунта можно изменить только через администратора</small>
                            </div>
                            
                            <?php if ($user['store_name']): ?>
                                <div class="mb-3">
                                    <label class="form-label">Название магазина</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['store_name']) ?>" readonly>
                                    <small class="form-text text-muted">Название магазина можно изменить через администратора</small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Дата регистрации:</strong> <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?><br>
                                    <strong>Последнее обновление:</strong> <?= date('d.m.Y H:i', strtotime($user['updated_at'])) ?>
                                </small>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Сохранить изменения
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Смена пароля -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Безопасность</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Для смены пароля обратитесь к администратору сайта.</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Статус аккаунта:</strong> 
                            <?php if ($user['is_active']): ?>
                                <span class="text-success">Активен</span>
                            <?php else: ?>
                                <span class="text-danger">Заблокирован</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>