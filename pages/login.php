<?php
session_start();
require_once '../config/connectdb.php';

// Eğer zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $errors = [];
    
    // Validasyon
    if (empty($email)) $errors[] = "Email gereklidir.";
    if (empty($password)) $errors[] = "Şifre gereklidir.";
    
    if (empty($errors)) {
        try {
            $db = getDBConnection();
            
            // Kullanıcıyı veritabanında ara
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // ✅ HASH'Lİ ŞİFRE KONTROLÜ
                if (password_verify($password, $user['password'])) {
                    
                    // Session başlat
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['balance'] = $user['balance'];
                    
                    // Başarılı giriş
                    header("Location: dashboard.php");
                    exit();
                    
                } else {
                    $errors[] = "Email veya şifre hatalı!";
                }
            } else {
                $errors[] = "Bu email ile kayıtlı kullanıcı bulunamadı!";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Giriş sırasında hata: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Bilet Platformu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffffff; }
        .login-container { max-width: 400px; margin: 100px auto; padding: 30px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">TicketPlatform</a>
        </div>
    </nav>

    <div class="container">
        <div class="login-container bg-white rounded shadow">
            <h2 class="text-center mb-4">Giriş Yap</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                
                <div class="text-center mt-3">
                    <a href="register.php">Hesabın yok mu? Kayıt Ol</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>