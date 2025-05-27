<?php
require_once 'config.php';

// Получаем категории для меню
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем последние товары для главной страницы
$stmt = $pdo->query("SELECT p.*, c.name as category_name, u.first_name, u.last_name, u.store_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN users u ON p.store_user_id = u.id 
                     WHERE p.is_active = 1 
                     ORDER BY p.created_at DESC 
                     LIMIT 8");
$featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем количество товаров в корзине
$cart_count = 0;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cart_count = $result['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClothingStore - Интернет-магазин одежды</title>
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
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="catalog.php">Каталог</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="categoriesDropdown" role="button" data-bs-toggle="dropdown">
                            Категории
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($categories as $category): ?>
                                <li><a class="dropdown-item" href="catalog.php?category=<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
                
                <!-- Поиск -->
                <form class="d-flex me-3" action="catalog.php" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Поиск товаров..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                </form>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
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
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Войти</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Регистрация</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Главная секция -->
    <section class="hero bg-primary text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Добро пожаловать в ClothingStore</h1>
                    <p class="lead mb-4">Лучший интернет-магазин одежды с широким ассортиментом и удобным интерфейсом</p>
                    <a href="catalog.php" class="btn btn-light btn-lg">Начать покупки</a>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-tshirt display-1"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Категории -->
    <?php if (!empty($categories)): ?>
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Категории товаров</h2>
            <div class="row">
                <?php foreach ($categories as $category): ?>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 shadow-sm category-card">
                            <div class="card-body text-center">
                                <i class="fas fa-tags text-primary mb-3" style="font-size: 2rem;"></i>
                                <h5 class="card-title"><?= htmlspecialchars($category['name']) ?></h5>
                                <a href="catalog.php?category=<?= $category['id'] ?>" class="btn btn-primary">Смотреть</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Рекомендуемые товары -->
    <?php if (!empty($featured_products)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <h2>Рекомендуемые товары</h2>
                <a href="catalog.php" class="btn btn-outline-primary">Посмотреть все</a>
            </div>
            
            <div class="row">
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 shadow-sm product-card">
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-fluid" style="max-height: 100%; max-width: 100%;">
                                <?php else: ?>
                                    <i class="fas fa-image text-muted" style="font-size: 3rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                                <p class="card-text text-muted small"><?= htmlspecialchars(substr($product['description'] ?? '', 0, 100)) ?>...</p>
                                <?php if ($product['store_name']): ?>
                                    <p class="small text-primary"><i class="fas fa-store me-1"></i><?= htmlspecialchars($product['store_name']) ?></p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="text-primary mb-0"><?= number_format($product['price'], 0, ',', ' ') ?> ₽</h5>
                                    <?php if (isLoggedIn()): ?>
                                        <button class="btn btn-primary btn-sm add-to-cart" data-product-id="<?= $product['id'] ?>">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php" class="btn btn-outline-primary btn-sm">Войти</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Преимущества -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-shipping-fast text-primary mb-3" style="font-size: 3rem;"></i>
                    <h4>Быстрая доставка</h4>
                    <p class="text-muted">Доставим ваш заказ в кратчайшие сроки</p>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-shield-alt text-primary mb-3" style="font-size: 3rem;"></i>
                    <h4>Гарантия качества</h4>
                    <p class="text-muted">Только качественные товары от проверенных магазинов</p>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-headset text-primary mb-3" style="font-size: 3rem;"></i>
                    <h4>Поддержка 24/7</h4>
                    <p class="text-muted">Всегда готовы помочь с любыми вопросами</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Футер -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>ClothingStore</h5>
                    <p class="text-muted">Интернет-магазин одежды</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted">© 2024 ClothingStore. Все права защищены.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>