<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Sadece admin erişebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$db = getDBConnection();

// GENEL İSTATİSTİKLER
$stats = [];

// Kullanıcı istatistikleri
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
        SUM(CASE WHEN role = 'company_admin' THEN 1 ELSE 0 END) as company_admins,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(balance) as total_balance
    FROM users
");
$stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Firma istatistikleri
$stmt = $db->query("SELECT COUNT(*) as total FROM bus_companies");
$stats['companies'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Sefer istatistikleri
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(capacity) as total_capacity,
        AVG(price) as avg_price
    FROM trips
");
$stats['trips'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Bilet istatistikleri
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'active' THEN total_price ELSE 0 END) as total_revenue,
        AVG(total_price) as avg_ticket_price
    FROM tickets
");
$stats['tickets'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Kupon istatistikleri
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_coupons,
        (SELECT COUNT(*) FROM user_coupons) as used_coupons
    FROM coupons
");
$stats['coupons'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Firma bazlı satışlar
$stmt = $db->query("
    SELECT 
        bc.name as company_name,
        COUNT(t.id) as ticket_count,
        SUM(CASE WHEN t.status = 'active' THEN t.total_price ELSE 0 END) as revenue,
        COUNT(DISTINCT tr.id) as trip_count
    FROM bus_companies bc
    LEFT JOIN trips tr ON bc.id = tr.company_id
    LEFT JOIN tickets t ON tr.id = t.trip_id
    GROUP BY bc.id
    ORDER BY revenue DESC
");
$company_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// En çok satan seferler
$stmt = $db->query("
    SELECT 
        tr.departure_city,
        tr.destination_city,
        tr.departure_time,
        tr.price,
        bc.name as company_name,
        COUNT(t.id) as ticket_count,
        SUM(t.total_price) as revenue
    FROM trips tr
    LEFT JOIN tickets t ON tr.id = t.trip_id AND t.status = 'active'
    LEFT JOIN bus_companies bc ON tr.company_id = bc.id
    GROUP BY tr.id
    HAVING ticket_count > 0
    ORDER BY ticket_count DESC
    LIMIT 10
");
$top_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son 7 günlük satışlar (örnek - tarih sistemi yok)
$stmt = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as ticket_count,
        SUM(total_price) as revenue
    FROM tickets
    WHERE status = 'active'
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 7
");
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Doluluk oranı
$occupancy_query = $db->query("
    SELECT 
        SUM(tr.capacity) as total_capacity,
        COUNT(t.id) as sold_tickets
    FROM trips tr
    LEFT JOIN tickets t ON tr.id = t.trip_id AND t.status = 'active'
");
$occupancy_data = $occupancy_query->fetch(PDO::FETCH_ASSOC);
$occupancy_rate = $occupancy_data['total_capacity'] > 0 ? 
    ($occupancy_data['sold_tickets'] / $occupancy_data['total_capacity']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem İstatistikleri - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-sublabel {
            color: #999;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .chart-card h4 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }
        
        .progress-custom {
            height: 30px;
            border-radius: 15px;
            background: #e9ecef;
        }
        
        .progress-bar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .company-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .company-name {
            font-weight: 600;
            color: #333;
        }
        
        .company-stats {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
        }
        
        .trip-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        
        .trip-route {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .trip-details {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .badge-custom {
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.85rem;
        }
        
        .table-stats {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table-stats th {
            border-top: none;
            color: #667eea;
            font-weight: 600;
        }
        
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .summary-item {
            text-align: center;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }
        
        .summary-value {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .summary-label {
            opacity: 0.9;
            font-size: 0.9rem;
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
                    Admin: <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                </span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">📊 Sistem İstatistikleri</h2>
        
        <!-- Özet Kutu -->
        <div class="summary-box">
            <h3 class="mb-3">💰 Genel Özet</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value"><?php echo number_format($stats['tickets']['total_revenue'], 0); ?> ₺</div>
                    <div class="summary-label">Toplam Gelir</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $stats['tickets']['active']; ?></div>
                    <div class="summary-label">Aktif Bilet</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $stats['companies']['total']; ?></div>
                    <div class="summary-label">Firma</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $stats['users']['users']; ?></div>
                    <div class="summary-label">Yolcu</div>
                </div>
            </div>
        </div>
        
        <!-- Ana İstatistikler -->
        <div class="stats-grid">
            <!-- Kullanıcılar -->
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $stats['users']['total']; ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
                <div class="stat-sublabel">
                    <?php echo $stats['users']['users']; ?> Yolcu • 
                    <?php echo $stats['users']['company_admins']; ?> Firma Admin
                </div>
            </div>
            
            <!-- Firmalar -->
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-value"><?php echo $stats['companies']['total']; ?></div>
                <div class="stat-label">Otobüs Firması</div>
            </div>
            
            <!-- Seferler -->
            <div class="stat-card">
                <div class="stat-icon">🚌</div>
                <div class="stat-value"><?php echo $stats['trips']['total']; ?></div>
                <div class="stat-label">Toplam Sefer</div>
                <div class="stat-sublabel">
                    <?php echo number_format($stats['trips']['total_capacity'], 0); ?> Toplam Koltuk
                </div>
            </div>
            
            <!-- Biletler -->
            <div class="stat-card">
                <div class="stat-icon">🎫</div>
                <div class="stat-value"><?php echo $stats['tickets']['total']; ?></div>
                <div class="stat-label">Satılan Bilet</div>
                <div class="stat-sublabel">
                    <?php echo $stats['tickets']['active']; ?> Aktif • 
                    <?php echo $stats['tickets']['cancelled']; ?> İptal
                </div>
            </div>
            
            <!-- Gelir -->
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?php echo number_format($stats['tickets']['total_revenue'], 0); ?> ₺</div>
                <div class="stat-label">Toplam Gelir</div>
                <div class="stat-sublabel">
                    Ort: <?php echo number_format($stats['tickets']['avg_ticket_price'], 2); ?> ₺/bilet
                </div>
            </div>
            
            <!-- Kuponlar -->
            <div class="stat-card">
                <div class="stat-icon">🎟️</div>
                <div class="stat-value"><?php echo $stats['coupons']['total_coupons']; ?></div>
                <div class="stat-label">Aktif Kupon</div>
                <div class="stat-sublabel">
                    <?php echo $stats['coupons']['used_coupons']; ?> Kullanım
                </div>
            </div>
            
            <!-- Bakiye -->
            <div class="stat-card">
                <div class="stat-icon">💳</div>
                <div class="stat-value"><?php echo number_format($stats['users']['total_balance'], 0); ?> ₺</div>
                <div class="stat-label">Sistemdeki Bakiye</div>
            </div>
            
            <!-- Doluluk -->
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo number_format($occupancy_rate, 1); ?>%</div>
                <div class="stat-label">Ortalama Doluluk</div>
                <div class="stat-sublabel">
                    <?php echo $occupancy_data['sold_tickets']; ?> / <?php echo $occupancy_data['total_capacity']; ?> koltuk
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Firma Bazlı Satışlar -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <h4>🏢 Firma Bazlı Performans</h4>
                    <?php if (empty($company_stats)): ?>
                        <p class="text-muted text-center py-4">Henüz firma verisi yok</p>
                    <?php else: ?>
                        <?php foreach ($company_stats as $company): ?>
                        <div class="company-item">
                            <div>
                                <div class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
                                <div class="company-stats">
                                    <span>🎫 <?php echo $company['ticket_count']; ?> bilet</span>
                                    <span>🚌 <?php echo $company['trip_count']; ?> sefer</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <strong style="color: #28a745; font-size: 1.2rem;">
                                    <?php echo number_format($company['revenue'], 0); ?> ₺
                                </strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- En Çok Satan Seferler -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <h4>🔥 En Çok Satan Seferler</h4>
                    <?php if (empty($top_trips)): ?>
                        <p class="text-muted text-center py-4">Henüz satış yok</p>
                    <?php else: ?>
                        <?php foreach ($top_trips as $index => $trip): ?>
                        <div class="trip-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge-custom" style="background: #667eea; color: white;">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                    <div class="trip-route">
                                        <?php echo htmlspecialchars($trip['departure_city']); ?> → 
                                        <?php echo htmlspecialchars($trip['destination_city']); ?>
                                    </div>
                                </div>
                                <strong style="color: #28a745;">
                                    <?php echo number_format($trip['revenue'], 0); ?> ₺
                                </strong>
                            </div>
                            <div class="trip-details">
                                <span>🏢 <?php echo htmlspecialchars($trip['company_name']); ?></span>
                                <span>🕐 <?php echo $trip['departure_time']; ?></span>
                                <span>🎫 <?php echo $trip['ticket_count']; ?> bilet</span>
                                <span>💰 <?php echo number_format($trip['price'], 2); ?> ₺</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Günlük Satışlar -->
        <?php if (!empty($daily_sales)): ?>
        <div class="chart-card">
            <h4>📈 Son 7 Günlük Satışlar</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Bilet Sayısı</th>
                            <th>Gelir</th>
                            <th>Ortalama Fiyat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_sales as $day): ?>
                        <tr>
                            <td><strong><?php echo date('d.m.Y', strtotime($day['date'])); ?></strong></td>
                            <td><?php echo $day['ticket_count']; ?> bilet</td>
                            <td><strong style="color: #28a745;"><?php echo number_format($day['revenue'], 2); ?> ₺</strong></td>
                            <td><?php echo number_format($day['revenue'] / $day['ticket_count'], 2); ?> ₺</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Doluluk Oranı Görseli -->
        <div class="chart-card">
            <h4>📊 Sistem Doluluk Oranı</h4>
            <div class="progress-custom">
                <div class="progress-bar-custom" style="width: <?php echo $occupancy_rate; ?>%">
                    <?php echo number_format($occupancy_rate, 1); ?>%
                </div>
            </div>
            <div class="text-center mt-3">
                <p class="text-muted mb-0">
                    <?php echo $occupancy_data['sold_tickets']; ?> koltuk satıldı / 
                    <?php echo $occupancy_data['total_capacity']; ?> toplam kapasite
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>