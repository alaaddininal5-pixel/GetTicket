<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Sadece admin erişebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$db = getDBConnection();
$message = '';

// FIRMA YÖNETİCİSİ ATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_company_admin'])) {
    $user_id = $_POST['user_id'];
    $company_id = $_POST['company_id'];
    
    try {
        // Kullanıcıyı company_admin yap ve firma ata
        $stmt = $db->prepare("UPDATE users SET role = 'company_admin', company_id = ? WHERE id = ?");
        $stmt->execute([$company_id, $user_id]);
        $message = '<div class="alert alert-success">Kullanıcı firma yöneticisi olarak atandı!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Atama sırasında hata: ' . $e->getMessage() . '</div>';
    }
}

// FIRMA YÖNETİCİLİĞİNİ KALDIR
if (isset($_GET['remove_company_admin'])) {
    $user_id = $_GET['remove_company_admin'];
    
    try {
        // Kullanıcıyı user yap ve firma bilgisini temizle
        $stmt = $db->prepare("UPDATE users SET role = 'user', company_id = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = '<div class="alert alert-success">Firma yöneticiliği kaldırıldı!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">İşlem sırasında hata: ' . $e->getMessage() . '</div>';
    }
}

// KULLANICI DÜZENLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $balance = $_POST['balance'];

    try {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, balance = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $balance, $user_id]);
        $message = '<div class="alert alert-success">Kullanıcı başarıyla güncellendi!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Kullanıcı güncellenirken hata: ' . $e->getMessage() . '</div>';
    }
}

// KULLANICI SİLME
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    if ($user_id == $_SESSION['user_id']) {
        $message = '<div class="alert alert-danger">Kendi hesabınızı silemezsiniz!</div>';
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = '<div class="alert alert-success">Kullanıcı başarıyla silindi!</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Kullanıcı silinirken hata: ' . $e->getMessage() . '</div>';
        }
    }
}

// ROL DEĞİŞTİRME (Sadece admin/user arasında)
if (isset($_GET['change_role'])) {
    $user_id = $_GET['change_role'];
    $new_role = $_GET['role'];
    
    if ($user_id == $_SESSION['user_id']) {
        $message = '<div class="alert alert-danger">Kendi rolünüzü değiştiremezsiniz!</div>';
    } else {
        try {
            // Company admin rolünü buradan değiştiremez, özel butonla yapılacak
            if ($new_role !== 'company_admin') {
                $stmt = $db->prepare("UPDATE users SET role = ?, company_id = NULL WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                $message = '<div class="alert alert-success">Kullanıcı rolü başarıyla değiştirildi!</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Rol değiştirilirken hata: ' . $e->getMessage() . '</div>';
        }
    }
}

// Kullanıcıları getir
$stmt = $db->query("
    SELECT u.*, c.name as company_name 
    FROM users u 
    LEFT JOIN bus_companies c ON u.company_id = c.id 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Firmaları getir
$stmt = $db->query("SELECT id, name FROM bus_companies ORDER BY name");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Firma yöneticisi olmayan kullanıcılar (atama için)
$regular_users = array_filter($users, fn($u) => $u['role'] === 'user');
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .table-actions { white-space: nowrap; }
        .role-badge { font-size: 0.75em; }
        .company-admin-section { border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">🎫 GetTicket</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Admin: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>👥 Kullanıcı Yönetimi</h2>
        
        <?php echo $message; ?>

        <!-- FIRMA YÖNETİCİSİ ATAMA BÖLÜMÜ -->
        <div class="card company-admin-section">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">🏢 Firma Yöneticisi Atama</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Kullanıcı Seçin</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">Kullanıcı Seçin</option>
                            <?php foreach ($regular_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Firma Seçin</label>
                        <select name="company_id" class="form-control" required>
                            <option value="">Firma Seçin</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>">
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="assign_company_admin" class="btn btn-warning w-100">
                            Ata
                        </button>
                    </div>
                </form>
                
                <!-- MEVCUT FIRMA YÖNETİCİLERİ -->
                <?php 
                $company_admins = array_filter($users, fn($u) => $u['role'] === 'company_admin');
                if (!empty($company_admins)): ?>
                <div class="mt-4">
                    <h6>Mevcut Firma Yöneticileri:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Kullanıcı</th>
                                    <th>Firma</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($company_admins as $admin): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($admin['full_name']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($admin['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($admin['company_name']): ?>
                                            <?php echo htmlspecialchars($admin['company_name']); ?>
                                        <?php else: ?>
                                            <span class="text-danger">Firma atanmamış!</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?remove_company_admin=<?php echo $admin['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Firma yöneticiliğini kaldırmak istediğinizden emin misiniz?')">
                                            Kaldır
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TÜM KULLANICILAR LİSTESİ -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Tüm Kullanıcılar</h5>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <p class="text-muted">Henüz kullanıcı bulunmuyor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Ad Soyad</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Bakiye</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-info">Siz</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge role-badge 
                                            <?php echo $user['role'] === 'admin' ? 'bg-danger' : 
                                                  ($user['role'] === 'company_admin' ? 'bg-warning' : 'bg-primary'); ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                        
                                        <!-- Hızlı Rol Değiştirme (Sadece admin/user) -->
                                        <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] !== 'company_admin'): ?>
                                        <div class="btn-group btn-group-sm mt-1">
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                Değiştir
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="?change_role=<?php echo $user['id']; ?>&role=user">User</a></li>
                                                <li><a class="dropdown-item" href="?change_role=<?php echo $user['id']; ?>&role=admin">Admin</a></li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($user['balance'], 2); ?> TL</td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                    <td class="table-actions">
                                        <!-- Düzenle Butonu -->
                                        <button type="button" class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal<?php echo $user['id']; ?>">
                                            Düzenle
                                        </button>
                                        
                                        <!-- Sil Butonu -->
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">
                                            Sil
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>Sil</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- DÜZENLEME MODAL -->
                                <div class="modal fade" id="editModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Kullanıcı Düzenle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Ad Soyad</label>
                                                        <input type="text" name="full_name" class="form-control" 
                                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" name="email" class="form-control" 
                                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Bakiye (TL)</label>
                                                        <input type="number" step="0.01" name="balance" class="form-control" 
                                                               value="<?php echo $user['balance']; ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Rol</label>
                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
                                                        <small class="text-muted">Rol değişikliği için üstteki butonları kullanın</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                    <button type="submit" name="edit_user" class="btn btn-primary">Kaydet</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- İSTATİSTİKLER -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo count($users); ?></h3>
                        <p class="text-muted">Toplam Kullanıcı</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo count(array_filter($users, fn($u) => $u['role'] === 'user')); ?></h3>
                        <p class="text-muted">Normal Kullanıcı</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo count(array_filter($users, fn($u) => $u['role'] === 'company_admin')); ?></h3>
                        <p class="text-muted">Firma Admin</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></h3>
                        <p class="text-muted">Sistem Admin</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>