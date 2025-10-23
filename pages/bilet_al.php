<?php
session_start();
require_once __DIR__ . '/../config/connectdb.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=bilet_al.php");
    exit();
}

$db = getDBConnection();
$message = '';
$error = '';

// Trip ID kontrolü
if (!isset($_GET['trip_id'])) {
    header("Location: sefer_ara.php");
    exit();
}

$trip_id = (int)$_GET['trip_id'];

// Sefer bilgilerini getir
$stmt = $db->prepare("
    SELECT t.*, bc.name as company_name, bc.logo_path 
    FROM trips t 
    LEFT JOIN bus_companies bc ON t.company_id = bc.id 
    WHERE t.id = ?
");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    header("Location: sefer_ara.php");
    exit();
}

// Kullanıcı bilgilerini getir
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Dolu koltukları getir
$stmt = $db->prepare("SELECT seat_number FROM tickets WHERE trip_id = ? AND status = 'active'");
$stmt->execute([$trip_id]);
$booked_seats = $stmt->fetchAll(PDO::FETCH_COLUMN);

// AJAX Kupon Kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_coupon'])) {
    header('Content-Type: application/json');
    
    $coupon_code = strtoupper(trim($_POST['coupon_code']));
    
    if (empty($coupon_code)) {
        echo json_encode(['success' => false, 'message' => 'Kupon kodu giriniz!']);
        exit();
    }
    
    // Kuponu getir
    $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ?");
    $stmt->execute([$coupon_code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz kupon kodu!']);
        exit();
    }
    
    // Süre kontrolü
    if ($coupon['expire_date'] < date('Y-m-d H:i:s')) {
        echo json_encode(['success' => false, 'message' => 'Kuponun süresi dolmuş!']);
        exit();
    }
    
    // Kullanım limiti kontrolü
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_coupons WHERE coupon_id = ?");
    $stmt->execute([$coupon['id']]);
    $used_count = $stmt->fetchColumn();
    
    if ($used_count >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Kupon kullanım limiti dolmuş!']);
        exit();
    }
    
    // Kullanıcı daha önce bu kuponu kullanmış mı?
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_coupons WHERE coupon_id = ? AND user_id = ?");
    $stmt->execute([$coupon['id'], $_SESSION['user_id']]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Bu kuponu daha önce kullandınız!']);
        exit();
    }
    
    // İndirim hesapla
    $discount_amount = ($trip['price'] * $coupon['discount']) / 100;
    $discounted_price = $trip['price'] - $discount_amount;
    
    echo json_encode([
        'success' => true,
        'message' => 'Kupon başarıyla uygulandı!',
        'discount' => $coupon['discount'],
        'discount_amount' => number_format($discount_amount, 2),
        'discounted_price' => number_format($discounted_price, 2),
        'coupon_id' => $coupon['id']
    ]);
    exit();
}

// Koltuk seçimi ve bilet alma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_ticket'])) {
    $selected_seat = (int)$_POST['seat_number'];
    $coupon_id = !empty($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : null;
    $final_price = $trip['price'];
    
    // Kupon varsa kontrollerini yap ve fiyatı güncelle
    if ($coupon_id) {
        $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
        $stmt->execute([$coupon_id]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coupon && $coupon['expire_date'] >= date('Y-m-d H:i:s')) {
            // İndirim uygula
            $discount_amount = ($trip['price'] * $coupon['discount']) / 100;
            $final_price = $trip['price'] - $discount_amount;
        } else {
            $coupon_id = null; // Geçersiz kupon
        }
    }
    
    // Validasyonlar
    if (empty($selected_seat)) {
        $error = "Lütfen bir koltuk seçin!";
    } elseif (in_array($selected_seat, $booked_seats)) {
        $error = "Bu koltuk zaten dolu!";
    } elseif ($user['balance'] < $final_price) {
        $error = "Yetersiz bakiye! Bakiyeniz: " . number_format($user['balance'], 2) . " TL";
    } else {
        try {
            $db->beginTransaction();
            
            // Bilet oluştur
            $stmt = $db->prepare("
                INSERT INTO tickets (trip_id, user_id, seat_number, status, total_price) 
                VALUES (?, ?, ?, 'active', ?)
            ");
            $stmt->execute([$trip_id, $_SESSION['user_id'], $selected_seat, $final_price]);
            $ticket_id = $db->lastInsertId();
            
            // Bakiyeyi düş
            $new_balance = $user['balance'] - $final_price;
            $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $_SESSION['user_id']]);
            
            // Session'ı güncelle
            $_SESSION['balance'] = $new_balance;
            
            // Koltuğu bloke et
            $stmt = $db->prepare("INSERT INTO blocked_seats (ticket_id, seat_number) VALUES (?, ?)");
            $stmt->execute([$ticket_id, $selected_seat]);
            
            // Kupon kullanıldıysa kaydet
            if ($coupon_id) {
                $stmt = $db->prepare("INSERT INTO user_coupons (coupon_id, user_id) VALUES (?, ?)");
                $stmt->execute([$coupon_id, $_SESSION['user_id']]);
            }
            
            $db->commit();
            
            // Başarılı, bilet sayfasına yönlendir
            header("Location: bilet_detay.php?ticket_id=" . $ticket_id . "&success=1");
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Bilet alınırken hata oluştu: " . $e->getMessage();
        }
    }
}

// Koltuk düzeni
$total_seats = $trip['capacity'];
$seats_per_row = 4;
$rows = ceil($total_seats / $seats_per_row);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Al - GetTicket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .trip-info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .seat-selection-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        /* Otobüs Düzeni */
        .bus-layout {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px 20px;
            position: relative;
        }
        
        .bus-front {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            font-weight: bold;
        }
        
        .seat-row {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .seat-group {
            display: flex;
            gap: 10px;
        }
        
        .aisle {
            width: 40px;
        }
        
        .seat {
            width: 50px;
            height: 50px;
            border: 2px solid #667eea;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s;
            position: relative;
        }
        
        .seat:hover:not(.booked) {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .seat.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        .seat.booked {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
            cursor: not-allowed;
        }
        
        .seat.booked:hover {
            transform: none;
        }
        
        /* Kupon Bölümü */
        .coupon-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .coupon-input-group {
            display: flex;
            gap: 10px;
        }
        
        .coupon-success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .coupon-error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        /* Açıklamalar */
        .seat-legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .legend-seat {
            width: 30px;
            height: 30px;
            border-radius: 5px;
        }
        
        .legend-seat.available {
            background: white;
            border: 2px solid #667eea;
        }
        
        .legend-seat.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .legend-seat.booked {
            background: #dc3545;
        }
        
        /* Özet Bölümü */
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .summary-item:last-child {
            border-bottom: none;
            font-size: 1.3rem;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .original-price {
            text-decoration: line-through;
            opacity: 0.7;
            font-size: 0.9rem;
        }
        
        .btn-buy {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px;
            font-weight: bold;
            font-size: 1.1rem;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-buy:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(255,255,255,0.3);
            color: #764ba2;
        }
        
        .btn-buy:disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            transform: none;
        }
        
        .balance-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
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
                <span class="navbar-text me-3">
                    💰 Bakiye: <strong id="user-balance"><?php echo number_format($user['balance'], 2); ?> TL</strong>
                </span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Mesajlar -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <strong>Hata!</strong> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Sefer Bilgileri -->
        <div class="trip-info-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php if (!empty($trip['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($trip['logo_path']); ?>" 
                                 alt="Logo" style="height: 50px; object-fit: contain;">
                        <?php endif; ?>
                        <div>
                            <h3 class="mb-0"><?php echo htmlspecialchars($trip['company_name']); ?></h3>
                            <small class="text-muted">Otobüs Firması</small>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-4">
                        <div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($trip['departure_city']); ?></h4>
                            <small class="text-muted">Kalkış: <?php echo htmlspecialchars($trip['departure_time']); ?></small>
                        </div>
                        <div style="font-size: 2rem; color: #667eea;">→</div>
                        <div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($trip['destination_city']); ?></h4>
                            <small class="text-muted">Varış: <?php echo htmlspecialchars($trip['arrival_time']); ?></small>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <div style="font-size: 2rem; font-weight: bold; color: #28a745;" id="display-price">
                        <?php echo number_format($trip['price'], 2); ?> TL
                    </div>
                    <small class="text-muted">Bilet Fiyatı</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Koltuk Seçimi -->
            <div class="col-lg-8">
                <div class="seat-selection-card">
                    <h4 class="mb-4">🚌 Koltuk Seçimi</h4>
                    
                    <form method="POST" id="ticketForm">
                        <input type="hidden" name="seat_number" id="selected_seat" value="">
                        <input type="hidden" name="coupon_id" id="coupon_id" value="">
                        
                        <div class="bus-layout">
                            <div class="bus-front">
                                🚗 ŞOFÖR
                            </div>
                            
                            <?php
                            $seat_number = 1;
                            for ($row = 0; $row < $rows; $row++):
                            ?>
                                <div class="seat-row">
                                    <div class="seat-group">
                                        <?php for ($i = 0; $i < 2 && $seat_number <= $total_seats; $i++, $seat_number++): 
                                            $is_booked = in_array($seat_number, $booked_seats);
                                        ?>
                                            <div class="seat <?php echo $is_booked ? 'booked' : ''; ?>" 
                                                 data-seat="<?php echo $seat_number; ?>"
                                                 <?php echo $is_booked ? 'title="Dolu"' : 'title="Koltuk ' . $seat_number . '"'; ?>>
                                                <?php echo $seat_number; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <div class="aisle"></div>
                                    
                                    <div class="seat-group">
                                        <?php for ($i = 0; $i < 2 && $seat_number <= $total_seats; $i++, $seat_number++): 
                                            $is_booked = in_array($seat_number, $booked_seats);
                                        ?>
                                            <div class="seat <?php echo $is_booked ? 'booked' : ''; ?>" 
                                                 data-seat="<?php echo $seat_number; ?>"
                                                 <?php echo $is_booked ? 'title="Dolu"' : 'title="Koltuk ' . $seat_number . '"'; ?>>
                                                <?php echo $seat_number; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        
                        <!-- Açıklamalar -->
                        <div class="seat-legend">
                            <div class="legend-item">
                                <div class="legend-seat available"></div>
                                <span>Boş</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-seat selected"></div>
                                <span>Seçili</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-seat booked"></div>
                                <span>Dolu</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Özet ve Ödeme -->
            <div class="col-lg-4">
                <!-- Kupon Bölümü -->
                <div class="coupon-section">
                    <h5 class="mb-3">🎟️ İndirim Kuponu</h5>
                    <div class="coupon-input-group">
                        <input type="text" id="coupon_code" class="form-control" 
                               placeholder="Kupon kodunu girin" style="text-transform: uppercase;">
                        <button type="button" class="btn btn-primary" id="applyCouponBtn">
                            Uygula
                        </button>
                    </div>
                    <div id="coupon-message"></div>
                </div>
                
                <div class="summary-card">
                    <h4 class="mb-4">📋 Rezervasyon Özeti</h4>
                    
                    <div class="summary-item">
                        <span>Güzergah:</span>
                        <strong><?php echo htmlspecialchars($trip['departure_city']); ?> - <?php echo htmlspecialchars($trip['destination_city']); ?></strong>
                    </div>
                    
                    <div class="summary-item">
                        <span>Kalkış:</span>
                        <strong><?php echo htmlspecialchars($trip['departure_time']); ?></strong>
                    </div>
                    
                    <div class="summary-item">
                        <span>Seçili Koltuk:</span>
                        <strong id="display_seat">-</strong>
                    </div>
                    
                    <div class="summary-item">
                        <span>Yolcu:</span>
                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                    </div>
                    
                    <div id="discount-info" style="display: none;">
                        <div class="summary-item">
                            <span>Normal Fiyat:</span>
                            <span class="original-price" id="original-price"><?php echo number_format($trip['price'], 2); ?> TL</span>
                        </div>
                        <div class="summary-item">
                            <span>İndirim:</span>
                            <strong style="color: #ffeb3b;" id="discount-percent">-</strong>
                        </div>
                    </div>
                    
                    <div class="summary-item">
                        <span>Toplam Tutar:</span>
                        <strong id="final-price"><?php echo number_format($trip['price'], 2); ?> TL</strong>
                    </div>
                    
                    <button type="submit" form="ticketForm" name="buy_ticket" 
                            class="btn btn-buy w-100 mt-3" id="buyButton" disabled>
                        🎫 Bileti Satın Al
                    </button>
                    
                    <div id="balance-warning-dynamic" style="display: none;" class="balance-warning mt-3">
                        <strong>⚠️ Yetersiz Bakiye!</strong>
                        <p class="mb-0 mt-2">İndirimli fiyat bile bakiyenizi aşıyor.</p>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="sefer_ara.php" class="btn btn-outline-secondary">
                        ← Sefer Listesine Dön
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const seats = document.querySelectorAll('.seat:not(.booked)');
            const selectedSeatInput = document.getElementById('selected_seat');
            const displaySeat = document.getElementById('display_seat');
            const buyButton = document.getElementById('buyButton');
            const userBalance = <?php echo $user['balance']; ?>;
            const originalPrice = <?php echo $trip['price']; ?>;
            let currentPrice = originalPrice;
            let appliedCoupon = null;
            
            // Koltuk seçimi
            seats.forEach(seat => {
                seat.addEventListener('click', function() {
                    seats.forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    const seatNumber = this.getAttribute('data-seat');
                    selectedSeatInput.value = seatNumber;
                    displaySeat.textContent = 'Koltuk ' + seatNumber;
                    
                    // Butonu aktifleştir
                    checkBalance();
                });
            });
            
            function checkBalance() {
                const balanceWarning = document.getElementById('balance-warning-dynamic');
                if (selectedSeatInput.value && userBalance >= currentPrice) {
                    buyButton.disabled = false;
                    balanceWarning.style.display = 'none';
                } else if (selectedSeatInput.value && userBalance < currentPrice) {
                    buyButton.disabled = true;
                    balanceWarning.style.display = 'block';
                }
            }
            
            // Kupon uygulama
            document.getElementById('applyCouponBtn').addEventListener('click', function() {
                const couponCode = document.getElementById('coupon_code').value.trim();
                const messageDiv = document.getElementById('coupon-message');
                const applyBtn = this;
                
                if (!couponCode) {
                    messageDiv.innerHTML = '<div class="coupon-error mt-3">Lütfen kupon kodu girin!</div>';
                    return;
                }
                
                applyBtn.disabled = true;
                applyBtn.textContent = 'Kontrol ediliyor...';
                
                // AJAX ile kupon kontrolü
                const formData = new FormData();
                formData.append('check_coupon', '1');
                formData.append('coupon_code', couponCode);
                
                fetch('bilet_al.php?trip_id=<?php echo $trip_id; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        appliedCoupon = data;
                        currentPrice = parseFloat(data.discounted_price.replace(',', '.'));
                        
                        // UI güncelle
                        messageDiv.innerHTML = '<div class="coupon-success">' + 
                            '✅ ' + data.message + ' (-%' + data.discount + ')' +
                            '</div>';
                        
                        document.getElementById('coupon_id').value = data.coupon_id;
                        document.getElementById('discount-info').style.display = 'block';
                        document.getElementById('discount-percent').textContent = '-%' + data.discount + ' (-' + data.discount_amount + ' TL)';
                        document.getElementById('final-price').textContent = data.discounted_price + ' TL';
                        document.getElementById('display-price').innerHTML = 
                            '<span class="original-price" style="font-size: 1.2rem;">' + originalPrice.toFixed(2) + ' TL</span><br>' +
                            data.discounted_price + ' TL';
                        
                        // Kupon inputunu devre dışı bırak
                        document.getElementById('coupon_code').disabled = true;
                        applyBtn.textContent = '✓ Uygulandı';
                        applyBtn.classList.replace('btn-primary', 'btn-success');
                        
                        // Bakiye kontrolü
                        checkBalance();
                    } else {
                        messageDiv.innerHTML = '<div class="coupon-error mt-3">❌ ' + data.message + '</div>';
                        applyBtn.disabled = false;
                        applyBtn.textContent = 'Uygula';
                    }
                })
                .catch(error => {
                    messageDiv.innerHTML = '<div class="coupon-error mt-3">Bir hata oluştu!</div>';
                    console.error('Error:', error);
                    applyBtn.disabled = false;
                    applyBtn.textContent = 'Uygula';
                });
            });
            
            // Form gönderme kontrolü
            document.getElementById('ticketForm').addEventListener('submit', function(e) {
                if (!selectedSeatInput.value) {
                    e.preventDefault();
                    alert('Lütfen bir koltuk seçin!');
                    return false;
                }
                
                if (userBalance < currentPrice) {
                    e.preventDefault();
                    alert('Yetersiz bakiye! Lütfen bakiye yükleyin.');
                    return false;
                }
                
                // Onay mesajı
                const seatNum = selectedSeatInput.value;
                const confirmMsg = 'Koltuk ' + seatNum + ' için ' + currentPrice.toFixed(2) + ' TL ödeyeceksiniz. Onaylıyor musunuz?';
                if (!confirm(confirmMsg)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>