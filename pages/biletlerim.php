<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = getDBConnection();

// Filtre parametresi
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// İptal başarılı mesajı
$success_message = '';
if (isset($_GET['cancelled']) && $_GET['cancelled'] == 1) {
    $success_message = '<div class="alert alert-success alert-dismissible fade show">
        <strong>✅ Başarılı!</strong> Bilet iptal edildi ve para iadesi hesabınıza yapıldı.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

// Biletleri getir
$sql = "
    SELECT 
        t.*, 
        tr.departure_city, 
        tr.destination_city, 
        tr.departure_time, 
        tr.arrival_time,
        bc.name as company_name, 
        bc.logo_path
    FROM tickets t
    LEFT JOIN trips tr ON t.trip_id = tr.id
    LEFT JOIN bus_companies bc ON tr.company_id = bc.id
    WHERE t.user_id = ?
";

// Filtre uygula
switch ($filter) {
    case 'active':
        $sql .= " AND t.status = 'active'";
        break;
    case 'cancelled':
        $sql .= " AND t.status = 'cancelled'";
        break;
    case 'past':
        // Geçmiş biletler (kalkış saati geçmiş)
        $sql .= " AND t.status = 'active' AND tr.departure_time < '" . date('H:i:s') . "'";
        break;
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stats_query = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(total_price) as total_spent
    FROM tickets 
    WHERE user_id = ?
");
$stats_query->execute([$_SESSION['user_id']]);
$stats = $stats_query->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletlerim - GetTicket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .stat-item {
            text-align: center;
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
        
        .filter-buttons {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .filter-btn {
            padding: 10px 25px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 25px;
            margin: 5px;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        
        .ticket-item {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
            border-left: 5px solid #667eea;
        }
        
        .ticket-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .ticket-item.cancelled {
            border-left-color: #dc3545;
            opacity: 0.8;
        }
        
        .ticket-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .company-logo {
            height: 40px;
            object-fit: contain;
        }
        
        .company-name {
            font-weight: 600;
            color: #333;
        }
        
        .ticket-status {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
        }
        
        .ticket-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .ticket-status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .route-section {
            display: flex;
            align-items: center;
            gap: 30px;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .route-city {
            flex: 1;
        }
        
        .city-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .city-time {
            color: #666;
            font-size: 0.9rem;
        }
        
        .route-arrow {
            font-size: 2rem;
            color: #667eea;
        }
        
        .ticket-details {
            display: flex;
            gap: 30px;
            margin: 20px 0;
            padding-top: 20px;
            border-top: 2px dashed #dee2e6;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-label {
            color: #666;
            font-size: 0.85rem;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
        }
        
        .ticket-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-detail {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-detail:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-pdf {
            background: #28a745;
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-pdf:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .no-tickets {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .no-tickets-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .price-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.2rem;
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
                <a class="nav-link" href="sefer_ara.php">Sefer Ara</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">🎫 Biletlerim</h2>
        
        <?php echo $success_message; ?>
        
        <!-- İstatistikler -->
        <div class="stats-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Toplam Bilet</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #28a745;"><?php echo $stats['active']; ?></div>
                        <div class="stat-label">Aktif Bilet</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #dc3545;"><?php echo $stats['cancelled']; ?></div>
                        <div class="stat-label">İptal Edilmiş</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #ffc107;"><?php echo number_format($stats['total_spent'], 0); ?></div>
                        <div class="stat-label">Toplam Harcama (TL)</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtre Butonları -->
        <div class="filter-buttons">
            <div class="d-flex justify-content-center flex-wrap">
                <a href="?filter=all" class="btn filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    🎫 Tüm Biletler
                </a>
                <a href="?filter=active" class="btn filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">
                    ✅ Aktif Biletler
                </a>
                <a href="?filter=cancelled" class="btn filter-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                    ❌ İptal Edilmiş
                </a>
            </div>
        </div>
        
        <!-- Bilet Listesi -->
        <?php if (empty($tickets)): ?>
            <div class="no-tickets">
                <div class="no-tickets-icon">🎫</div>
                <h3>Bilet Bulunamadı</h3>
                <p class="text-muted">
                    <?php 
                    switch ($filter) {
                        case 'active':
                            echo 'Henüz aktif biletiniz bulunmuyor.';
                            break;
                        case 'cancelled':
                            echo 'İptal edilmiş biletiniz bulunmuyor.';
                            break;
                        case 'past':
                            echo 'Geçmiş biletiniz bulunmuyor.';
                            break;
                        default:
                            echo 'Henüz hiç bilet almadınız.';
                    }
                    ?>
                </p>
                <a href="sefer_ara.php" class="btn btn-primary mt-3">
                    🔍 Sefer Ara ve Bilet Al
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($tickets as $ticket): 
                $is_cancelled = $ticket['status'] === 'cancelled';
            ?>
            <div class="ticket-item <?php echo $is_cancelled ? 'cancelled' : ''; ?>">
                <!-- Bilet Header -->
                <div class="ticket-header-row">
                    <div class="company-info">
                        <?php if (!empty($ticket['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($ticket['logo_path']); ?>" 
                                 alt="Logo" class="company-logo">
                        <?php endif; ?>
                        <div>
                            <div class="company-name"><?php echo htmlspecialchars($ticket['company_name']); ?></div>
                            <small class="text-muted">Bilet #<?php echo str_pad($ticket['id'], 8, '0', STR_PAD_LEFT); ?></small>
                        </div>
                    </div>
                    <div>
                        <span class="ticket-status <?php echo $ticket['status']; ?>">
                            <?php echo $is_cancelled ? '❌ İptal Edildi' : '✅ Aktif'; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Güzergah -->
                <div class="route-section">
                    <div class="route-city">
                        <div class="city-name"><?php echo htmlspecialchars($ticket['departure_city']); ?></div>
                        <div class="city-time">Kalkış: <?php echo htmlspecialchars($ticket['departure_time']); ?></div>
                    </div>
                    <div class="route-arrow">→</div>
                    <div class="route-city text-end">
                        <div class="city-name"><?php echo htmlspecialchars($ticket['destination_city']); ?></div>
                        <div class="city-time">Varış: <?php echo htmlspecialchars($ticket['arrival_time']); ?></div>
                    </div>
                </div>
                
                <!-- Detaylar -->
                <div class="ticket-details">
                    <div class="detail-item">
                        <div class="detail-label">Koltuk No</div>
                        <div class="detail-value"><?php echo $ticket['seat_number']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Satın Alma</div>
                        <div class="detail-value"><?php echo date('d.m.Y', strtotime($ticket['created_at'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Tutar</div>
                        <div class="detail-value"><?php echo number_format($ticket['total_price'], 2); ?> TL</div>
                    </div>
                </div>
                
                <!-- Aksiyon Butonları -->
                <div class="ticket-actions">
                    <a href="bilet_detay.php?ticket_id=<?php echo $ticket['id']; ?>" 
                       class="btn btn-detail">
                        📋 Detaylar
                    </a>
                    <a href="bilet_pdf.php?ticket_id=<?php echo $ticket['id']; ?>" 
                       class="btn btn-pdf" target="_blank">
                        📄 PDF İndir
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($tickets)): ?>
        <div class="text-center mt-4 mb-5">
            <a href="sefer_ara.php" class="btn btn-primary btn-lg">
                🔍 Yeni Bilet Al
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>