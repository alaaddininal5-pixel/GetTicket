<?php
session_start();
require_once '../config/connectdb.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validasyon
    if (empty($full_name)) $errors[] = "Ad soyad gereklidir.";
    if (empty($email)) $errors[] = "Email gereklidir.";
    if (empty($password)) $errors[] = "Şifre gereklidir.";
    if ($password !== $confirm_password) $errors[] = "Şifreler eşleşmiyor.";
    if (strlen($password) < 6) $errors[] = "Şifre en az 6 karakter olmalıdır.";
    
    // Email kontrolü
    if (empty($errors)) {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Bu email zaten kayıtlı.";
        }
    }
    
    // Kayıt işlemi
    if (empty($errors)) {
        try {
            $db = getDBConnection();
            
            // Şifreyi hash'le
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (full_name, email, password, role, balance) VALUES (?, ?, ?, 'user', 800.00)");
            $stmt->execute([$full_name, $email, $hashed_password]);
            
            $success = "Kayıt başarılı! Giriş yapabilirsiniz.";
        } catch (PDOException $e) {
            $errors[] = "Kayıt sırasında hata: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Bilet Platformu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffffff;}
        .register-container { max-width: 500px; margin: 50px auto; padding: 30px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">TicketPlatform</a>
        </div>
    </nav>

    <div class="container">
        <div class="register-container bg-white rounded shadow">
            <h2 class="text-center mb-4">Kayıt Ol</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <a href="login.php" class="alert-link">Giriş yapmak için tıklayın</a>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Ad Soyad</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Şifre Tekrar</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Kayıt Ol</button>
                
                <div class="text-center mt-3">
                    <a href="login.php">Zaten hesabın var mı? Giriş Yap</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>