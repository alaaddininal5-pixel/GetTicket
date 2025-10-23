# 🎫 GetTicket - Otobüs Bileti Satın Alma Platformu

Modern web teknolojileri kullanılarak geliştirilmiş, dinamik ve çok kullanıcılı otobüs bileti satış platformu.

## 🚀 Özellikler

### 👥 Kullanıcı Rolleri
- **User (Yolcu):** Sefer arama, bilet satın alma, iptal etme
- **Firma Admin:** Sefer yönetimi (CRUD), rezervasyon takibi
- **Admin:** Sistem yönetimi, firma yönetimi, kupon yönetimi

### ✨ Temel Özellikler
- 🎫 **Bilet Satın Alma:** Koltuk seçimi ile bilet alma
- 🎟️ **Kupon Sistemi:** İndirim kuponları (Genel & Firma özel)
- ❌ **İptal Sistemi:** 1 saat kuralı ile bilet iptali ve para iadesi
- 🚌 **Sefer Yönetimi:** Firma bazlı sefer ekleme, düzenleme, silme
- 💳 **Bakiye Sistemi:** Sanal kredi ile güvenli alışveriş
- 📊 **İstatistikler:** Detaylı raporlama ve analizler
- 📋 **Sistem Logları:** Tüm işlemlerin kaydı

## 🛠️ Teknolojiler

- **Backend:** PHP 8.1
- **Veritabanı:** SQLite
- **Frontend:** HTML, CSS, Bootstrap 5
- **Container:** Docker & Docker Compose

## 📦 Kurulum

### Docker ile Çalıştırma (Önerilen)

1. Repoyu klonlayın:
```bash
git clone https://github.com/KULLANICI_ADINIZ/getticket.git
cd getticket
```

2. Docker container'ını başlatın:
```bash
docker-compose up -d
```

3. Tarayıcınızda açın:
```
http://localhost:8080
```

### Manuel Kurulum

1. Gereksinimler:
   - PHP 8.0+
   - SQLite3
   - Apache/Nginx

2. Veritabanını oluşturun:
```bash
sqlite3 database.sqlite < database.sql
```

3. Web sunucusunu başlatın

## 👤 Varsayılan Giriş Bilgileri

**Admin:**
- Email: `admin@geticket.com`
- Şifre: (database.sql'deki hash'li şifre)

**Test için yeni kullanıcı oluşturabilirsiniz:** `/pages/register.php`

## 📁 Proje Yapısı
```
getticket/
├── config/
│   └── connectdb.php            # Veritabanı bağlantısı
├── pages/
│   ├── login.php                  # Giriş sayfası
│   ├── register.php               # Kayıt sayfası
│   ├── dashboard.php              # Kullanıcı paneli
│   ├── sefer_ara.php              # Sefer arama
│   ├── bilet_al.php               # Bilet satın alma
│   ├── biletlerim.php             # Bilet listesi
│   ├── sefer_ekle.php             # Sefer ekleme (Firma Admin)
│   ├── seferlerim.php             # Sefer yönetimi (Firma Admin)
│   ├── kupon_yonetimi.php         # Kupon yönetimi
│   └── bilet_detay.php            # Bilet Detayları
|   └── bilet_pdf.php              # Bileti PDF olarak kaydetme
|   └── firma_yonetimi.php         # Firma ekle/sil
|   └── kullanıcı_yonetimi.php     # kullanıcı rol atama ve silme silme vs.
|   └── loglar.php                 # Platform İşlemlerinin kaydı
|   └── logout.php                 # Kullanıcı çıkış
|   └── profil_duzenle.php         # Kullanıcının profil işlemleri
|   └── sefer_ara.php              # Sefer arama
|   └── sistem_istatistik.php      # Sistemdeki değerlerin yansıtıldığı kısım
|   └── rezervasyonlar.php         # Rezeve biletler
|
├── logos/                     # Firma logoları
├── database.sqlite            # SQLite veritabanı
├── database.sql               # Veritabanı SQL dosyası
├── index.php                  # Ana sayfa
├── Dockerfile
├──.dockerignore
├── docker-compose.yml
└── README.MD
```

## 🎯 Kullanım

1. **Yolcu olarak:**
   - Kayıt olun
   - Sefer arayın
   - Koltuk seçin ve bilet alın
   - Kupon kodu ile indirim kazanın

2. **Firma Admin olarak:**
   - Admin tarafından oluşturulan hesapla giriş yapın
   - Yeni sefer ekleyin
   - Rezervasyonları görüntüleyin
   - Firma özel kupon oluşturun

3. **Admin olarak:**
   - Sistemdeki tüm verilere erişin
   - Firma ve kullanıcı yönetin
   - Genel kuponlar oluşturun
   - Sistem istatistiklerini görüntüleyin

## 📊 Veritabanı Tabloları

- `users` - Kullanıcı bilgileri
- `bus_companies` - Otobüs firmaları
- `trips` - Seferler
- `tickets` - Biletler
- `coupons` - İndirim kuponları
- `user_coupons` - Kupon kullanımları
- `blocked_seats` - Bloke koltuklar

## 🔒 Güvenlik

- ✅ Password hashing (PHP password_hash)
- ✅ SQL Injection koruması (Prepared Statements)
- ✅ XSS koruması (htmlspecialchars)
- ✅ Session yönetimi
- ✅ Rol bazlı yetkilendirme

## 📝 Lisans

Bu proje eğitim amaçlı geliştirilmiştir.

## 👨‍💻 Geliştirici

[Alaaddin İnal] - [alaaddininall@gmail.com]


⭐ Projeyi beğendiyseniz yıldız vermeyi unutmayın!
```

---

### 3️⃣ **.gitignore Oluştur**
```
# IDE
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Logs
*.log

# Database (opsiyonel - database.sqlite'ı da yükleyebilirsiniz)
# database.sqlite

# Environment
.env