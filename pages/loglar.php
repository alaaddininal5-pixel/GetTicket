<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Sadece admin erişebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$db = getDBConnection();

// Filtreleme
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

// Log verilerini topla
$logs = [];

// 1. KULLANICI İŞLEMLERİ (Kayıt, Giriş)
$stmt = $db->query("
    SELECT 
        id,
        full_name,
        email,
        role,
        created_at,
        'user_register' as log_type,
        'Yeni kullanıcı kaydı' as log_action
    FROM users
    ORDER BY created_at DESC
");
$user_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($user_logs as $log) {
    $logs[] = [
        'id' => 'USER-' . $log['id'],
        'type' => 'user',
        'action' => $log['log_action'],
        'description' => $log['full_name'] . ' (' . $log['email'] . ') sisteme kayıt oldu',
        'user' => $log['full_name'],
        'timestamp' => $log['created_at'],
        'icon' => '👤',
        'color' => '#17a2b8'
    ];
}

// 2. FİRMA İŞLEMLERİ
$stmt = $db->query("
    SELECT 
        id,
        name,
        created_at
    FROM bus_companies
    ORDER BY created_at DESC
");
$company_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($company_logs as $log) {
    $logs[] = [
        'id' => 'COMPANY-' . $log['id'],
        'type' => 'company',
        'action' => 'Yeni firma eklendi',
        'description' => $log['name'] . ' firması sisteme eklendi',
        'user' => 'Admin',
        'timestamp' => $log['created_at'],
        'icon' => '🏢',
        'color' => '#ffc107'
    ];
}

// 3. SEFER İŞLEMLERİ
$stmt = $db->query("
    SELECT 
        t.id,
        t.departure_city,
        t.destination_city,
        t.price,
        t.created_date,
        bc.name as company_name
    FROM trips t
    LEFT JOIN bus_companies bc ON t.company_id = bc.id
    ORDER BY t.created_date DESC
");
$trip_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($trip_logs as $log) {
    $logs[] = [
        'id' => 'TRIP-' . $log['id'],
        'type' => 'trip',
        'action' => 'Yeni sefer oluşturuldu',
        'description' => $log['company_name'] . ' - ' . $log['departure_city'] . ' → ' . $log['destination_city'] . ' (' . $log['price'] . ' TL)',
        'user' => $log['company_name'],
        'timestamp' => $log['created_date'],
        'icon' => '🚌',
        'color' => '#667eea'
    ];
}

// 4. BİLET İŞLEMLERİ
$stmt = $db->query("
    SELECT 
        ti.id,
        ti.status,
        ti.total_price,
        ti.created_at,
        u.full_name as user_name,
        tr.departure_city,
        tr.destination_city
    FROM tickets ti
    LEFT JOIN users u ON ti.user_id = u.id
    LEFT JOIN trips tr ON ti.trip_id = tr.id
    ORDER BY ti.created_at DESC
");
$ticket_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($ticket_logs as $log) {
    $action = $log['status'] === 'active' ? 'Bilet satın alındı' : 'Bilet iptal edildi';
    $logs[] = [
        'id' => 'TICKET-' . $log['id'],
        'type' => $log['status'] === 'active' ? 'ticket_purchase' : 'ticket_cancel',
        'action' => $action,
        'description' => $log['user_name'] . ' - ' . $log['departure_city'] . ' → ' . $log['destination_city'] . ' (' . number_format($log['total_price'], 2) . ' TL)',
        'user' => $log['user_name'],
        'timestamp' => $log['created_at'],
        'icon' => $log['status'] === 'active' ? '🎫' : '❌',
        'color' => $log['status'] === 'active' ? '#28a745' : '#dc3545'
    ];
}

// 5. KUPON İŞLEMLERİ
$stmt = $db->query("
    SELECT 
        c.id,
        c.code,
        c.discount,
        c.created_at
    FROM coupons c
    ORDER BY c.created_at DESC
");
$coupon_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($coupon_logs as $log) {
    $logs[] = [
        'id' => 'COUPON-' . $log['id'],
        'type' => 'coupon',
        'action' => 'Yeni kupon oluşturuldu',
        'description' => $log['code'] . ' kuponu (-%' . $log['discount'] . ') oluşturuldu',
        'user' => 'Admin',
        'timestamp' => $log['created_at'],
        'icon' => '🎟️',
        'color' => '#e83e8c'
    ];
}

// 6. KUPON KULLANIMLARI
$stmt = $db->query("
    SELECT 
        uc.used_at,
        c.code,
        u.full_name
    FROM user_coupons uc
    LEFT JOIN coupons c ON uc.coupon_id = c.id
    LEFT JOIN users u ON uc.user_id = u.id
    ORDER BY uc.used_at DESC
");
$coupon_usage_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($coupon_usage_logs as $log) {
    $logs[] = [
        'id' => 'COUPON-USE-' . strtotime($log['used_at']),
        'type' => 'coupon_usage',
        'action' => 'Kupon kullanıldı',
        'description' => $log['full_name'] . ' - ' . $log['code'] . ' kuponunu kullandı',
        'user' => $log['full_name'],
        'timestamp' => $log['used_at'],
        'icon' => '🎁',
        'color' => '#fd7e14'
    ];
}

// Logları tarihe göre sırala
usort($logs, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Filtreleme uygula
$filtered_logs = [];
foreach ($logs as $log) {
    // Tip filtresi
    if ($filter_type !== 'all' && $log['type'] !== $filter_type) {
        continue;
    }
    
    // Arama filtresi
    if (!empty($search)) {
        $search_lower = strtolower($search);
        if (strpos(strtolower($log['description']), $search_lower) === false &&
            strpos(strtolower($log['user']), $search_lower) === false &&
            strpos(strtolower($log['action']), $search_lower) === false) {
            continue;
        }
    }
    
    $filtered_logs[] = $log;
}

// Limit uygula
$filtered_logs = array_slice($filtered_logs, 0, $limit);

// İstatistikler
$total_logs = count($logs);
$log_types = [
    'user' => 0,
    'company' => 0,
    'trip' => 0,
    'ticket_purchase' => 0,
    'ticket_cancel' => 0,
    'coupon' => 0,
    'coupon_usage' => 0
];

foreach ($logs as $log) {
    if (isset($log_types[$log['type']])) {
        $log_types[$log['type']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Logları - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .log-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid #667eea;
        }
        
        .log-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .log-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .log-action {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }
        
        .log-timestamp {
            color: #999;
            font-size: 0.85rem;
        }
        
        .log-description {
            color: #666;
            margin-left: 40px;
            font-size: 0.95rem;
        }
        
        .log-user {
            display: inline-block;
            background: #f8f9fa;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            color: #666;
            margin-top: 8px;
            margin-left: 40px;
        }
        
        .log-id {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #999;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 4px;
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
            cursor: pointer;
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
        
        .no-logs {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .timeline-date {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            margin: 20px 0 15px 0;
            display: inline-block;
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
                <a class="nav-link" href="sistem_istatistik.php">İstatistikler</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">📋 Sistem Logları</h2>
        
        <!-- İstatistikler -->
        <div class="stats-card">
            <div class="row">
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_logs; ?></div>
                        <div class="stat-label">Toplam Log</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $log_types['user']; ?></div>
                        <div class="stat-label">Kullanıcı</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $log_types['company']; ?></div>
                        <div class="stat-label">Firma</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $log_types['trip']; ?></div>
                        <div class="stat-label">Sefer</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $log_types['ticket_purchase']; ?></div>
                        <div class="stat-label">Bilet Satışı</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $log_types['coupon_usage']; ?></div>
                        <div class="stat-label">Kupon Kullanımı</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtreler -->
        <div class="filter-card">
            <h5 class="mb-3">🔍 Filtrele ve Ara</h5>
            
            <!-- Arama -->
            <form method="GET" class="search-box">
                <input type="text" name="search" class="form-control" 
                       placeholder="Arama yapın... (kullanıcı, işlem, açıklama)"
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="limit" class="form-select" style="max-width: 150px;">
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 kayıt</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 kayıt</option>
                    <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200 kayıt</option>
                    <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500 kayıt</option>
                </select>
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>">
                <button type="submit" class="btn btn-primary">Ara</button>
                <?php if (!empty($search)): ?>
                    <a href="?type=<?php echo $filter_type; ?>&limit=<?php echo $limit; ?>" class="btn btn-secondary">Temizle</a>
                <?php endif; ?>
            </form>
            
            <!-- Tip Filtreleri -->
            <div class="d-flex flex-wrap">
                <a href="?type=all&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>" 
                   class="filter-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                    📋 Tümü
                </a>
                <a href="?type=user&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>" 
                   class="filter-btn <?php echo $filter_type === 'user' ? 'active' : ''; ?>">
                    👤 Kullanıcılar
                </a>
                <a href="?type=company&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>" 
                   class="filter-btn <?php echo $filter_type === 'company' ? 'active' : ''; ?>">
                    🏢 Firmalar
                </a>
                <a href="?type=trip&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>" 
                   class="filter-btn <?php echo $filter_type === 'trip' ? 'active' : ''; ?>">
                    🚌 Seferler
                </a>
                <a href="?type=ticket_purchase&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>" 
                   class="filter-btn <?php echo $filter_type === 'ticket_purchase' ? 'active' : ''; ?>">
                    🎫 Bilet Satışı
                </a>
                <a href="?type=ticket_cancel&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>" 
                   class="filter-btn <?php echo $filter_type === 'ticket_cancel' ? 'active' : ''; ?>">
                    ❌ Bilet İptali
                </a>
                <a href="?type=coupon&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>" 
                   class="filter-btn <?php echo $filter_type === 'coupon' ? 'active' : ''; ?>">
                    🎟️ Kuponlar
                </a>
            </div>
        </div>
        
        <!-- Log Listesi -->
        <?php if (empty($filtered_logs)): ?>
            <div class="no-logs">
                <div style="font-size: 5rem; opacity: 0.5;">📋</div>
                <h3>Log Bulunamadı</h3>
                <p class="text-muted">Arama kriterlerinizi değiştirerek tekrar deneyin.</p>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <strong><?php echo count($filtered_logs); ?></strong> log gösteriliyor
            </div>
            
            <?php 
            $current_date = '';
            foreach ($filtered_logs as $log): 
                $log_date = date('d.m.Y', strtotime($log['timestamp']));
                if ($log_date !== $current_date):
                    $current_date = $log_date;
            ?>
                <div class="timeline-date">📅 <?php echo $log_date; ?></div>
            <?php endif; ?>
            
            <div class="log-item" style="border-left-color: <?php echo $log['color']; ?>;">
                <div class="log-header">
                    <div>
                        <span class="log-icon"><?php echo $log['icon']; ?></span>
                        <span class="log-action"><?php echo htmlspecialchars($log['action']); ?></span>
                        <span class="log-id"><?php echo $log['id']; ?></span>
                    </div>
                    <span class="log-timestamp">
                        <?php echo date('H:i:s', strtotime($log['timestamp'])); ?>
                    </span>
                </div>
                <div class="log-description">
                    <?php echo htmlspecialchars($log['description']); ?>
                </div>
                <span class="log-user">
                    👤 <?php echo htmlspecialchars($log['user']); ?>
                </span>
            </div>
            <?php endforeach; ?>
            
            <?php if (count($filtered_logs) >= $limit): ?>
                <div class="alert alert-info text-center mt-4">
                    ℹ️ Sadece ilk <?php echo $limit; ?> kayıt gösteriliyor. 
                    Daha fazla görmek için limit değerini artırın.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>