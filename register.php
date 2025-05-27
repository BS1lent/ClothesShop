<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'] ?? 'customer';
    $store_name = trim($_POST['store_name'] ?? '');
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Пожалуйста, заполните все обязательные поля';
    } elseif ($password !== $confirm_password) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен содержать минимум 6 символов';
    } elseif ($user_type === 'store' && empty($store_name)) {
        $error = 'Для магазина необходимо указать название';
    } else {
        // Проверяем, не существует ли уже пользователь с таким email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким email уже существует';
        } else {
            // Создаем нового пользователя
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_id = uniqid('user_', true);
            
            $stmt = $pdo->prepare("INSERT INTO users (id, email, first_name, last_name, password, user_type, store_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if ($stmt->execute([$user_id, $email, $first_name, $last_name, $hashed_password, $user_type, $store_name])) {
                $success = 'Регистрация успешна! Теперь вы можете войти в систему.';
            } else {
                $error = 'Ошибка при регистрации. Попробуйте снова.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - ClothingStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center min-vh-100 py-4">
        <div class="row w-100">
            <div class="col-md-8 col-lg-6 mx-auto">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary">
                                <i class="fas fa-tshirt me-2"></i>ClothingStore
                            </h2>
                            <p class="text-muted">Создайте новый аккаунт</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-success btn-sm">Войти в систему</a>
                                </div>
                            </div>
                        <?php else: ?>
                        
                        <form method="POST" id="registerForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Имя *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required 
                                           value="<?= htmlspecialchars($first_name ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Фамилия *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required 
                                           value="<?= htmlspecialchars($last_name ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?= htmlspecialchars($email ?? '') ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Пароль *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Минимум 6 символов</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Подтвердите пароль *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="user_type" class="form-label">Тип аккаунта</label>
                                <select class="form-select" id="user_type" name="user_type" onchange="toggleStoreFields()">
                                    <option value="customer" <?= ($user_type ?? 'customer') === 'customer' ? 'selected' : '' ?>>
                                        <i class="fas fa-user"></i> Покупатель
                                    </option>
                                    <option value="store" <?= ($user_type ?? '') === 'store' ? 'selected' : '' ?>>
                                        <i class="fas fa-store"></i> Магазин
                                    </option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="storeFields" style="display: <?= ($user_type ?? '') === 'store' ? 'block' : 'none' ?>;">
                                <label for="store_name" class="form-label">Название магазина *</label>
                                <input type="text" class="form-control" id="store_name" name="store_name" 
                                       value="<?= htmlspecialchars($store_name ?? '') ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>Зарегистрироваться
                            </button>
                        </form>
                        
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <p class="mb-0">Уже есть аккаунт? 
                                <a href="login.php" class="text-primary text-decoration-none">Войти</a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="text-muted text-decoration-none">
                        <i class="fas fa-arrow-left me-2"></i>Вернуться на главную
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleStoreFields() {
            const userType = document.getElementById('user_type').value;
            const storeFields = document.getElementById('storeFields');
            const storeNameInput = document.getElementById('store_name');
            
            if (userType === 'store') {
                storeFields.style.display = 'block';
                storeNameInput.required = true;
            } else {
                storeFields.style.display = 'none';
                storeNameInput.required = false;
                storeNameInput.value = '';
            }
        }
        
        // Проверка совпадения паролей
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Пароли не совпадают');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>