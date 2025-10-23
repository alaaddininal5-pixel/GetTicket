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
$message = '';
$error = '';

// Kullanıcı bilgilerini getir
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// SEFER SİLME
if (isset($_GET['delete'])) {
    $trip_id = (int)$_GET['delete'];
    
    // Firma kontrolü (sadece kendi seferlerini silebilir)
    $stmt = $db->prepare("SELECT company_id FROM trips WHERE id = ?");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($trip && ($_SESSION['role'] === 'admin' || $trip['company_id'] == $user['company_id'])) {
        try {
            // Satılmış bilet kontrolü
            $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = ? AND status = 'active'");
            $stmt->execute([$trip_id]);
            $ticket_count = $stmt->fetchColumn();
            
            if ($ticket_count > 0) {
                $error = '<div class="alert alert-warning">Bu sefer için ' . $ticket_count . ' aktif bilet bulunuyor. Silmek için önce biletleri iptal edin!</div>';
            } else {
                $stmt = $db->prepare("DELETE FROM trips WHERE id = ?");
                $stmt->execute([$trip_id]);
                $message = '<div class="alert alert-success">✅ Sefer başarıyla silindi!</div>';
            }
        } catch (PDOException $e) {
            $error = '<div class="alert alert-danger">Sefer silinirken hata: ' . $e->getMessage() . '</div>';
        }
    } else {
        $error = '<div class="alert alert-danger">Bu seferi silme yetkiniz yok!</div>';
    }
}

// SEFER DÜZENLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_trip'])) {
    $trip_id = (int)$_POST['trip_id'];
    $departure_city = trim($_POST['departure_city']);
    $destination_city = trim($_POST['destination_city']);
    $departure_time = trim($_POST['departure_time']);
    $arrival_time = trim($_POST['arrival_time']);
    $price = (float)$_POST['price'];
    $capacity = (int)$_POST['capacity'];
    
    // Firma kontrolü
    $stmt = $db->prepare("SELECT company_id FROM trips WHERE id = ?");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($trip && ($_SESSION['role'] === 'admin' || $trip['company_id'] == $user['company_id'])) {
        $errors = [];
        
        if (empty($departure_city)) $errors[] = "Kalkış şehri gereklidir.";
        if (empty($destination_city)) $errors[] = "Varış şehri gereklidir.";
        if ($departure_city === $destination_city) $errors[] = "Kalkış ve varış şehri aynı olamaz.";
        if ($price <= 0) $errors[] = "Fiyat 0'dan büyük olmalıdır.";
        if ($capacity <= 0) $errors[] = "Kapasite 0'dan büyük olmalıdır.";
        
        // Satılmış koltuk sayısından az kapasite verilemez
        $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = ? AND status = 'active'");
        $stmt->execute([$trip_id]);
        $sold_seats = $stmt->fetchColumn();
        
        if ($capacity < $sold_seats) {
            $errors[] = "Kapasite en az " . $sold_seats . " olmalıdır. (Satılmış bilet sayısı)";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE trips 
                    SET departure_city = ?, destination_city = ?, departure_time = ?, 
                        arrival_time = ?, price = ?, capacity = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $departure_city,
                    $destination_city,
                    $departure_time,
                    $arrival_time,
                    $price,
                    $capacity,
                    $trip_id
                ]);
                $message = '<div class="alert alert-success">✅ Sefer başarıyla güncellendi!</div>';
            } catch (PDOException $e) {
                $error = '<div class="alert alert-danger">Sefer güncellenirken hata: ' . $e->getMessage() . '</div>';
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

// Seferleri getir
$where_clause = $_SESSION['role'] === 'company_admin' ? 
    "WHERE t.company_id = {$user['company_id']}" : "";

$stmt = $db->query("
    SELECT 
        t.*,
        bc.name as company_name,
        (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status = 'active') as sold_tickets,
        (SELECT SUM(total_price) FROM tickets WHERE trip_id = t.id AND status = 'active') as revenue
    FROM trips t
    LEFT JOIN bus_companies bc ON t.company_id = bc.id
    $where_clause
    ORDER BY t.created_date DESC
");
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Türkiye şehirleri
$turkish_cities = [
    'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 
    'Antalya', 'Ardahan', 'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman', 
    'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 
    'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce', 'Edirne', 'Elazığ', 
    'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 
    'Hatay', 'Iğdır', 'Isparta', 'İstanbul', 'İzmir', 'Kahramanmaraş', 'Karabük', 
    'Karaman', 'Kars', 'Kastamonu', 'Kayseri', 'Kırıkkale', 'Kırklareli', 'Kırşehir', 
    'Kilis', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Mardin', 'Mersin', 
    'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye', 'Rize', 'Sakarya', 
    'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Şanlıurfa', 'Şırnak', 'Tekirdağ', 'Tokat', 
    'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak'
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seferlerim - GetTicket</title>
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
        
        .trip-card {
            background: white;
            border-left: 5px solid #667eea;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .trip-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }
        
        .trip-route {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
        }
        
        .route-arrow {
            color: #667eea;
            margin: 0 10px;
        }
        
        .trip-details {
            display: flex;
            gap: 30px;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
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
        
        .sales-info {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            justify-content: space-around;
            margin: 15px 0;
        }
        
        .sales-item {
            text-align: center;
        }
        
        .sales-label {
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .sales-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .trip-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-edit {
            background: #ffc107;
            border: none;
            color: #333;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-edit:hover {
            background: #e0a800;
            transform: translateY(-2px);
            color: #333;
        }
        
        .btn-delete {
            background: #dc3545;
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
            color: white;
        }
        
        .occupancy-bar {
            height: 25px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .occupancy-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            transition: width 0.5s;
        }
        
        .no-trips {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
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
                <a class="nav-link" href="sefer_ekle.php">Sefer Ekle</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>🚌 Seferlerim</h2>
            <a href="sefer_ekle.php" class="btn btn-primary">
                ➕ Yeni Sefer Ekle
            </a>
        </div>
        
        <?php echo $message; ?>
        <?php echo $error; ?>
        
        <!-- İstatistikler -->
        <?php 
        $total_trips = count($trips);
        $total_capacity = array_sum(array_column($trips, 'capacity'));
        $total_sold = array_sum(array_column($trips, 'sold_tickets'));
        $total_revenue = array_sum(array_column($trips, 'revenue'));
        ?>
        
        <div class="stats-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_trips; ?></div>
                        <div class="stat-label">Toplam Sefer</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #28a745;"><?php echo $total_sold; ?></div>
                        <div class="stat-label">Satılan Bilet</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #ffc107;"><?php echo $total_capacity; ?></div>
                        <div class="stat-label">Toplam Kapasite</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #17a2b8;">
                            <?php echo number_format($total_revenue, 0); ?> ₺
                        </div>
                        <div class="stat-label">Toplam Gelir</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sefer Listesi -->
        <?php if (empty($trips)): ?>
            <div class="no-trips">
                <div style="font-size: 5rem; opacity: 0.5;">🚌</div>
                <h3>Henüz Sefer Yok</h3>
                <p class="text-muted">Yeni sefer ekleyerek başlayın!</p>
                <a href="sefer_ekle.php" class="btn btn-primary mt-3">
                    ➕ İlk Seferimi Ekle
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($trips as $trip): 
                $occupancy = ($trip['sold_tickets'] / $trip['capacity']) * 100;
                $remaining_seats = $trip['capacity'] - $trip['sold_tickets'];
            ?>
            <div class="trip-card">
                <div class="trip-header">
                    <div>
                        <div class="trip-route">
                            <?php echo htmlspecialchars($trip['departure_city']); ?>
                            <span class="route-arrow">→</span>
                            <?php echo htmlspecialchars($trip['destination_city']); ?>
                        </div>
                        <small class="text-muted">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <?php echo htmlspecialchars($trip['company_name']); ?> • 
                            <?php endif; ?>
                            Sefer #<?php echo $trip['id']; ?>
                        </small>
                    </div>
                    <div>
                        <span class="badge bg-primary" style="font-size: 1rem; padding: 10px 20px;">
                            <?php echo number_format($trip['price'], 2); ?> TL
                        </span>
                    </div>
                </div>
                
                <div class="trip-details">
                    <div class="detail-item">
                        <div class="detail-label">Kalkış</div>
                        <div class="detail-value"><?php echo htmlspecialchars($trip['departure_time']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Varış</div>
                        <div class="detail-value"><?php echo htmlspecialchars($trip['arrival_time']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Kapasite</div>
                        <div class="detail-value"><?php echo $trip['capacity']; ?> koltuk</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Eklenme</div>
                        <div class="detail-value"><?php echo date('d.m.Y', strtotime($trip['created_date'])); ?></div>
                    </div>
                </div>
                
                <!-- Satış Bilgileri -->
                <div class="sales-info">
                    <div class="sales-item">
                        <div class="sales-label">Satılan</div>
                        <div class="sales-value"><?php echo $trip['sold_tickets']; ?></div>
                    </div>
                    <div class="sales-item">
                        <div class="sales-label">Boş Koltuk</div>
                        <div class="sales-value"><?php echo $remaining_seats; ?></div>
                    </div>
                    <div class="sales-item">
                        <div class="sales-label">Gelir</div>
                        <div class="sales-value"><?php echo number_format($trip['revenue'], 0); ?> ₺</div>
                    </div>
                    <div class="sales-item">
                        <div class="sales-label">Doluluk</div>
                        <div class="sales-value"><?php echo number_format($occupancy, 0); ?>%</div>
                    </div>
                </div>
                
                <!-- Doluluk Çubuğu -->
                <div class="occupancy-bar">
                    <div class="occupancy-fill" style="width: <?php echo $occupancy; ?>%;">
                        <?php if ($occupancy > 10): ?>
                            <?php echo $trip['sold_tickets']; ?> / <?php echo $trip['capacity']; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Aksiyon Butonları -->
                <div class="trip-actions">
                    <button type="button" class="btn btn-edit" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editModal<?php echo $trip['id']; ?>">
                        ✏️ Düzenle
                    </button>
                    
                    <?php if ($trip['sold_tickets'] == 0): ?>
                        <a href="?delete=<?php echo $trip['id']; ?>" 
                           class="btn btn-delete"
                           onclick="return confirm('Bu seferi silmek istediğinizden emin misiniz?')">
                            🗑️ Sil
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled title="Satılmış biletler var">
                            🗑️ Sil
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Düzenleme Modal -->
            <div class="modal fade" id="editModal<?php echo $trip['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Sefer Düzenle</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Kalkış Şehri</label>
                                        <input type="text" name="departure_city" class="form-control" 
                                               value="<?php echo htmlspecialchars($trip['departure_city']); ?>"
                                               list="cities-departure-<?php echo $trip['id']; ?>" required>
                                        <datalist id="cities-departure-<?php echo $trip['id']; ?>">
                                            <?php foreach ($turkish_cities as $city): ?>
                                                <option value="<?php echo $city; ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Varış Şehri</label>
                                        <input type="text" name="destination_city" class="form-control" 
                                               value="<?php echo htmlspecialchars($trip['destination_city']); ?>"
                                               list="cities-destination-<?php echo $trip['id']; ?>" required>
                                        <datalist id="cities-destination-<?php echo $trip['id']; ?>">
                                            <?php foreach ($turkish_cities as $city): ?>
                                                <option value="<?php echo $city; ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Kalkış Saati</label>
                                        <input type="time" name="departure_time" class="form-control" 
                                               value="<?php echo htmlspecialchars($trip['departure_time']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Varış Saati</label>
                                        <input type="time" name="arrival_time" class="form-control" 
                                               value="<?php echo htmlspecialchars($trip['arrival_time']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fiyat (TL)</label>
                                        <input type="number" name="price" class="form-control" 
                                               value="<?php echo $trip['price']; ?>" 
                                               min="1" step="0.01" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Kapasite</label>
                                        <input type="number" name="capacity" class="form-control" 
                                               value="<?php echo $trip['capacity']; ?>" 
                                               min="<?php echo $trip['sold_tickets']; ?>" required>
                                        <small class="text-muted">
                                            En az <?php echo $trip['sold_tickets']; ?> olmalı (satılan bilet sayısı)
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                <button type="submit" name="edit_trip" class="btn btn-primary">Kaydet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>