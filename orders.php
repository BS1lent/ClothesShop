<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Получаем заказы пользователя
$stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.id) as items_count,
           GROUP_CONCAT(oi.product_name SEPARATOR ', ') as product_names
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем количество товаров в корзине для навигации
$stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$cart_count = $result['total'] ?? 0;

function getStatusText($status) {
    switch ($status) {
        case 'pending': return 'Ожидает подтверждения';
        case 'approved': return 'Подтвержден';
        case 'rejected': return 'Отклонен';
        case 'delivered': return 'Доставлен';
        default: return $status;
    }
}

function getStatusClass($status) {
    switch ($status) {
        case 'pending': return 'status-pending';
        case 'approved': return 'status-approved';
        case 'rejected': return 'status-rejected';
        case 'delivered': return 'status-delivered';
        default: return '';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заказы - ClothingStore</title>
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
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> 
                            <?= htmlspecialchars($_SESSION['first_name'] ?? 'Пользователь') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Профиль</a></li>
                            <li><a class="dropdown-item active" href="orders.php"><i class="fas fa-box me-2"></i>Заказы</a></li>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-box me-2"></i>Мои заказы</h2>
            <a href="catalog.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag me-2"></i>Продолжить покупки
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h4>У вас пока нет заказов</h4>
                <p>Оформите первый заказ в нашем каталоге товаров</p>
                <a href="catalog.php" class="btn btn-primary">Перейти в каталог</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($orders as $order): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Заказ #<?= $order['id'] ?></h6>
                                <span class="badge <?= getStatusClass($order['status']) ?>">
                                    <?= getStatusText($order['status']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted">Дата заказа:</small><br>
                                    <strong><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></strong>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Товары (<?= $order['items_count'] ?>):</small><br>
                                    <span class="small">
                                        <?= htmlspecialchars(strlen($order['product_names']) > 100 ? 
                                            substr($order['product_names'], 0, 100) . '...' : 
                                            $order['product_names']) ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Получатель:</small><br>
                                    <strong><?= htmlspecialchars($order['customer_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($order['customer_phone']) ?></small>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Адрес доставки:</small><br>
                                    <span class="small"><?= htmlspecialchars($order['customer_address']) ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">Сумма:</small><br>
                                        <h5 class="text-primary mb-0"><?= number_format($order['total_amount'], 0, ',', ' ') ?> ₽</h5>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="showOrderDetails(<?= $order['id'] ?>)">
                                        <i class="fas fa-info-circle me-1"></i>Подробнее
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($order['status'] === 'pending'): ?>
                                <div class="card-footer">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>Ожидает подтверждения от магазина
                                    </small>
                                </div>
                            <?php elseif ($order['status'] === 'approved'): ?>
                                <div class="card-footer">
                                    <small class="text-success">
                                        <i class="fas fa-check me-1"></i>Заказ подтвержден, готовится к отправке
                                    </small>
                                </div>
                            <?php elseif ($order['status'] === 'delivered'): ?>
                                <div class="card-footer">
                                    <small class="text-success">
                                        <i class="fas fa-truck me-1"></i>Заказ доставлен
                                    </small>
                                </div>
                            <?php elseif ($order['status'] === 'rejected'): ?>
                                <div class="card-footer">
                                    <small class="text-danger">
                                        <i class="fas fa-times me-1"></i>Заказ был отклонен
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Модальное окно с деталями заказа -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Детали заказа</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Загрузка...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showOrderDetails(orderId) {
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            const content = document.getElementById('orderDetailsContent');
            
            // Показываем модальное окно с загрузкой
            modal.show();
            
            // Загружаем детали заказа
            fetch(`order_details.php?id=${orderId}`)
            .then(response => response.text())
            .then(html => {
                content.innerHTML = html;
            })
            .catch(error => {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Ошибка загрузки деталей заказа
                    </div>
                `;
            });
        }
    </script>
    <script src="script.js"></script>
</body>
</html>