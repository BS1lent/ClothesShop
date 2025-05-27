<?php
require_once 'config.php';

// Получаем параметры фильтрации
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;

// Получаем категории для фильтра
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Строим запрос для товаров
$where_conditions = ["p.is_active = 1"];
$params = [];

if ($category_id) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($min_price !== null) {
    $where_conditions[] = "p.price >= ?";
    $params[] = $min_price;
}

if ($max_price !== null) {
    $where_conditions[] = "p.price <= ?";
    $params[] = $max_price;
}

// Определяем сортировку
$order_by = "p.created_at DESC";
switch ($sort) {
    case 'name_asc':
        $order_by = "p.name ASC";
        break;
    case 'name_desc':
        $order_by = "p.name DESC";
        break;
    case 'price_asc':
        $order_by = "p.price ASC";
        break;
    case 'price_desc':
        $order_by = "p.price DESC";
        break;
}

$where_clause = implode(" AND ", $where_conditions);

$sql = "SELECT p.*, c.name as category_name, u.store_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.store_user_id = u.id 
        WHERE $where_clause 
        ORDER BY $order_by";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Каталог товаров - ClothingStore</title>
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
                        <a class="nav-link" href="index.php">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="catalog.php">Каталог</a>
                    </li>
                </ul>
                
                <!-- Поиск -->
                <form class="d-flex me-3" method="GET">
                    <div class="search-box">
                        <input class="form-control" type="search" name="search" placeholder="Поиск товаров..." value="<?= htmlspecialchars($search) ?>">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                    <button class="btn btn-outline-primary ms-2" type="submit">Найти</button>
                    <!-- Сохраняем другие фильтры -->
                    <?php if ($category_id): ?>
                        <input type="hidden" name="category" value="<?= $category_id ?>">
                    <?php endif; ?>
                    <?php if ($sort !== 'name_asc'): ?>
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                    <?php endif; ?>
                    <?php if ($min_price !== null): ?>
                        <input type="hidden" name="min_price" value="<?= $min_price ?>">
                    <?php endif; ?>
                    <?php if ($max_price !== null): ?>
                        <input type="hidden" name="max_price" value="<?= $max_price ?>">
                    <?php endif; ?>
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

    <div class="container py-4">
        <!-- Хлебные крошки -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
                <li class="breadcrumb-item active">Каталог</li>
                <?php if ($category_id): ?>
                    <?php
                    $category_name = '';
                    foreach ($categories as $cat) {
                        if ($cat['id'] == $category_id) {
                            $category_name = $cat['name'];
                            break;
                        }
                    }
                    ?>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($category_name) ?></li>
                <?php endif; ?>
            </ol>
        </nav>

        <div class="row">
            <!-- Боковая панель с фильтрами -->
            <div class="col-lg-3 mb-4">
                <div class="filter-section">
                    <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Фильтры</h5>
                    
                    <form method="GET" id="filterForm">
                        <!-- Сохраняем поиск -->
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        
                        <!-- Категории -->
                        <div class="mb-4">
                            <h6>Категории</h6>
                            <div class="list-group">
                                <a href="?<?= http_build_query(array_filter(['search' => $search, 'sort' => $sort, 'min_price' => $min_price, 'max_price' => $max_price])) ?>" 
                                   class="list-group-item list-group-item-action <?= !$category_id ? 'active' : '' ?>">
                                    Все категории
                                </a>
                                <?php foreach ($categories as $category): ?>
                                    <a href="?<?= http_build_query(array_filter(['category' => $category['id'], 'search' => $search, 'sort' => $sort, 'min_price' => $min_price, 'max_price' => $max_price])) ?>" 
                                       class="list-group-item list-group-item-action <?= $category_id == $category['id'] ? 'active' : '' ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Цена -->
                        <div class="mb-4">
                            <h6>Цена (₽)</h6>
                            <div class="row">
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" name="min_price" 
                                           placeholder="От" value="<?= $min_price ?>" min="0">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" name="max_price" 
                                           placeholder="До" value="<?= $max_price ?>" min="0">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm mt-2 w-100">Применить</button>
                        </div>
                        
                        <!-- Сортировка -->
                        <div class="mb-4">
                            <h6>Сортировка</h6>
                            <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>По алфавиту (А-Я)</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>По алфавиту (Я-А)</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Сначала дешевые</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Сначала дорогие</option>
                            </select>
                        </div>
                        
                        <!-- Сохраняем остальные параметры -->
                        <?php if ($category_id): ?>
                            <input type="hidden" name="category" value="<?= $category_id ?>">
                        <?php endif; ?>
                    </form>
                    
                    <?php if ($category_id || $search || $min_price || $max_price): ?>
                        <div class="mt-3">
                            <a href="catalog.php" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-times me-2"></i>Сбросить фильтры
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Основное содержимое -->
            <div class="col-lg-9">
                <!-- Результаты поиска -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4>
                            <?php if ($search): ?>
                                Результаты поиска "<?= htmlspecialchars($search) ?>"
                            <?php elseif ($category_id): ?>
                                <?php
                                foreach ($categories as $cat) {
                                    if ($cat['id'] == $category_id) {
                                        echo htmlspecialchars($cat['name']);
                                        break;
                                    }
                                }
                                ?>
                            <?php else: ?>
                                Все товары
                            <?php endif; ?>
                        </h4>
                        <p class="text-muted mb-0">Найдено товаров: <?= count($products) ?></p>
                    </div>
                </div>
                
                <!-- Товары -->
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h5>Товары не найдены</h5>
                        <p>Попробуйте изменить параметры поиска или фильтры</p>
                        <a href="catalog.php" class="btn btn-primary">Посмотреть все товары</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-xl-4 mb-4">
                                <div class="card h-100 shadow-sm product-card">
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 250px;">
                                        <?php if ($product['image_url']): ?>
                                            <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                                 class="img-fluid product-image" 
                                                 style="max-height: 100%; max-width: 100%;">
                                        <?php else: ?>
                                            <i class="fas fa-image text-muted" style="font-size: 4rem;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                                        
                                        <?php if ($product['description']): ?>
                                            <p class="card-text text-muted small mb-2">
                                                <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>
                                                <?= strlen($product['description']) > 100 ? '...' : '' ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['category_name']): ?>
                                            <span class="badge bg-light text-dark mb-2">
                                                <?= htmlspecialchars($product['category_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['store_name']): ?>
                                            <p class="small text-primary mb-2">
                                                <i class="fas fa-store me-1"></i><?= htmlspecialchars($product['store_name']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-auto">
                                            <h5 class="text-primary mb-0"><?= number_format($product['price'], 0, ',', ' ') ?> ₽</h5>
                                            
                                            <?php if (isLoggedIn()): ?>
                                                <button class="btn btn-primary btn-sm add-to-cart" data-product-id="<?= $product['id'] ?>">
                                                    <i class="fas fa-cart-plus me-1"></i>В корзину
                                                </button>
                                            <?php else: ?>
                                                <a href="login.php" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-sign-in-alt me-1"></i>Войти
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Футер -->
    <footer class="bg-dark text-white py-4 mt-5">
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