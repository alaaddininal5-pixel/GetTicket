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

// FIRMA EKLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {
    $name = trim($_POST['name']);
    $logo_path = trim($_POST['logo_path']);
    
    try {
        $stmt = $db->prepare("INSERT INTO bus_companies (name, logo_path) VALUES (?, ?)");
        $stmt->execute([$name, $logo_path]);
        $message = '<div class="alert alert-success">Firma başarıyla eklendi!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Firma eklenirken hata: ' . $e->getMessage() . '</div>';
    }
}

// FIRMA SİLME
if (isset($_GET['delete'])) {
    $company_id = $_GET['delete'];
    
    try {
        // Önce bu firmaya ait kullanıcıları kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $user_count = $stmt->fetchColumn();
        
        if ($user_count > 0) {
            $message = '<div class="alert alert-warning">Bu firmaya ait kullanıcılar olduğu için silinemez!</div>';
        } else {
            $stmt = $db->prepare("DELETE FROM bus_companies WHERE id = ?");
            $stmt->execute([$company_id]);
            $message = '<div class="alert alert-success">Firma başarıyla silindi!</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Firma silinirken hata: ' . $e->getMessage() . '</div>';
    }
}

// FIRMA DÜZENLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_company'])) {
    $company_id = $_POST['company_id'];
    $name = trim($_POST['name']);
    $logo_path = trim($_POST['logo_path']);
    
    try {
        $stmt = $db->prepare("UPDATE bus_companies SET name = ?, logo_path = ? WHERE id = ?");
        $stmt->execute([$name, $logo_path, $company_id]);
        $message = '<div class="alert alert-success">Firma başarıyla güncellendi!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Firma güncellenirken hata: ' . $e->getMessage() . '</div>';
    }
}

// Firmaları getir
$stmt = $db->query("SELECT * FROM bus_companies ORDER BY created_at DESC");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .table-actions { white-space: nowrap; }
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
        <h2>🏢 Firma Yönetimi</h2>
        
        <?php echo $message; ?>

        <div class="row">
            <!-- FIRMA EKLEME FORMU -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Yeni Firma Ekle</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Firma Adı</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Logo Yolu (opsiyonel)</label>
                                <input type="text" name="logo_path" class="form-control" placeholder="/logos/firma.png">
                            </div>
                            <button type="submit" name="add_company" class="btn btn-success w-100">Firma Ekle</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- FIRMA LİSTESİ -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Firma Listesi</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($companies)): ?>
                            <p class="text-muted">Henüz firma bulunmuyor.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Firma Adı</th>
                                            <th>Logo</th>
                                            <th>Oluşturulma</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($companies as $company): ?>
                                        <tr>
                                            <td><?php echo $company['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($company['name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($company['logo_path'])): ?>
                                                    <small><?php echo htmlspecialchars($company['logo_path']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Yok</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($company['created_at'])); ?></td>
                                            <td class="table-actions">
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $company['id']; ?>">
                                                    Düzenle
                                                </button>
                                                <a href="?delete=<?php echo $company['id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Bu firmayı silmek istediğinizden emin misiniz?')">
                                                    Sil
                                                </a>
                                            </td>
                                        </tr>

                                        <!-- DÜZENLEME MODAL -->
                                        <div class="modal fade" id="editModal<?php echo $company['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Firma Düzenle</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Firma Adı</label>
                                                                <input type="text" name="name" class="form-control" 
                                                                       value="<?php echo htmlspecialchars($company['name']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Logo Yolu</label>
                                                                <input type="text" name="logo_path" class="form-control" 
                                                                       value="<?php echo htmlspecialchars($company['logo_path']); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                            <button type="submit" name="edit_company" class="btn btn-primary">Kaydet</button>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>