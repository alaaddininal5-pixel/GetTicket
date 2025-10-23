<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Sadece company_admin ve admin erişebilir
if (!isset($_SESSION['user_id']) || 
    ($_SESSION['role'] !== 'company_admin' && $_SESSION['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$db = getDBConnection();

// Kullanıcı bilgilerini getir
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Sefer filtresi
$trip_filter = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : null;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Firma kontrolü
$where_clause = $_SESSION['role'] === 'company_admin' ? 
    "WHERE tr.company_id = {$user['company_id']}" : "WHERE 1=1";

// Firma seferlerini getir (dropdown için)
$trips_sql = "
    SELECT tr.id, tr.departure_city, tr.destination_city, tr.departure_time,
           COUNT(t.id) as ticket_count
    FROM trips tr
    LEFT JOIN tickets t ON tr.id = t.trip_id
    $where_clause
    GROUP BY tr.id
    ORDER BY tr.created_date DESC
";
$stmt = $db->query($trips_sql);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rezervasyonları getir
$sql = "
    SELECT 
        t.*,
        tr.departure_city,
        tr.destination_city,
        tr.departure_time,
        tr.arrival_time,
        tr.price as trip_price,
        u.full_name as passenger_name,
        u.email as passenger_email,
        bc.name as company_name
    FROM tickets t
    LEFT JOIN trips tr ON t.trip_id = tr.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN bus_companies bc ON tr.company_id = bc.id
    $where_clause
";

$params = [];

// Sefer filtresi
if ($trip_filter) {
    $sql .= " AND tr.id = ?";
    $params[] = $trip_filter;
}

// Durum filtresi
if ($status_filter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stats_sql = "
    SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN t.status = 'active' THEN 1 ELSE 0 END) as active_tickets,
        SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tickets,
        SUM(CASE WHEN t.status = 'active' THEN t.total_price ELSE 0 END) as total_revenue
    FROM tickets t
    LEFT JOIN trips tr ON t.trip_id = tr.id
    $where_clause
";
$stmt = $db->query($stats_sql);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervasyonlar - GetTicket</title>
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
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .filter-btn {
            padding: 8px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 20px;
            margin: 5px;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 0.9rem;
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
        
        .reservation-card {
            background: white;
            border-left: 5px solid #667eea;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .reservation-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .reservation-card.cancelled {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        
        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .ticket-number {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .reservation-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: bold;
            color: #333;
        }
        
        .route-info {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .route-arrow {
            color: #667eea;
            margin: 0 10px;
        }
        
        .passenger-info {
            background: #fff3cd;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
        }
        
        .passenger-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .passenger-email {
            color: #666;
            font-size: 0.9rem;
        }
        
        .no-reservations {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table-responsive {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            border-top: none;
            color: #667eea;
            font-weight: 600;
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
                <a class="nav-link" href="seferlerim.php">Seferlerim</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">🎟️ Rezervasyonlar</h2>
        
        <!-- İstatistikler -->
        <div class="stats-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_tickets']; ?></div>
                        <div class="stat-label">Toplam Rezervasyon</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #28a745;"><?php echo $stats['active_tickets']; ?></div>
                        <div class="stat-label">Aktif Bilet</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #dc3545;"><?php echo $stats['cancelled_tickets']; ?></div>
                        <div class="stat-label">İptal Edilmiş</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #ffc107;">
                            <?php echo number_format($stats['total_revenue'], 0); ?> ₺
                        </div>
                        <div class="stat-label">Toplam Gelir</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtreler -->
        <div class="filter-card">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label class="form-label fw-bold">🚌 Sefer Filtresi</label>
                    <select class="form-select" onchange="window.location.href='?trip_id=' + this.value + '&status=<?php echo $status_filter; ?>'">
                        <option value="">Tüm Seferler</option>
                        <?php foreach ($trips as $trip): ?>
                            <option value="<?php echo $trip['id']; ?>" <?php echo $trip_filter == $trip['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($trip['departure_city']); ?> → 
                                <?php echo htmlspecialchars($trip['destination_city']); ?> 
                                (<?php echo $trip['departure_time']; ?>) - 
                                <?php echo $trip['ticket_count']; ?> bilet
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">📊 Durum Filtresi</label>
                    <div class="d-flex flex-wrap">
                        <a href="?trip_id=<?php echo $trip_filter; ?>&status=all" 
                           class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                            Tümü
                        </a>
                        <a href="?trip_id=<?php echo $trip_filter; ?>&status=active" 
                           class="filter-btn <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                            Aktif
                        </a>
                        <a href="?trip_id=<?php echo $trip_filter; ?>&status=cancelled" 
                           class="filter-btn <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                            İptal
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rezervasyon Listesi -->
        <?php if (empty($reservations)): ?>
            <div class="no-reservations">
                <div style="font-size: 5rem; opacity: 0.5;">🎟️</div>
                <h3>Rezervasyon Bulunamadı</h3>
                <p class="text-muted">
                    <?php 
                    if ($trip_filter) {
                        echo 'Bu sefer için rezervasyon bulunmuyor.';
                    } elseif ($status_filter === 'active') {
                        echo 'Henüz aktif rezervasyon yok.';
                    } elseif ($status_filter === 'cancelled') {
                        echo 'İptal edilmiş rezervasyon yok.';
                    } else {
                        echo 'Henüz hiç rezervasyon yapılmamış.';
                    }
                    ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($reservations as $reservation): ?>
            <div class="reservation-card <?php echo $reservation['status'] === 'cancelled' ? 'cancelled' : ''; ?>">
                <div class="reservation-header">
                    <div>
                        <span class="ticket-number">
                            🎫 TICKET-<?php echo str_pad($reservation['id'], 8, '0', STR_PAD_LEFT); ?>
                        </span>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <div class="text-muted small mt-1">
                                <?php echo htmlspecialchars($reservation['company_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge <?php echo $reservation['status']; ?>">
                        <?php echo $reservation['status'] === 'active' ? '✅ Aktif' : '❌ İptal'; ?>
                    </span>
                </div>
                
                <div class="route-info">
                    <?php echo htmlspecialchars($reservation['departure_city']); ?>
                    <span class="route-arrow">→</span>
                    <?php echo htmlspecialchars($reservation['destination_city']); ?>
                </div>
                
                <div class="reservation-details">
                    <div class="detail-item">
                        <span class="detail-label">🕐 Kalkış</span>
                        <span class="detail-value"><?php echo htmlspecialchars($reservation['departure_time']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🕐 Varış</span>
                        <span class="detail-value"><?php echo htmlspecialchars($reservation['arrival_time']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">💺 Koltuk No</span>
                        <span class="detail-value"><?php echo $reservation['seat_number']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">💰 Ücret</span>
                        <span class="detail-value"><?php echo number_format($reservation['total_price'], 2); ?> TL</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">📅 Satın Alma</span>
                        <span class="detail-value"><?php echo date('d.m.Y H:i', strtotime($reservation['created_at'])); ?></span>
                    </div>
                </div>
                
                <div class="passenger-info">
                    <div class="passenger-name">
                        👤 <?php echo htmlspecialchars($reservation['passenger_name']); ?>
                    </div>
                    <div class="passenger-email">
                        📧 <?php echo htmlspecialchars($reservation['passenger_email']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Özet Tablo -->
            <div class="table-responsive mt-4">
                <h5 class="mb-3">📊 Özet Tablo</h5>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Bilet No</th>
                            <th>Yolcu</th>
                            <th>Güzergah</th>
                            <th>Koltuk</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                        <tr style="<?php echo $reservation['status'] === 'cancelled' ? 'opacity: 0.6;' : ''; ?>">
                            <td>
                                <span class="badge bg-primary">
                                    #<?php echo str_pad($reservation['id'], 6, '0', STR_PAD_LEFT); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($reservation['passenger_name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($reservation['departure_city']); ?> → 
                                <?php echo htmlspecialchars($reservation['destination_city']); ?>
                            </td>
                            <td><strong><?php echo $reservation['seat_number']; ?></strong></td>
                            <td><strong><?php echo number_format($reservation['total_price'], 2); ?> ₺</strong></td>
                            <td>
                                <?php if ($reservation['status'] === 'active'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">İptal</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($reservation['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>