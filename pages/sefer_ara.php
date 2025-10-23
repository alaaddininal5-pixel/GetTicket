<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

$db = getDBConnection();

// Arama parametrelerini al
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'price_asc';

// Tüm şehirleri getir (dropdown için)
$cities_query = $db->query("
    SELECT DISTINCT departure_city FROM trips 
    UNION 
    SELECT DISTINCT destination_city FROM trips 
    ORDER BY departure_city
");
$cities = $cities_query->fetchAll(PDO::FETCH_COLUMN);

// Sefer arama sorgusu
$sql = "SELECT t.*, bc.name as company_name, bc.logo_path 
        FROM trips t 
        LEFT JOIN bus_companies bc ON t.company_id = bc.id 
        WHERE 1=1";

$params = [];

if (!empty($from)) {
    $sql .= " AND t.departure_city LIKE ?";
    $params[] = "%$from%";
}

if (!empty($to)) {
    $sql .= " AND t.destination_city LIKE ?";
    $params[] = "%$to%";
}

// Sıralama
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY t.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY t.price DESC";
        break;
    case 'time_asc':
        $sql .= " ORDER BY t.departure_time ASC";
        break;
    case 'time_desc':
        $sql .= " ORDER BY t.departure_time DESC";
        break;
    default:
        $sql .= " ORDER BY t.created_date DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toplam sefer sayısı
$total_trips = count($trips);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Ara - GetTicket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Arama Kutusu */
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .search-box .form-control,
        .search-box .form-select {
            border: 2px solid #e0e0e0;
            padding: 12px;
            border-radius: 8px;
        }
        
        .search-box .form-control:focus,
        .search-box .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Filtre Bölgesi */
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        /* Sefer Kartı */
        .trip-card {
            background: white;
            border: none;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .trip-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .company-logo {
            height: 40px;
            object-fit: contain;
            border-radius: 5px;
        }
        
        .company-name {
            font-weight: 600;
            color: #333;
            font-size: 1.1rem;
        }
        
        .route-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 20px 0;
        }
        
        .route-city {
            flex: 1;
        }
        
        .route-city h4 {
            margin: 0;
            color: #333;
            font-weight: bold;
        }
        
        .route-city small {
            color: #666;
        }
        
        .route-arrow {
            font-size: 2rem;
            color: #667eea;
        }
        
        .trip-details {
            display: flex;
            gap: 30px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .trip-detail-item {
            text-align: center;
        }
        
        .trip-detail-item i {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: #667eea;
        }
        
        .trip-detail-item strong {
            display: block;
            font-size: 1.1rem;
            color: #333;
        }
        
        .trip-detail-item small {
            color: #666;
        }
        
        .price-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
        }
        
        .price-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .btn-book {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: 600;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        
        .btn-book:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .no-trips {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .no-trips-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }
        
        .badge-capacity {
            background: #17a2b8;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .badge-capacity.low {
            background: #dc3545;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <span class="navbar-text me-3">
                                <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Çıkış</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Giriş Yap</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="register.php">Kayıt Ol</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Arama Kutusu -->
        <div class="search-box">
            <h4 class="mb-4">🔍 Sefer Ara</h4>
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Nereden</label>
                        <input type="text" name="from" class="form-control" 
                               placeholder="Şehir giriniz" 
                               value="<?php echo htmlspecialchars($from); ?>" 
                               list="cities-from">
                        <datalist id="cities-from">
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Nereye</label>
                        <input type="text" name="to" class="form-control" 
                               placeholder="Şehir giriniz" 
                               value="<?php echo htmlspecialchars($to); ?>" 
                               list="cities-to">
                        <datalist id="cities-to">
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Tarih</label>
                        <input type="date" name="date" class="form-control" 
                               value="<?php echo htmlspecialchars($date); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Sıralama</label>
                        <select name="sort" class="form-select">
                            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>
                                Fiyat (Düşük-Yüksek)
                            </option>
                            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>
                                Fiyat (Yüksek-Düşük)
                            </option>
                            <option value="time_asc" <?php echo $sort === 'time_asc' ? 'selected' : ''; ?>>
                                Kalkış (Erken-Geç)
                            </option>
                            <option value="time_desc" <?php echo $sort === 'time_desc' ? 'selected' : ''; ?>>
                                Kalkış (Geç-Erken)
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Ara</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Filtre Bar -->
        <div class="filter-bar d-flex justify-content-between align-items-center">
            <div>
                <strong><?php echo $total_trips; ?></strong> sefer bulundu
                <?php if (!empty($from) || !empty($to)): ?>
                    <span class="text-muted">
                        - <?php echo !empty($from) ? htmlspecialchars($from) : 'Tüm şehirler'; ?> 
                        → 
                        <?php echo !empty($to) ? htmlspecialchars($to) : 'Tüm şehirler'; ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($from) || !empty($to)): ?>
                <a href="sefer_ara.php" class="btn btn-sm btn-outline-secondary">Filtreyi Temizle</a>
            <?php endif; ?>
        </div>

        <!-- Sefer Listesi -->
        <?php if (empty($trips)): ?>
            <div class="no-trips">
                <div class="no-trips-icon">🚌</div>
                <h3>Sefer Bulunamadı</h3>
                <p class="text-muted">Arama kriterlerinizi değiştirerek tekrar deneyin.</p>
                <a href="sefer_ara.php" class="btn btn-primary mt-3">Tüm Seferleri Gör</a>
            </div>
        <?php else: ?>
            <?php foreach ($trips as $trip): 
                $remaining_seats = $trip['capacity'];
                $is_low_capacity = $remaining_seats <= 10;
            ?>
            <div class="trip-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($trip['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($trip['logo_path']); ?>" 
                                 alt="Logo" class="company-logo">
                        <?php endif; ?>
                        <div>
                            <div class="company-name"><?php echo htmlspecialchars($trip['company_name']); ?></div>
                            <small class="text-muted">Otobüs Firması</small>
                        </div>
                    </div>
                    <span class="badge-capacity <?php echo $is_low_capacity ? 'low' : ''; ?>">
                        <?php echo $remaining_seats; ?> Koltuk
                    </span>
                </div>

                <div class="route-info">
                    <div class="route-city text-start">
                        <h4><?php echo htmlspecialchars($trip['departure_city']); ?></h4>
                        <small>Kalkış</small>
                    </div>
                    <div class="route-arrow">→</div>
                    <div class="route-city text-end">
                        <h4><?php echo htmlspecialchars($trip['destination_city']); ?></h4>
                        <small>Varış</small>
                    </div>
                </div>

                <div class="trip-details">
                    <div class="trip-detail-item">
                        <div style="font-size: 1.5rem;">🕐</div>
                        <strong><?php echo htmlspecialchars($trip['departure_time']); ?></strong>
                        <small>Kalkış Saati</small>
                    </div>
                    <div class="trip-detail-item">
                        <div style="font-size: 1.5rem;">🕐</div>
                        <strong><?php echo htmlspecialchars($trip['arrival_time']); ?></strong>
                        <small>Varış Saati</small>
                    </div>
                    <div class="trip-detail-item">
                        <div style="font-size: 1.5rem;">💺</div>
                        <strong><?php echo $trip['capacity']; ?></strong>
                        <small>Toplam Koltuk</small>
                    </div>
                </div>

                <div class="price-section">
                    <div class="price-badge">
                        <?php echo number_format($trip['price'], 2); ?> TL
                    </div>
                    <div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="bilet_al.php?trip_id=<?php echo $trip['id']; ?>" 
                               class="btn btn-primary btn-book">
                                🎫 Bilet Al
                            </a>
                        <?php else: ?>
                            <a href="login.php?redirect=sefer_ara.php" 
                               class="btn btn-primary btn-book">
                                Giriş Yapın
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>