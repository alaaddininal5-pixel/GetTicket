<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Sadece giriş yapmış kullanıcılar erişebilir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = getDBConnection();

// Bilet ID'sini al
$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

if ($ticket_id <= 0) {
    die("Geçersiz bilet ID!");
}

// Bilet bilgilerini getir
$stmt = $db->prepare("
    SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_date, tr.departure_time, tr.arrival_time, tr.price,
           bc.name as company_name, bc.logo_path,
           u.full_name as passenger_name, u.email
    FROM tickets t
    LEFT JOIN trips tr ON t.trip_id = tr.id
    LEFT JOIN bus_companies bc ON tr.company_id = bc.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id = ? AND t.user_id = ?
");

$stmt->execute([$ticket_id, $_SESSION['user_id']]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Bilet bulunamadı veya bu bilete erişim izniniz yok!");
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet - GetTicket</title>
    <style>
        /* Yazdırma Stilleri */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            .bilet-container {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
            }
        }

        /* Ekran Stilleri */
        @media screen {
            body {
                background: #f5f5f5;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .bilet-container {
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
        }

        /* Ortak Stiller */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            background: white;
        }

        .bilet-container {
            width: 210mm;
            height: 297mm;
            background: white;
            position: relative;
            overflow: hidden;
        }

        /* Header */
        .bilet-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-align: center;
            height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .bilet-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            line-height: 1.2;
        }

        .bilet-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin: 5px 0 0 0;
        }

        .bilet-number {
            position: absolute;
            top: 15px;
            right: 30px;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        /* Ana İçerik */
        .bilet-content {
            padding: 25px 30px;
            height: calc(297mm - 160px);
            display: flex;
            flex-direction: column;
        }

        /* Firma Bilgisi */
        .company-section {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .company-logo {
            max-width: 120px;
            max-height: 50px;
            margin: 0 auto 10px;
            display: block;
            object-fit: contain;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        /* Grid Layout */
        .bilet-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            flex: 1;
        }

        /* Bölüm Stilleri */
        .section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
        }

        .section.compact {
            padding: 15px;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Bilgi Öğeleri */
        .info-item {
            margin-bottom: 12px;
        }

        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }

        /* Rota Görünümü */
        .route-section {
            grid-column: 1 / -1;
            background: white;
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            margin-bottom: 20px;
        }

        .route-display {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin: 15px 0;
        }

        .city {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            flex: 1;
            text-align: center;
        }

        .arrow {
            font-size: 18px;
            color: #667eea;
            font-weight: bold;
        }

        .route-details {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        /* Fiyat */
        .price-section {
            grid-column: 1 / -1;
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            margin: 10px 0;
        }

        .price-badge {
            font-size: 24px;
            font-weight: bold;
        }

        /* Doğrulama Kodu */
        .verification-section {
            grid-column: 1 / -1;
            background: #333;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 10px 0;
        }

        .verification-code {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            letter-spacing: 2px;
            margin: 10px 0;
            font-weight: bold;
        }

        /* Uyarılar */
        .warnings-section {
            grid-column: 1 / -1;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 10px 0;
        }

        .warning-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .warning-item:last-child {
            margin-bottom: 0;
        }

        /* İletişim */
        .contact-section {
            grid-column: 1 / -1;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-top: 10px;
            border-top: 3px solid #667eea;
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .contact-item {
            font-size: 12px;
            color: #666;
        }

        .contact-item strong {
            color: #333;
            display: block;
            margin-top: 5px;
            font-size: 13px;
        }

        /* Durum Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-cancelled {
            background: #dc3545;
            color: white;
        }

        /* Buton */
        .btn-print {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .btn-print:hover {
            transform: translateX(-50%) translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body>
    <div class="bilet-container">
        <!-- Header -->
        <div class="bilet-header">
            <div class="bilet-number">Bilet No: GT<?php echo str_pad($ticket['id'], 6, '0', STR_PAD_LEFT); ?></div>
            <h1 class="bilet-title">GETTICKET</h1>
            <p class="bilet-subtitle">Online Otobüs Bileti</p>
        </div>
        
        <!-- Ana İçerik -->
        <div class="bilet-content">
            <!-- Firma Bilgisi -->
            <div class="company-section">
                <?php if (!empty($ticket['logo_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($ticket['logo_path']); ?>" alt="Logo" class="company-logo">
                <?php endif; ?>
                <h2 class="company-name"><?php echo htmlspecialchars($ticket['company_name']); ?></h2>
            </div>

            <!-- Grid Layout -->
            <div class="bilet-grid">
                <!-- Yolcu Bilgileri -->
                <div class="section">
                    <h3 class="section-title">👤 Yolcu Bilgileri</h3>
                    <div class="info-item">
                        <div class="info-label">Ad Soyad</div>
                        <div class="info-value"><?php echo htmlspecialchars($ticket['passenger_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">E-posta</div>
                        <div class="info-value"><?php echo htmlspecialchars($ticket['email']); ?></div>
                    </div>
                </div>

                <!-- Bilet Detayları -->
                <div class="section">
                    <h3 class="section-title">🎫 Bilet Detayları</h3>
                    <div class="info-item">
                        <div class="info-label">Koltuk Numarası</div>
                        <div class="info-value"><?php echo $ticket['seat_number']; ?>. Koltuk</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Bilet Durumu</div>
                        <div class="info-value">
                            <?php echo $ticket['status'] == 'active' ? 'Aktif' : 'İptal Edildi'; ?>
                            <span class="status-badge <?php echo $ticket['status'] == 'active' ? 'status-active' : 'status-cancelled'; ?>">
                                <?php echo $ticket['status'] == 'active' ? '✓' : '✗'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Satın Alma Tarihi</div>
                        <div class="info-value"><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></div>
                    </div>
                </div>

                <!-- Rota Bilgisi -->
                <div class="route-section">
                    <h3 class="section-title">📍 Güzergah Bilgisi</h3>
                    <div class="route-display">
                        <div class="city"><?php echo htmlspecialchars($ticket['departure_city']); ?></div>
                        <div class="arrow">─────→</div>
                        <div class="city"><?php echo htmlspecialchars($ticket['destination_city']); ?></div>
                    </div>
                    <div class="route-details">
                        <div class="info-item">
                            <div class="info-label">Kalkış Tarihi</div>
                            <div class="info-value"><?php echo date('d.m.Y', strtotime($ticket['departure_date'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Kalkış Saati</div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['departure_time']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Varış Saati</div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['arrival_time']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Sefer Süresi</div>
                            <div class="info-value">
                                <?php 
                                $departure = DateTime::createFromFormat('H:i', $ticket['departure_time']);
                                $arrival = DateTime::createFromFormat('H:i', $ticket['arrival_time']);
                                $duration = $departure->diff($arrival);
                                echo $duration->format('%h saat %i dakika');
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fiyat -->
                <div class="price-section">
                    <div class="info-label" style="color: rgba(255,255,255,0.9);">Bilet Fiyatı</div>
                    <div class="price-badge"><?php echo number_format($ticket['price'], 2); ?> TL</div>
                </div>

                <!-- Doğrulama Kodu -->
                <div class="verification-section">
                    <div class="info-label" style="color: rgba(255,255,255,0.9);">Doğrulama Kodu</div>
                    <div class="verification-code">
                        GT<?php echo str_pad($ticket['id'], 6, '0', STR_PAD_LEFT); ?>-<?php echo substr(md5($ticket['id'] . $ticket['user_id']), 0, 8); ?>
                    </div>
                    <div style="font-size: 12px; opacity: 0.8; margin-top: 8px;">
                        Bu kod biletinizin doğrulanması için kullanılır
                    </div>
                </div>

                <!-- Uyarılar -->
                <div class="warnings-section">
                    <h3 class="section-title">⚠️ Önemli Uyarılar</h3>
                    <div class="warning-item">
                        <strong>❗</strong>
                        <span>Otobüse en az 15 dakika önce geliniz.</span>
                    </div>
                    <div class="warning-item">
                        <strong>❗</strong>
                        <span>Biletinizi ve kimlik belgenizi yanınızda bulundurunuz.</span>
                    </div>
                    <div class="warning-item">
                        <strong>❗</strong>
                        <span>İptal işlemleri için seferden 1 saat öncesine kadar biletlerinizi iptal edebilirsiniz.</span>
                    </div>
                </div>

                <!-- İletişim -->
                <div class="contact-section">
                    <h3 class="section-title">📞 İletişim</h3>
                    <div class="contact-info">
                        <div class="contact-item">
                            Müşteri Hizmetleri<br>
                            <strong>0850 000 00 00</strong>
                        </div>
                        <div class="contact-item">
                            E-posta<br>
                            <strong>info@getticket.com</strong>
                        </div>
                        <div class="contact-item">
                            Web Sitesi<br>
                            <strong>www.getticket.com</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yazdırma Butonu -->
    <button class="btn-print no-print" onclick="window.print()">
        🖨️ Yazdır / PDF'e Kaydet
    </button>
</body>
</html>