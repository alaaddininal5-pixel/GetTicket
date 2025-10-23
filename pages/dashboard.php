<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kullanıcı bilgilerini veritabanından al
$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Rol kontrol fonksiyonları
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCompanyAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'company_admin';
}

function isUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bilet Platformu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffffff; }
        .card { margin-bottom: 20px; }
        .badge { font-size: 0.8em; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">🎫 GetTicket</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <strong><?php echo htmlspecialchars($user['role']); ?></strong> - 
                    Hoş geldin, <?php echo htmlspecialchars($user['full_name']); ?>
                </span>
                <a class="nav-link" href="logout.php">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Dashboard</h2>
        
        <!-- KULLANICI BİLGİLERİ -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Profil Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Rol:</strong> 
                            <span class="badge 
                                <?php echo $user['role'] === 'admin' ? 'bg-danger' : 
                                      ($user['role'] === 'company_admin' ? 'bg-warning' : 'bg-primary'); ?>">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </p>
                        <p><strong>Bakiye:</strong> <?php echo number_format($user['balance'], 2); ?> TL</p>
                        <p><strong>Üyelik Tarihi:</strong> <?php echo date('d.m.Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- USER (YOLCU) PANELİ -->
            <?php if (isUser()): ?>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title">Yolcu İşlemleri</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="sefer_ara.php" class="btn btn-success w-100 py-3">
                                    <h6>🛒 Yeni Bilet Al</h6>
                                    <small>Sefer ara ve bilet satın al</small>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="biletlerim.php" class="btn btn-info w-100 py-3">
                                    <h6>🎫 Biletlerim</h6>
                                    <small>Geçmiş ve aktif biletler</small>
                                </a>
                            </div>
                            <div class="col-md-12 mb-3">
                                <a href="profil_duzenle.php" class="btn btn-outline-secondary w-100 py-3">
                                    <h6>👤 Profili Düzenle</h6>
                                    <small>Bilgilerimi güncelle</small>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">

                            </div>
                        </div>
                    </div>
                </div>

                <!-- SON BİLETLER (USER İÇİN) -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Son Biletlerim</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Henüz bilet almadınız.</p>
                        <!-- Buraya kullanıcının son biletleri gelecek -->
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- FIRMA ADMIN PANELİ -->
            <?php if (isCompanyAdmin()): ?>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title">Firma Yönetimi</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="sefer_ekle.php" class="btn btn-success w-100 py-3">
                                    <h6>🚌 Yeni Sefer Ekle</h6>
                                    <small>Yolculuk oluştur</small>
                                </a>
                            </div>
                               <div class="col-md-6 mb-3">
                                <a href="kupon_yonetimi.php" class="btn btn-success w-100 py-3">
                                    <h6>🎁 Kupon Yönetimi</h6>
                                    <small>İndirim kuponları</small>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="seferlerim.php" class="btn btn-info w-100 py-3">
                                    <h6>📋 Seferlerim</h6>
                                    <small>Seferleri yönet</small>
                                </a>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <a href="rezervasyonlar.php" class="btn btn-secondary w-100 py-3">
                                    <h6>🎟️ Rezervasyonlar</h6>
                                    <small>Biletleri görüntüle</small>
                                </a>
                                
                            </div>
                        </div>
                    </div>
                </div>

            
                        <!-- Buraya firma bilgileri gelecek -->
                    </div>
                </div>
            </div>
            <?php endif; ?>

 <!-- ADMIN PANELİ -->
            <?php if (isAdmin()): ?>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title">Sistem Yönetimi</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <a href="firma_yonetimi.php" class="btn btn-warning w-100 py-3">
                                    <h6>🏢 Firma Yönetimi</h6>
                                    <small>Firma ekle/düzenle</small>
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="kullanici_yonetimi.php" class="btn btn-info w-100 py-3">
                                    <h6>👥 Kullanıcı Yönetimi</h6>
                                    <small>Kullanıcıları yönet</small>
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="kupon_yonetimi.php" class="btn btn-success w-100 py-3">
                                    <h6>🎁 Kupon Yönetimi</h6>
                                    <small>İndirim kuponları</small>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="sistem_istatistik.php" class="btn btn-primary w-100 py-3">
                                    <h6>📈 Sistem İstatistikleri</h6>
                                    <small>Genel raporlar</small>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="loglar.php" class="btn btn-secondary w-100 py-3">
                                    <h6>📋 Sistem Logları</h6>
                                    <small>İşlem geçmişi</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>