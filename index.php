<?php
session_start();
require_once __DIR__ . '/config/connectdb.php';

// Popüler güzergahları getir
$db = getDBConnection();
$stmt = $db->query("
    SELECT t.*, bc.name as company_name, bc.logo_path 
    FROM trips t 
    LEFT JOIN bus_companies bc ON t.company_id = bc.id 
    ORDER BY t.created_date DESC 
    LIMIT 6
");
$popular_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toplam istatistikler
$stats = [
    'companies' => $db->query("SELECT COUNT(*) FROM bus_companies")->fetchColumn(),
    'trips' => $db->query("SELECT COUNT(*) FROM trips")->fetchColumn(),
    'users' => $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GetTicket - Online Otobüs Bileti</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
       
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
        }
        
        .hero-section h1 {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .hero-section p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.9;
        }
        
        /* Arama Kutusu */
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        
        .search-box label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
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
        
        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: 600;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* Özellikler */
        .features-section {
            padding: 60px 0;
            background: #f8f9fa;
        }
        
        .feature-card {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        /* Popüler Güzergahlar */
        .trips-section {
            padding: 60px 0;
        }
        
        .trip-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            overflow: hidden;
            height: 100%;
        }
        
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .trip-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            font-weight: 600;
        }
        
        .price-badge {
            background: #28a745;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        /* İstatistikler */
        .stats-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px 0;
            margin: 60px 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        /* Footer */
        .footer {
            background: #212529;
            color: white;
            padding: 40px 0 20px;
            margin-top: 60px;
        }
        
        .footer a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .footer a:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <span style="font-size: 1.5rem;">🎫 GetTicket</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#seferler">Seferler</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#hakkimizda">Hakkımızda</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="pages/dashboard.php">
                                <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pages/logout.php">Çıkış</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="pages/login.php">Giriş Yap</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="pages/register.php">Kayıt Ol</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1>Türkiye'nin En Kolay Bilet Platformu</h1>
                    <p>Yüzlerce güzergah, tek tıkla bilet! Hemen aramaya başla.</p>
                </div>
                <div class="col-lg-6">
                    <div class="search-box">
                        <form action="pages/sefer_ara.php" method="GET">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label>Nereden</label>
                                    <input type="text" name="from" class="form-control" placeholder="İstanbul" required>
                                </div>
                                <div class="col-md-6">
                                    <label>Nereye</label>
                                    <input type="text" name="to" class="form-control" placeholder="Ankara" required>
                                </div>
                                <div class="col-md-6">
                                    <label>Tarih</label>
                                    <input type="date" name="date" class="form-control" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label>Yolcu Sayısı</label>
                                    <select name="passengers" class="form-select">
                                        <option value="1">1 Kişi</option>
                                        <option value="2">2 Kişi</option>
                                        <option value="3">3 Kişi</option>
                                        <option value="4">4 Kişi</option>
                                        <option value="5">5+ Kişi</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-search w-100">
                                        🔍 Sefer Ara
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Özellikler -->
    <div class="features-section">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">Neden GetTicket?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">⚡</div>
                        <h4>Hızlı ve Kolay</h4>
                        <p class="text-muted">Dakikalar içinde biletinizi alın, karmaşık işlemlerden kurtulun.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h4>Güvenli Ödeme</h4>
                        <p class="text-muted">Bakiye sistemi ile güvenli alışveriş yapın, kart bilgisi gerekmez.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h4>Mobil Uyumlu</h4>
                        <p class="text-muted">Her cihazdan kolayca erişin, istediğiniz yerden bilet alın.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- İstatistikler -->
    <div class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['companies']; ?></div>
                        <div class="stat-label">Otobüs Firması</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['trips']; ?></div>
                        <div class="stat-label">Aktif Sefer</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['users']; ?></div>
                        <div class="stat-label">Mutlu Yolcu</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Popüler Güzergahlar -->
    <div class="trips-section" id="seferler">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">Popüler Güzergahlar</h2>
            
            <?php if (empty($popular_trips)): ?>
                <div class="text-center text-muted">
                    <p style="font-size: 3rem;">🚌</p>
                    <h5>Henüz sefer bulunmuyor</h5>
                    <p>Yakında birçok güzergahta hizmet vermeye başlayacağız!</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($popular_trips as $trip): ?>
                    <div class="col-md-4">
                        <div class="card trip-card">
                            <div class="card-header">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span><?php echo htmlspecialchars($trip['company_name']); ?></span>
                                    <?php if (!empty($trip['logo_path'])): ?>
                                        <img src="./<?php echo htmlspecialchars($trip['logo_path']); ?>" 
                                             alt="Logo" style="height: 30px; object-fit: contain;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h5 class="mb-1">
                                        <strong><?php echo htmlspecialchars($trip['departure_city']); ?></strong>
                                        <span class="mx-2">→</span>
                                        <strong><?php echo htmlspecialchars($trip['destination_city']); ?></strong>
                                    </h5>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <small class="text-muted">Kalkış</small>
                                        <div><strong><?php echo htmlspecialchars($trip['departure_time']); ?></strong></div>
                                    </div>
                                    <div class="text-center">
                                        <small class="text-muted">Varış</small>
                                        <div><strong><?php echo htmlspecialchars($trip['arrival_time']); ?></strong></div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">Koltuk</small>
                                        <div><strong><?php echo $trip['capacity']; ?></strong></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="price-badge"><?php echo number_format($trip['price'], 2); ?> TL</span>
                                    <a href="pages/sefer_ara.php?trip_id=<?php echo $trip['id']; ?>" 
                                       class="btn btn-primary">Bilet Al</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-5">
                <a href="pages/sefer_ara.php" class="btn btn-outline-primary btn-lg">
                    Tüm Seferleri Gör
                </a>
            </div>
        </div>
    </div>

   
    <div class="features-section" id="hakkimizda">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">3 Adımda Bilet Al</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">🔍</div>
                        <h4>1. Ara</h4>
                        <p class="text-muted">Gideceğiniz yeri ve tarihi seçin, uygun seferleri listeleyin.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">💺</div>
                        <h4>2. Seç</h4>
                        <p class="text-muted">Koltuğunuzu seçin, yolcu bilgilerinizi girin.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">✅</div>
                        <h4>3. Al</h4>
                        <p class="text-muted">Ödemenizi tamamlayın, biletiniz hazır!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">🎫 GetTicket</h5>
                    <p class="text-muted">Türkiye'nin en kolay online otobüs bileti platformu.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h6 class="fw-bold mb-3">Hızlı Linkler</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#seferler">Seferler</a></li>
                        <li class="mb-2"><a href="#hakkimizda">Hakkımızda</a></li>
                        <li class="mb-2"><a href="pages/login.php">Giriş Yap</a></li>
                        <li class="mb-2"><a href="pages/register.php">Kayıt Ol</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h6 class="fw-bold mb-3">İletişim</h6>
                    <p class="mb-2">📧 info@getticket.com</p>
                    <p class="mb-2">📞 0850 000 00 00</p>
                    <p class="mb-2">📍 Şanlıurfa, Türkiye</p>
                </div>
            </div>
            <hr style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center">
                <p class="mb-0 text-muted">&copy; 2025 GetTicket. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>