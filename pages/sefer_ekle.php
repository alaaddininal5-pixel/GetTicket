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

// Türkiye'nin popüler şehirleri
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
    'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Şanlıurha', 'Şırnak', 'Tekirdağ', 'Tokat', 
    'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak'
];

// Sefer ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trip'])) {
    $company_id = $_SESSION['role'] === 'company_admin' ? $user['company_id'] : (int)$_POST['company_id'];
    $departure_city = trim($_POST['departure_city']);
    $destination_city = trim($_POST['destination_city']);
    $departure_date = trim($_POST['departure_date']);
    $departure_time = trim($_POST['departure_time']);
    $arrival_time = trim($_POST['arrival_time']);
    $price = (float)$_POST['price'];
    $capacity = (int)$_POST['capacity'];
    
    // Validasyon
    $errors = [];
    
    if (empty($company_id)) $errors[] = "Firma seçimi gereklidir.";
    if (empty($departure_city)) $errors[] = "Kalkış şehri gereklidir.";
    if (empty($destination_city)) $errors[] = "Varış şehri gereklidir.";
    if ($departure_city === $destination_city) $errors[] = "Kalkış ve varış şehri aynı olamaz.";
    if (empty($departure_date)) $errors[] = "Kalkış tarihi gereklidir.";
    if (empty($departure_time)) $errors[] = "Kalkış saati gereklidir.";
    if (empty($arrival_time)) $errors[] = "Varış saati gereklidir.";
    if ($price <= 0) $errors[] = "Fiyat 0'dan büyük olmalıdır.";
    if ($capacity <= 0 || $capacity > 60) $errors[] = "Kapasite 1-60 arası olmalıdır.";
    
    if (empty($errors)) {
        try {
            // Önce tabloya departure_date kolonunu ekleyelim
            try {
                $db->exec("ALTER TABLE trips ADD COLUMN departure_date DATE");
            } catch (Exception $e) {
                // Kolon zaten varsa hata verme
            }
            
            $stmt = $db->prepare("
                INSERT INTO trips (company_id, departure_city, destination_city, departure_date, departure_time, arrival_time, price, capacity) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $company_id,
                $departure_city,
                $destination_city,
                $departure_date,
                $departure_time,
                $arrival_time,
                $price,
                $capacity
            ]);
            
            $message = '<div class="alert alert-success">✅ Sefer başarıyla eklendi!</div>';
            
            // Formu temizle
            $_POST = [];
            
        } catch (PDOException $e) {
            $error = '<div class="alert alert-danger">Sefer eklenirken hata: ' . $e->getMessage() . '</div>';
        }
    } else {
        $error = '<div class="alert alert-danger"><ul class="mb-0">';
        foreach ($errors as $err) {
            $error .= '<li>' . $err . '</li>';
        }
        $error .= '</ul></div>';
    }
}

// Son eklenen seferler
$recent_limit = $_SESSION['role'] === 'company_admin' ? 
    "WHERE t.company_id = {$user['company_id']}" : "";
$stmt = $db->query("
    SELECT t.*, bc.name as company_name 
    FROM trips t 
    LEFT JOIN bus_companies bc ON t.company_id = bc.id 
    $recent_limit
    ORDER BY t.created_date DESC 
    LIMIT 10
");
$recent_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Ekle - GetTicket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .form-card h4 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            padding: 12px;
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: 600;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .company-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .trip-item {
            background: white;
            border: none;
            border-left: 4px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .trip-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .trip-route {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .trip-details {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .badge-price {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .required-star {
            color: #dc3545;
        }
        
        .date-time-group {
            display: flex;
            gap: 15px;
        }
        
        .date-time-group .form-control {
            flex: 1;
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
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Sefer Ekleme Formu -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h4>🚌 Yeni Sefer Ekle</h4>
                    
                    <?php echo $message; ?>
                    <?php echo $error; ?>
                    
                    <?php if ($_SESSION['role'] === 'company_admin' && $user_company): ?>
                        <div class="company-badge">
                            <strong>Firma:</strong> <?php echo htmlspecialchars($user_company['name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="mb-3">
                            <label class="form-label">Firma <span class="required-star">*</span></label>
                            <select name="company_id" class="form-select" required>
                                <option value="">Firma Seçin</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>">
                                        <?php echo htmlspecialchars($company['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kalkış Şehri <span class="required-star">*</span></label>
                                <input type="text" name="departure_city" class="form-control" 
                                       list="cities-departure" placeholder="Şehir seçin" required>
                                <datalist id="cities-departure">
                                    <?php foreach ($turkish_cities as $city): ?>
                                        <option value="<?php echo $city; ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Varış Şehri <span class="required-star">*</span></label>
                                <input type="text" name="destination_city" class="form-control" 
                                       list="cities-destination" placeholder="Şehir seçin" required>
                                <datalist id="cities-destination">
                                    <?php foreach ($turkish_cities as $city): ?>
                                        <option value="<?php echo $city; ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kalkış Tarihi <span class="required-star">*</span></label>
                            <input type="date" name="departure_date" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kalkış Saati <span class="required-star">*</span></label>
                                <input type="time" name="departure_time" class="form-control" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Varış Saati <span class="required-star">*</span></label>
                                <input type="time" name="arrival_time" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bilet Fiyatı (TL) <span class="required-star">*</span></label>
                                <input type="number" name="price" class="form-control" 
                                       min="1" step="0.01" placeholder="150.00" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Koltuk Kapasitesi <span class="required-star">*</span></label>
                                <select name="capacity" class="form-select" required>
                                    <option value="">Kapasite Seçin</option>
                                    <option value="20">20 Koltuk</option>
                                    <option value="30">30 Koltuk</option>
                                    <option value="40" selected>40 Koltuk</option>
                                    <option value="45">45 Koltuk</option>
                                    <option value="50">50 Koltuk</option>
                                    <option value="54">54 Koltuk</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_trip" class="btn btn-primary btn-add w-100">
                            ✅ Sefer Ekle
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Son Eklenen Seferler -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h4>📋 Son Eklenen Seferler</h4>
                    
                    <?php if (empty($recent_trips)): ?>
                        <div class="text-center text-muted py-5">
                            <div style="font-size: 3rem;">🚌</div>
                            <p>Henüz sefer eklenmemiş</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_trips as $trip): ?>
                        <div class="trip-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="trip-route">
                                        <?php echo htmlspecialchars($trip['departure_city']); ?> 
                                        → 
                                        <?php echo htmlspecialchars($trip['destination_city']); ?>
                                    </div>
                                    <div class="trip-details">
                                        <span> <?php echo htmlspecialchars($trip['company_name']); ?></span>
                                        <?php if (isset($trip['departure_date'])): ?>
                                            <span>📅 <?php echo date('d.m.Y', strtotime($trip['departure_date'])); ?></span>
                                        <?php endif; ?>
                                        <span>🕐 <?php echo htmlspecialchars($trip['departure_time']); ?></span>
                                        <span>💺 <?php echo $trip['capacity']; ?> koltuk</span>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Eklenme: <?php echo date('d.m.Y H:i', strtotime($trip['created_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge-price"><?php echo number_format($trip['price'], 2); ?> TL</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="seferlerim.php" class="btn btn-outline-primary">
                                Tüm Seferleri Gör
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Hızlı İstatistikler -->
                <div class="form-card">
                    <h5>📊 İstatistikler</h5>
                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <h3 class="text-primary"><?php echo count($recent_trips); ?></h3>
                            <small class="text-muted">Toplam Sefer</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-success">
                                <?php 
                                $total_capacity = array_sum(array_column($recent_trips, 'capacity'));
                                echo $total_capacity; 
                                ?>
                            </h3>
                            <small class="text-muted">Toplam Koltuk</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-info">
                                <?php 
                                $avg_price = !empty($recent_trips) ? 
                                    number_format(array_sum(array_column($recent_trips, 'price')) / count($recent_trips), 0) : 0;
                                echo $avg_price; 
                                ?>
                            </h3>
                            <small class="text-muted">Ort. Fiyat (TL)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>