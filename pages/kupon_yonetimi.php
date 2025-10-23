<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Sadece admin ve company_admin erişebilir
if (!isset($_SESSION['user_id']) || 
    ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'company_admin')) {
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

// Firma bilgisini getir (company_admin ise)
$user_company = null;
if ($_SESSION['role'] === 'company_admin' && !empty($user['company_id'])) {
    $stmt = $db->prepare("SELECT * FROM bus_companies WHERE id = ?");
    $stmt->execute([$user['company_id']]);
    $user_company = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Admin ise tüm firmaları getir
$companies = [];
if ($_SESSION['role'] === 'admin') {
    $stmt = $db->query("SELECT * FROM bus_companies ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// KUPON EKLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount = (float)$_POST['discount'];
    $usage_limit = (int)$_POST['usage_limit'];
    $expire_date = trim($_POST['expire_date']);
    
    // Firma ID belirleme
    $company_id = null;
    if ($_SESSION['role'] === 'company_admin') {
        // Firma admin sadece kendi firması için kupon oluşturabilir
        $company_id = $user['company_id'];
    } elseif ($_SESSION['role'] === 'admin' && !empty($_POST['company_id'])) {
        // Admin firma seçebilir veya genel kupon yapabilir
        $company_id = $_POST['company_id'] === 'general' ? null : (int)$_POST['company_id'];
    }
    
    $errors = [];
    
    if (empty($code)) $errors[] = "Kupon kodu gereklidir.";
    if (strlen($code) < 3) $errors[] = "Kupon kodu en az 3 karakter olmalıdır.";
    if ($discount <= 0 || $discount > 100) $errors[] = "İndirim oranı 1-100 arası olmalıdır.";
    if ($usage_limit <= 0) $errors[] = "Kullanım limiti 0'dan büyük olmalıdır.";
    if (empty($expire_date)) $errors[] = "Son kullanma tarihi gereklidir.";
    
    // Kupon kodunun benzersiz olup olmadığını kontrol et
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM coupons WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            $errors[] = "Bu kupon kodu zaten kullanılıyor!";
        }
    }
    
    if (empty($errors)) {
        try {
            // Coupons tablosuna company_id sütunu eklenmiş olmalı
            // Eğer yoksa ALTER TABLE ile ekleyin:
            // ALTER TABLE coupons ADD COLUMN company_id INTEGER;
            
            $stmt = $db->prepare("
                INSERT INTO coupons (code, discount, usage_limit, expire_date, company_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, $discount, $usage_limit, $expire_date, $company_id]);
            $message = '<div class="alert alert-success">✅ Kupon başarıyla eklendi!</div>';
        } catch (PDOException $e) {
            // Eğer company_id sütunu yoksa hata verecek
            if (strpos($e->getMessage(), 'company_id') !== false) {
                $error = '<div class="alert alert-danger">Veritabanı hatası! Lütfen coupons tablosuna company_id sütunu ekleyin: <br><code>ALTER TABLE coupons ADD COLUMN company_id INTEGER;</code></div>';
            } else {
                $error = '<div class="alert alert-danger">Kupon eklenirken hata: ' . $e->getMessage() . '</div>';
            }
        }
    } else {
        $error = '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $err) {
            $error .= '<li>' . $err . '</li>';
        }
        $error .= '</ul></div>';
    }
}

// KUPON SİLME
if (isset($_GET['delete'])) {
    $coupon_id = (int)$_GET['delete'];
    
    try {
        // Kupon sahibi kontrolü
        $stmt = $db->prepare("SELECT company_id FROM coupons WHERE id = ?");
        $stmt->execute([$coupon_id]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $can_delete = false;
        if ($_SESSION['role'] === 'admin') {
            $can_delete = true; // Admin her şeyi silebilir
        } elseif ($_SESSION['role'] === 'company_admin') {
            // Firma admin sadece kendi kuponlarını silebilir
            if ($coupon && $coupon['company_id'] == $user['company_id']) {
                $can_delete = true;
            }
        }
        
        if (!$can_delete) {
            $error = '<div class="alert alert-danger">Bu kuponu silme yetkiniz yok!</div>';
        } else {
            // Kullanılmış mı kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) FROM user_coupons WHERE coupon_id = ?");
            $stmt->execute([$coupon_id]);
            $usage_count = $stmt->fetchColumn();
            
            if ($usage_count > 0) {
                $error = '<div class="alert alert-warning">Bu kupon ' . $usage_count . ' kez kullanılmış, silinemez!</div>';
            } else {
                $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
                $stmt->execute([$coupon_id]);
                $message = '<div class="alert alert-success">✅ Kupon başarıyla silindi!</div>';
            }
        }
    } catch (PDOException $e) {
        $error = '<div class="alert alert-danger">Kupon silinirken hata: ' . $e->getMessage() . '</div>';
    }
}

// KUPON DÜZENLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_coupon'])) {
    $coupon_id = (int)$_POST['coupon_id'];
    $code = strtoupper(trim($_POST['code']));
    $discount = (float)$_POST['discount'];
    $usage_limit = (int)$_POST['usage_limit'];
    $expire_date = trim($_POST['expire_date']);
    
    // Kupon sahibi kontrolü
    $stmt = $db->prepare("SELECT company_id FROM coupons WHERE id = ?");
    $stmt->execute([$coupon_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $can_edit = false;
    if ($_SESSION['role'] === 'admin') {
        $can_edit = true;
    } elseif ($_SESSION['role'] === 'company_admin') {
        if ($coupon && $coupon['company_id'] == $user['company_id']) {
            $can_edit = true;
        }
    }
    
    if (!$can_edit) {
        $error = '<div class="alert alert-danger">Bu kuponu düzenleme yetkiniz yok!</div>';
    } else {
        $errors = [];
        
        if (empty($code)) $errors[] = "Kupon kodu gereklidir.";
        if ($discount <= 0 || $discount > 100) $errors[] = "İndirim oranı 1-100 arası olmalıdır.";
        if ($usage_limit <= 0) $errors[] = "Kullanım limiti 0'dan büyük olmalıdır.";
        if (empty($expire_date)) $errors[] = "Son kullanma tarihi gereklidir.";
        
        // Başka bir kuponun aynı koda sahip olup olmadığını kontrol et
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
            $stmt->execute([$code, $coupon_id]);
            if ($stmt->fetch()) {
                $errors[] = "Bu kupon kodu başka bir kupon tarafından kullanılıyor!";
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE coupons 
                    SET code = ?, discount = ?, usage_limit = ?, expire_date = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$code, $discount, $usage_limit, $expire_date, $coupon_id]);
                $message = '<div class="alert alert-success">✅ Kupon başarıyla güncellendi!</div>';
            } catch (PDOException $e) {
                $error = '<div class="alert alert-danger">Kupon güncellenirken hata: ' . $e->getMessage() . '</div>';
            }
        } else {
            $error = '<div class="alert alert-danger"><ul class="mb-0">';
            foreach ($errors as $err) {
                $error .= '<li>' . $err . '</li>';
            }
            $error .= '</ul></div>';
        }
    }
}

// Kuponları getir
if ($_SESSION['role'] === 'admin') {
    // Admin tüm kuponları görebilir
    $stmt = $db->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM user_coupons uc WHERE uc.coupon_id = c.id) as used_count,
               bc.name as company_name
        FROM coupons c 
        LEFT JOIN bus_companies bc ON c.company_id = bc.id
        ORDER BY c.created_at DESC
    ");
} else {
    // Firma admin sadece kendi kuponlarını görebilir
    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM user_coupons uc WHERE uc.coupon_id = c.id) as used_count,
               bc.name as company_name
        FROM coupons c 
        LEFT JOIN bus_companies bc ON c.company_id = bc.id
        WHERE c.company_id = ? OR c.company_id IS NULL
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user['company_id']]);
}
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$total_coupons = count($coupons);
$active_coupons = 0;
$expired_coupons = 0;
$current_date = date('Y-m-d H:i:s');

foreach ($coupons as $coupon) {
    if ($coupon['expire_date'] < $current_date) {
        $expired_coupons++;
    } else {
        $active_coupons++;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Yönetimi - <?php echo $_SESSION['role'] === 'admin' ? 'Admin' : 'Firma'; ?> Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .card-custom {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-add {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.4);
        }
        
        .coupon-card {
            background: white;
            border-left: 5px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .coupon-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .coupon-card.expired {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        
        .coupon-card.company-specific {
            border-left-color: #ffc107;
        }
        
        .coupon-code {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
        }
        
        .coupon-code.expired {
            color: #dc3545;
        }
        
        .discount-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .usage-badge {
            background: #17a2b8;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .expired-badge {
            background: #dc3545;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .company-badge {
            background: #ffc107;
            color: #333;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .general-badge {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .stat-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
                    <?php echo $_SESSION['role'] === 'admin' ? 'Admin' : 'Firma'; ?>: 
                    <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                </span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>🎟️ Kupon Yönetimi</h2>
        
        <?php if ($_SESSION['role'] === 'company_admin' && $user_company): ?>
            <div class="info-box">
                <strong>🏢 Firma:</strong> <?php echo htmlspecialchars($user_company['name']); ?><br>
                <small>Sadece firmanıza özel kuponlar oluşturabilirsiniz. Genel kuponları görebilir ancak düzenleyemezsiniz.</small>
            </div>
        <?php endif; ?>
        
        <?php echo $message; ?>
        <?php echo $error; ?>

        <!-- İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_coupons; ?></div>
                    <div class="stat-label">Toplam Kupon</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number" style="color: #28a745;"><?php echo $active_coupons; ?></div>
                    <div class="stat-label">Aktif Kupon</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number" style="color: #dc3545;"><?php echo $expired_coupons; ?></div>
                    <div class="stat-label">Süresi Dolmuş</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Kupon Ekleme Formu -->
            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5 class="mb-0">➕ Yeni Kupon Ekle</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <div class="mb-3">
                                <label class="form-label">Kupon Tipi <span class="text-danger">*</span></label>
                                <select name="company_id" class="form-select" required>
                                    <option value="general">🌍 Genel Kupon (Tüm firmalar)</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>">
                                            🏢 <?php echo htmlspecialchars($company['name']); ?> (Özel)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Genel kuponlar tüm firmalarda kullanılabilir</small>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    ℹ️ Bu kupon sadece <strong><?php echo htmlspecialchars($user_company['name']); ?></strong> 
                                    firmamızın seferlerinde kullanılabilir.
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Kupon Kodu <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" 
                                       placeholder="YILBASI2025" 
                                       style="text-transform: uppercase;" required>
                                <small class="text-muted">Örn: HOSGELDIN, INDIRIM50</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">İndirim Oranı (%) <span class="text-danger">*</span></label>
                                <input type="number" name="discount" class="form-control" 
                                       min="1" max="100" step="0.01" placeholder="15" required>
                                <small class="text-muted">1-100 arası değer giriniz</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Kullanım Limiti <span class="text-danger">*</span></label>
                                <input type="number" name="usage_limit" class="form-control" 
                                       min="1" placeholder="100" required>
                                <small class="text-muted">Kaç kişi kullanabilir?</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Son Kullanma Tarihi <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="expire_date" class="form-control" required>
                            </div>
                            
                            <button type="submit" name="add_coupon" class="btn btn-primary btn-add w-100">
                                ✅ Kupon Ekle
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Kupon Listesi -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5 class="mb-0">📋 Kupon Listesi</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($coupons)): ?>
                            <div class="text-center text-muted py-5">
                                <div style="font-size: 4rem;">🎟️</div>
                                <p>Henüz kupon eklenmemiş</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon): 
                                $is_expired = $coupon['expire_date'] < $current_date;
                                $remaining_usage = $coupon['usage_limit'] - $coupon['used_count'];
                                $is_company_specific = !empty($coupon['company_id']);
                                $can_edit = ($_SESSION['role'] === 'admin') || 
                                           ($_SESSION['role'] === 'company_admin' && $coupon['company_id'] == $user['company_id']);
                            ?>
                            <div class="coupon-card <?php echo $is_expired ? 'expired' : ''; ?> <?php echo $is_company_specific ? 'company-specific' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="coupon-code <?php echo $is_expired ? 'expired' : ''; ?>">
                                            <?php echo htmlspecialchars($coupon['code']); ?>
                                        </div>
                                        <div class="mt-2">
                                            <?php if ($is_company_specific): ?>
                                                <span class="company-badge">
                                                    🏢 <?php echo htmlspecialchars($coupon['company_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="general-badge">
                                                    🌍 Genel Kupon
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="discount-badge">
                                            %<?php echo number_format($coupon['discount'], 0); ?> İNDİRİM
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <small class="text-muted">Kullanım</small>
                                        <div>
                                            <span class="usage-badge">
                                                <?php echo $coupon['used_count']; ?> / <?php echo $coupon['usage_limit']; ?>
                                            </span>
                                            <?php if ($remaining_usage > 0): ?>
                                                <small class="text-success ms-2">(<?php echo $remaining_usage; ?> kalan)</small>
                                            <?php else: ?>
                                                <small class="text-danger ms-2">(Limit doldu)</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">Son Kullanma</small>
                                        <div>
                                            <?php if ($is_expired): ?>
                                                <span class="expired-badge">Süresi Doldu</span>
                                            <?php else: ?>
                                                <strong><?php echo date('d.m.Y H:i', strtotime($coupon['expire_date'])); ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Oluşturulma: <?php echo date('d.m.Y H:i', strtotime($coupon['created_at'])); ?>
                                    </small>
                                    <div class="table-actions">
                                        <?php if ($can_edit): ?>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $coupon['id']; ?>">
                                                Düzenle
                                            </button>
                                            <?php if ($coupon['used_count'] == 0): ?>
                                                <a href="?delete=<?php echo $coupon['id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Bu kuponu silmek istediğinizden emin misiniz?')">
                                                    Sil
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Kullanılmış kupon silinemez">
                                                    Sil
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sadece Görüntüleme</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Düzenleme Modal -->
                            <?php if ($can_edit): ?>
                            <div class="modal fade" id="editModal<?php echo $coupon['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Kupon Düzenle</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Kupon Kodu</label>
                                                    <input type="text" name="code" class="form-control" 
                                                           value="<?php echo htmlspecialchars($coupon['code']); ?>" 
                                                           style="text-transform: uppercase;" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">İndirim Oranı (%)</label>
                                                    <input type="number" name="discount" class="form-control" 
                                                           value="<?php echo $coupon['discount']; ?>" 
                                                           min="1" max="100" step="0.01" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Kullanım Limiti</label>
                                                    <input type="number" name="usage_limit" class="form-control" 
                                                           value="<?php echo $coupon['usage_limit']; ?>" 
                                                           min="<?php echo $coupon['used_count']; ?>" required>
                                                    <small class="text-muted">
                                                        En az <?php echo $coupon['used_count']; ?> olmalı (şu anki kullanım)
                                                    </small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Son Kullanma Tarihi</label>
                                                    <input type="datetime-local" name="expire_date" class="form-control" 
                                                           value="<?php echo date('Y-m-d\TH:i', strtotime($coupon['expire_date'])); ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                <button type="submit" name="edit_coupon" class="btn btn-primary">Kaydet</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>