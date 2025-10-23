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

// Ticket ID kontrolü
if (!isset($_GET['ticket_id'])) {
    header("Location: biletlerim.php");
    exit();
}

$ticket_id = (int)$_GET['ticket_id'];

// Bilet bilgilerini getir
$stmt = $db->prepare("
    SELECT 
        t.*, 
        tr.departure_city, 
        tr.destination_city, 
        tr.departure_time, 
        tr.arrival_time, 
        tr.price as original_price,
        bc.name as company_name, 
        bc.logo_path,
        u.full_name as passenger_name,
        u.email as passenger_email
    FROM tickets t
    LEFT JOIN trips tr ON t.trip_id = tr.id
    LEFT JOIN bus_companies bc ON tr.company_id = bc.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$ticket_id, $_SESSION['user_id']]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header("Location: biletlerim.php");
    exit();
}

// Başarı mesajı
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = '<div class="alert alert-success alert-dismissible fade show">
        <strong>✅ Tebrikler!</strong> Biletiniz başarıyla satın alındı!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

// İptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket'])) {
    // 1 saat kuralı kontrolü
    $departure_datetime = date('Y-m-d') . ' ' . $ticket['departure_time'];
    $time_diff = strtotime($departure_datetime) - time();
    $hours_remaining = $time_diff / 3600;
    
    if ($ticket['status'] !== 'active') {
        $error = "Bu bilet zaten iptal edilmiş!";
    } elseif ($hours_remaining < 1) {
        $error = "Kalkış saatine 1 saatten az süre kaldığı için iptal edilemez!";
    } else {
        try {
            $db->beginTransaction();
            
            // Bileti iptal et
            $stmt = $db->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$ticket_id]);
            
            // Parayı iade et
            $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$ticket['total_price'], $_SESSION['user_id']]);
            
            // Session'ı güncelle
            $_SESSION['balance'] = $_SESSION['balance'] + $ticket['total_price'];
            
            // Koltuk bloğunu kaldır
            $stmt = $db->prepare("DELETE FROM blocked_seats WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);
            
            $db->commit();
            
            // Başarılı, biletlerim sayfasına yönlendir
            header("Location: biletlerim.php?cancelled=1");
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "İptal işlemi sırasında hata oluştu: " . $e->getMessage();
        }
    }
}

// Kalan süreyi hesapla
$departure_datetime = date('Y-m-d') . ' ' . $ticket['departure_time'];
$time_diff = strtotime($departure_datetime) - time();
$hours_remaining = $time_diff / 3600;
$can_cancel = ($ticket['status'] === 'active' && $hours_remaining >= 1);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Detayı - GetTicket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .ticket-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .ticket-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            position: relative;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            position: relative;
        }
        
        .ticket-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: white;
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
        }
        
        .ticket-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .ticket-status.active {
            background: #28a745;
            color: white;
        }
        
        .ticket-status.cancelled {
            background: #dc3545;
            color: white;
        }
        
        .ticket-body {
            padding: 40px 30px 30px;
        }
        
        .company-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .company-logo {
            height: 60px;
            object-fit: contain;
        }
        
        .route-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            position: relative;
        }
        
        .route-cities {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .city {
            text-align: center;
            flex: 1;
        }
        
        .city-name {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
        }
        
        .city-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .route-arrow {
            font-size: 3rem;
            color: #667eea;
            margin: 0 30px;
        }
        
        .route-details {
            display: flex;
            justify-content: space-around;
            border-top: 2px dashed #dee2e6;
            padding-top: 20px;
        }
        
        .route-detail-item {
            text-align: center;
        }
        
        .detail-label {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        
        .qr-section {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 15px;
            margin: 30px 0;
        }
        
        .qr-code {
            width: 200px;
            height: 200px;
            background: white;
            border: 3px solid #667eea;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .ticket-number {
            font-family: 'Courier New', monospace;
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 2px;
        }
        
        .passenger-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
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
        
        .price-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin: 30px 0;
        }
        
        .price-label {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .price-amount {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-cancel {
            background: #dc3545;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .cancelled-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 4rem;
            font-weight: bold;
            color: rgba(220, 53, 69, 0.2);
            text-transform: uppercase;
            pointer-events: none;
            white-space: nowrap;
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
        <div class="ticket-container">
            <?php echo $success_message; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong>Hata!</strong> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="ticket-card">
                <?php if ($ticket['status'] === 'cancelled'): ?>
                    <div class="cancelled-overlay">İPTAL EDİLDİ</div>
                <?php endif; ?>
                
                <!-- Bilet Header -->
                <div class="ticket-header">
                    <div class="ticket-status <?php echo $ticket['status']; ?>">
                        <?php echo $ticket['status'] === 'active' ? '✓ Aktif' : '✗ İptal Edildi'; ?>
                    </div>
                    <h2 class="mb-2">🎫 Otobüs Bileti</h2>
                    <p class="mb-0 opacity-75">Bilet Numarası: #<?php echo str_pad($ticket['id'], 8, '0', STR_PAD_LEFT); ?></p>
                </div>
                
                <!-- Bilet Body -->
                <div class="ticket-body">
                    <!-- Firma Bilgisi -->
                    <div class="company-info">
                        <?php if (!empty($ticket['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($ticket['logo_path']); ?>" 
                                 alt="Logo" class="company-logo">
                        <?php endif; ?>
                        <div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($ticket['company_name']); ?></h4>
                            <small class="text-muted">Otobüs Firması</small>
                        </div>
                    </div>
                    
                    <!-- Güzergah -->
                    <div class="route-section">
                        <div class="route-cities">
                            <div class="city">
                                <div class="city-name"><?php echo htmlspecialchars($ticket['departure_city']); ?></div>
                                <div class="city-label">Kalkış</div>
                            </div>
                            <div class="route-arrow">→</div>
                            <div class="city">
                                <div class="city-name"><?php echo htmlspecialchars($ticket['destination_city']); ?></div>
                                <div class="city-label">Varış</div>
                            </div>
                        </div>
                        
                        <div class="route-details">
                            <div class="route-detail-item">
                                <div class="detail-label">Kalkış Saati</div>
                                <div class="detail-value"><?php echo htmlspecialchars($ticket['departure_time']); ?></div>
                            </div>
                            <div class="route-detail-item">
                                <div class="detail-label">Varış Saati</div>
                                <div class="detail-value"><?php echo htmlspecialchars($ticket['arrival_time']); ?></div>
                            </div>
                            <div class="route-detail-item">
                                <div class="detail-label">Koltuk No</div>
                                <div class="detail-value"><?php echo $ticket['seat_number']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- QR Kod -->
                    <div class="qr-section">
                        <div class="qr-code">
                            📱
                        </div>
                        <div class="ticket-number">TICKET-<?php echo str_pad($ticket['id'], 8, '0', STR_PAD_LEFT); ?></div>
                        <small class="text-muted d-block mt-2">Binişte gösteriniz</small>
                    </div>
                    
                    <!-- Yolcu Bilgileri -->
                    <div class="passenger-info">
                        <h5 class="mb-3">👤 Yolcu Bilgileri</h5>
                        <div class="info-row">
                            <span class="info-label">Ad Soyad:</span>
                            <span class="info-value"><?php echo htmlspecialchars($ticket['passenger_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">E-posta:</span>
                            <span class="info-value"><?php echo htmlspecialchars($ticket['passenger_email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Satın Alma Tarihi:</span>
                            <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <!-- Fiyat -->
                    <div class="price-section">
                        <div class="price-label">Ödenen Tutar</div>
                        <div class="price-amount"><?php echo number_format($ticket['total_price'], 2); ?> TL</div>
                        <?php if ($ticket['total_price'] < $ticket['original_price']): ?>
                            <small class="d-block mt-2">
                                <del><?php echo number_format($ticket['original_price'], 2); ?> TL</del>
                                <span class="ms-2">🎟️ Kupon indirimli</span>
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <!-- İptal Uyarısı -->
                    <?php if ($ticket['status'] === 'active' && !$can_cancel): ?>
                        <div class="warning-box">
                            <strong>⚠️ Dikkat!</strong> 
                            Kalkış saatine 1 saatten az süre kaldığı için bu bilet iptal edilemez.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Aksiyon Butonları -->
                    <div class="action-buttons">
                        <?php if ($can_cancel): ?>
                            <form method="POST" style="flex: 1;" onsubmit="return confirm('Bileti iptal etmek istediğinizden emin misiniz? Para iadesi hesabınıza yapılacaktır.');">
                                <button type="submit" name="cancel_ticket" class="btn btn-cancel w-100">
                                    ❌ Bileti İptal Et
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <a href="bilet_pdf.php?ticket_id=<?php echo $ticket['id']; ?>" 
                           class="btn btn-pdf" style="flex: 1;" target="_blank">
                            📄 PDF İndir
                        </a>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="biletlerim.php" class="btn btn-outline-secondary">
                            ← Tüm Biletlerim
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>