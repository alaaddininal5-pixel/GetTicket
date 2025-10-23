<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = getDBConnection();
$message = '';
$error = '';

// Kullanıcı bilgilerini getir
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// PROFİL BİLGİLERİ GÜNCELLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    $errors = [];
    
    if (empty($full_name)) $errors[] = "Ad Soyad gereklidir.";
    if (empty($email)) $errors[] = "Email gereklidir.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçerli bir email adresi girin.";
    
    // Email benzersiz mi kontrol et
    if (empty($errors) && $email !== $user['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = "Bu email adresi başka bir kullanıcı tarafından kullanılıyor.";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $_SESSION['user_id']]);
            
            // Session güncelle
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            // Kullanıcı bilgilerini yenile
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $message = '<div class="alert alert-success">✅ Profil bilgileri başarıyla güncellendi!</div>';
        } catch (PDOException $e) {
            $error = '<div class="alert alert-danger">Güncelleme sırasında hata: ' . $e->getMessage() . '</div>';
        }
    } else {
        $error = '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $err) {
            $error .= '<li>' . $err . '</li>';
        }
        $error .= '</ul></div>';
    }
}

// ŞİFRE DEĞİŞTİRME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($current_password)) $errors[] = "Mevcut şifrenizi girin.";
    if (empty($new_password)) $errors[] = "Yeni şifre gereklidir.";
    if (strlen($new_password) < 6) $errors[] = "Yeni şifre en az 6 karakter olmalıdır.";
    if ($new_password !== $confirm_password) $errors[] = "Yeni şifreler eşleşmiyor.";
    
    // Mevcut şifre doğru mu?
    if (empty($errors) && !password_verify($current_password, $user['password'])) {
        $errors[] = "Mevcut şifreniz yanlış!";
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $message = '<div class="alert alert-success">✅ Şifreniz başarıyla değiştirildi!</div>';
        } catch (PDOException $e) {
            $error = '<div class="alert alert-danger">Şifre değiştirilirken hata: ' . $e->getMessage() . '</div>';
        }
    } else {
        $error = '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $err) {
            $error .= '<li>' . $err . '</li>';
        }
        $error .= '</ul></div>';
    }
}

// İstatistikler
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_tickets,
        SUM(total_price) as total_spent
    FROM tickets 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Düzenle - GetTicket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .profile-role {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-row {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-box {
            flex: 1;
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .form-card h4 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            padding: 12px;
            border-radius: 8px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: 600;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .balance-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .balance-amount {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .account-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #666;
            font-weight: 600;
        }
        
        .info-value {
            color: #333;
            font-weight: bold;
        }
        
        .required-star {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <span style="font-size: 1.5rem;">🎫 GetTicket</span>
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                </span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="biletlerim.php">Biletlerim</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="profile-container">
            <?php echo $message; ?>
            <?php echo $error; ?>
            
            <!-- Profil Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="profile-avatar">👤</div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="profile-role">
                            <?php 
                            $role_names = [
                                'user' => '🎫 Yolcu',
                                'company_admin' => '🏢 Firma Yetkilisi',
                                'admin' => '⚙️ Sistem Yöneticisi'
                            ];
                            echo $role_names[$user['role']] ?? $user['role'];
                            ?>
                        </div>
                        
                        <div class="stats-row">
                            <div class="stat-box">
                                <div class="stat-value"><?php echo $stats['total_tickets']; ?></div>
                                <div class="stat-label">Toplam Bilet</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value"><?php echo $stats['active_tickets']; ?></div>
                                <div class="stat-label">Aktif Bilet</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value"><?php echo number_format($stats['total_spent'], 0); ?> ₺</div>
                                <div class="stat-label">Toplam Harcama</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="balance-card">
                            <div style="opacity: 0.9; margin-bottom: 10px;">💰 Bakiyem</div>
                            <div class="balance-amount"><?php echo number_format($user['balance'], 2); ?> ₺</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Profil Bilgileri -->
                <div class="col-lg-6">
                    <div class="form-card">
                        <h4>👤 Profil Bilgileri</h4>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Ad Soyad <span class="required-star">*</span></label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email <span class="required-star">*</span></label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary btn-save w-100">
                                💾 Kaydet
                            </button>
                        </form>
                    </div>
                    
                    <!-- Hesap Bilgileri -->
                    <div class="form-card">
                        <h4>📋 Hesap Bilgileri</h4>
                        
                        <div class="account-info">
                            <div class="info-row">
                                <span class="info-label">Kullanıcı ID:</span>
                                <span class="info-value">#<?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Rol:</span>
                                <span class="info-value">
                                    <?php 
                                    $role_names = [
                                        'user' => 'Yolcu',
                                        'company_admin' => 'Firma Yetkilisi',
                                        'admin' => 'Sistem Yöneticisi'
                                    ];
                                    echo $role_names[$user['role']] ?? $user['role'];
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Üyelik Tarihi:</span>
                                <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Bakiye:</span>
                                <span class="info-value"><?php echo number_format($user['balance'], 2); ?> TL</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Şifre Değiştirme -->
                <div class="col-lg-6">
                    <div class="form-card">
                        <h4>🔒 Şifre Değiştir</h4>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Mevcut Şifre <span class="required-star">*</span></label>
                                <input type="password" name="current_password" class="form-control" 
                                       placeholder="Mevcut şifrenizi girin" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Yeni Şifre <span class="required-star">*</span></label>
                                <input type="password" name="new_password" class="form-control" 
                                       placeholder="En az 6 karakter" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Yeni Şifre Tekrar <span class="required-star">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Yeni şifrenizi tekrar girin" required>
                            </div>
                            
                            <div class="alert alert-info">
                                <small>
                                    <strong>💡 İpucu:</strong> Güçlü bir şifre için büyük-küçük harf, rakam ve özel karakter kullanın.
                                </small>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-warning w-100">
                                🔑 Şifreyi Değiştir
                            </button>
                        </form>
                    </div>
                    
                    <!-- Hızlı Linkler -->
                    <div class="form-card">
                        <h4>🔗 Hızlı Linkler</h4>
                        
                        <div class="d-grid gap-2">
                            <a href="biletlerim.php" class="btn btn-outline-primary">
                                🎫 Biletlerim
                            </a>
                            <a href="sefer_ara.php" class="btn btn-outline-primary">
                                🔍 Sefer Ara
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                🏠 Dashboard'a Dön
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>