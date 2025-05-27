<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo '<div class="alert alert-danger">Необходима авторизация</div>';
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    echo '<div class="alert alert-danger">Заказ не найден</div>';
    exit;
}

// Получаем заказ
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo '<div class="alert alert-danger">Заказ не найден или у вас нет прав для его просмотра</div>';
    exit;
}

// Получаем товары заказа
$stmt = $pdo->prepare("
    SELECT oi.*, p.image_url 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<div class="order-details">
    <!-- Информация о заказе -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h6>Информация о заказе</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Номер заказа:</strong></td>
                    <td>#<?= $order['id'] ?></td>
                </tr>
                <tr>
                    <td><strong>Дата:</strong></td>
                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                </tr>
                <tr>
                    <td><strong>Статус:</strong></td>
                    <td><span class="badge <?= getStatusClass($order['status']) ?>"><?= getStatusText($order['status']) ?></span></td>
                </tr>
                <tr>
                    <td><strong>Сумма:</strong></td>
                    <td><strong class="text-primary"><?= number_format($order['total_amount'], 0, ',', ' ') ?> ₽</strong></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6>Информация о доставке</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Получатель:</strong></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                </tr>
                <tr>
                    <td><strong>Телефон:</strong></td>
                    <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                </tr>
                <tr>
                    <td><strong>Адрес:</strong></td>
                    <td><?= htmlspecialchars($order['customer_address']) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Товары в заказе -->
    <h6>Товары в заказе</h6>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Товар</th>
                    <th>Количество</th>
                    <th>Цена за шт.</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="me-3" style="width: 50px; height: 50px;">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                             alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                             class="img-fluid rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><?= $item['quantity'] ?> шт.</td>
                        <td><?= number_format($item['price'], 0, ',', ' ') ?> ₽</td>
                        <td><strong><?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> ₽</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-primary">
                    <th colspan="3">Итого:</th>
                    <th><?= number_format($order['total_amount'], 0, ',', ' ') ?> ₽</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Статус заказа -->
    <div class="mt-4">
        <?php if ($order['status'] === 'pending'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock me-2"></i>
                <strong>Ожидается подтверждение</strong><br>
                Ваш заказ передан магазину и ожидает подтверждения. Обычно это занимает от нескольких часов до 1 рабочего дня.
            </div>
        <?php elseif ($order['status'] === 'approved'): ?>
            <div class="alert alert-info">
                <i class="fas fa-check me-2"></i>
                <strong>Заказ подтвержден</strong><br>
                Ваш заказ подтвержден магазином и готовится к отправке. Мы уведомим вас о статусе доставки.
            </div>
        <?php elseif ($order['status'] === 'delivered'): ?>
            <div class="alert alert-success">
                <i class="fas fa-truck me-2"></i>
                <strong>Заказ доставлен</strong><br>
                Ваш заказ успешно доставлен. Спасибо за покупку!
            </div>
        <?php elseif ($order['status'] === 'rejected'): ?>
            <div class="alert alert-danger">
                <i class="fas fa-times me-2"></i>
                <strong>Заказ отклонен</strong><br>
                К сожалению, ваш заказ был отклонен магазином. Возможно, товары закончились или возникли другие проблемы.
            </div>
        <?php endif; ?>
    </div>
</div>