# 🗺️ WebGIS Manajemen Jalan & Parsil Tanah

Sistem Informasi Geografis berbasis web untuk manajemen data spasial jalan dan parsil tanah.

---

## 📁 Struktur File

```
webgis/
├── index.html          ← Halaman utama WebGIS
├── database.sql        ← Schema & sample data MySQL
├── README.md           ← Dokumentasi ini
└── api/
    ├── config.php      ← Konfigurasi koneksi database
    ├── jalan.php       ← API CRUD Data Jalan
    └── parsil.php      ← API CRUD Data Parsil Tanah
```

---

## ⚙️ Cara Install

### 1. Persyaratan
- **Web Server**: Apache / Nginx dengan PHP 7.4+
- **Database**: MySQL 5.7+ atau MariaDB 10.3+
- **Browser**: Chrome, Firefox, Edge (modern)

### 2. Setup Database

```sql
-- Jalankan di MySQL / phpMyAdmin
SOURCE database.sql;
```

Atau import file `database.sql` melalui phpMyAdmin.

### 3. Konfigurasi Database

Edit file `api/config.php`:

```php
define('DB_HOST', 'localhost');   // Host database
define('DB_USER', 'root');        // Username MySQL
define('DB_PASS', '');            // Password MySQL
define('DB_NAME', 'webgis_db');   // Nama database
```

### 4. Deploy ke Web Server

**Menggunakan XAMPP/WAMP/LAMP:**
```bash
# Copy folder webgis/ ke:
# Windows XAMPP: C:/xampp/htdocs/webgis/
# Linux LAMP:    /var/www/html/webgis/
```

**Akses aplikasi:**
```
http://localhost/polyline_polygon/index.html
```

---

## 🌟 Fitur Utama

### 🛣️ Manajemen Data Jalan (Polyline)
| Fitur | Deskripsi |
|-------|-----------|
| **Tambah Jalan** | Gambar polyline di peta atau input koordinat manual |
| **Status Jalan** | Nasional (merah), Provinsi (oranye), Kabupaten (hijau) |
| **Panjang Otomatis** | Dihitung menggunakan `L.latLng.distanceTo()` dari LeafletJS |
| **Edit & Hapus** | CRUD lengkap dengan konfirmasi hapus |
| **Drag Titik** | Titik koordinat bisa diubah langsung di tabel |

### 🏘️ Manajemen Parsil Tanah (Polygon)
| Fitur | Deskripsi |
|-------|-----------|
| **Tambah Parsil** | Gambar polygon di peta atau input koordinat manual |
| **Status Kepemilikan** | SHM, HGB, HGU, HP — masing-masing warna berbeda |
| **Luas Otomatis** | Dihitung dengan formula Spherical Excess dari LeafletJS |
| **Data Lengkap** | Nama parsil, nomor sertifikat, nama pemilik, keterangan |

### 🗺️ Fitur Peta
- **Basemap**: OpenStreetMap + Esri Satellite (switchable)
- **Draw Mode**: Klik peta untuk menggambar fitur spasial
- **Popup Interaktif**: Klik fitur di peta untuk melihat detail
- **Zoom to Feature**: Langsung zoom ke lokasi dari sidebar
- **Koordinat Real-time**: Tampil di pojok kiri bawah

---

## 🎨 Kode Warna

### Jalan
| Status | Warna | Hex |
|--------|-------|-----|
| Jalan Nasional | 🔴 Merah | `#E63946` |
| Jalan Provinsi | 🟠 Oranye | `#F4A261` |
| Jalan Kabupaten | 🟢 Hijau | `#2A9D8F` |

### Parsil Tanah
| Status | Warna | Hex |
|--------|-------|-----|
| SHM | 🔵 Biru | `#4361EE` |
| HGB | 🟣 Ungu | `#7209B7` |
| HGU | 🌸 Pink | `#F72585` |
| HP | 🩵 Cyan | `#4CC9F0` |

---

## 📡 API Endpoints

### Jalan
```
GET    api/jalan.php           → Ambil semua data jalan
GET    api/jalan.php?id={id}   → Ambil data jalan by ID
POST   api/jalan.php           → Tambah data jalan baru
PUT    api/jalan.php?id={id}   → Update data jalan
DELETE api/jalan.php?id={id}   → Hapus data jalan
```

### Parsil Tanah
```
GET    api/parsil.php           → Ambil semua data parsil
GET    api/parsil.php?id={id}   → Ambil data parsil by ID
POST   api/parsil.php           → Tambah data parsil baru
PUT    api/parsil.php?id={id}   → Update data parsil
DELETE api/parsil.php?id={id}   → Hapus data parsil
```

### Contoh Request Body (POST/PUT Jalan)
```json
{
  "nama_jalan": "Jalan Ahmad Yani",
  "status_jalan": "Jalan Nasional",
  "panjang_meter": 1250.5,
  "koordinat": [
    [0.0032, -109.3245],
    [0.0051, -109.3180],
    [0.0068, -109.3110]
  ],
  "keterangan": "Jalan utama kota"
}
```

### Contoh Request Body (POST/PUT Parsil)
```json
{
  "nama_parsil": "Kavling A-001",
  "nomor_sertifikat": "SHM-2024-001",
  "status_kepemilikan": "SHM",
  "luas_meter2": 450.0,
  "koordinat": [
    [0.0010, -109.3280],
    [0.0010, -109.3260],
    [0.0020, -109.3260],
    [0.0020, -109.3280]
  ],
  "nama_pemilik": "Budi Santoso",
  "keterangan": "Kavling perumahan"
}
```

---

## 💡 Cara Penggunaan

### Menambah Data Jalan Baru
1. Klik tab **"Data Jalan"** di topbar
2. Klik **"✚ Tambah Data Baru"** di sidebar
3. **Opsi A**: Klik **"✏️ Gambar di Peta"** → klik titik-titik di peta → klik **"⏹ Selesai Gambar"**
4. **Opsi B**: Isi koordinat manual di tabel (lat/lng double)
5. Isi nama jalan, pilih status, tambah keterangan
6. Panjang jalan otomatis terhitung oleh LeafletJS
7. Klik **"💾 Simpan Data"**

### Menambah Data Parsil Baru
1. Klik tab **"Parsil Tanah"** di topbar
2. Klik **"✚ Tambah Data Baru"**
3. Gambar polygon di peta (min. 3 titik) atau isi koordinat manual
4. Luas tanah otomatis terhitung oleh LeafletJS
5. Isi nama parsil, nomor sertifikat, status kepemilikan, nama pemilik
6. Klik **"💾 Simpan Data"**

### Edit Data
1. Klik **"✏️ Edit"** pada kartu data di sidebar, atau klik fitur di peta → klik Edit di popup
2. Ubah data yang diperlukan, termasuk koordinat
3. Klik **"💾 Simpan Data"**

---

## 🔧 Mode Demo

Jika server PHP belum terkonfigurasi, aplikasi otomatis berjalan dalam **Mode Demo** dengan data sample. Semua operasi CRUD tetap berfungsi secara lokal di browser (tidak tersimpan ke database).

---

## 📚 Teknologi

- **Frontend**: HTML5, CSS3 (Custom Properties), Vanilla JavaScript
- **Peta**: LeafletJS 1.9.4 + Leaflet.draw 1.0.4
- **Backend**: PHP 7.4+ (REST API)
- **Database**: MySQL / MariaDB
- **Basemap**: OpenStreetMap, Esri World Imagery
- **Font**: Plus Jakarta Sans, DM Mono (Google Fonts)
