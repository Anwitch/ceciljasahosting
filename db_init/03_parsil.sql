-- ============================================
-- WebGIS Manajemen Jalan & Parsil Tanah
-- Database Schema — Format GeoJSON
-- ============================================

CREATE DATABASE IF NOT EXISTS polyline_polygon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE polyline_polygon;

-- ============================================
-- Tabel Data Jalan (LineString GeoJSON)
-- ============================================
CREATE TABLE IF NOT EXISTS jalan (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nama_jalan   VARCHAR(255) NOT NULL,
    status_jalan ENUM('Jalan Nasional', 'Jalan Provinsi', 'Jalan Kabupaten') NOT NULL,
    panjang_meter DOUBLE DEFAULT 0,
    geojson      LONGTEXT NOT NULL COMMENT 'GeoJSON Feature object with geometry type LineString',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- Tabel Data Parsil Tanah (Polygon GeoJSON)
-- ============================================
CREATE TABLE IF NOT EXISTS parsil_tanah (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    nomor_sertifikat   VARCHAR(100),
    status_kepemilikan ENUM('SHM', 'HGB', 'HGU', 'HP') NOT NULL,
    luas_meter2        DOUBLE DEFAULT 0,
    geojson            LONGTEXT NOT NULL COMMENT 'GeoJSON Feature object with geometry type Polygon',
    nama_pemilik       VARCHAR(255),
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- Tabel Laporan Kerusakan Jalan (Point GeoJSON)
-- ============================================
CREATE TABLE IF NOT EXISTS laporan_kerusakan (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    jalan_id         INT DEFAULT 0 COMMENT 'FK ke tabel jalan (0 jika tidak terdeteksi)',
    nama_jalan       VARCHAR(255) DEFAULT 'Tidak Diketahui',
    status_jalan     VARCHAR(50) DEFAULT '',
    nama_pelapor     VARCHAR(255) DEFAULT 'Anonim',
    kategori_rusak   ENUM('Ringan', 'Sedang', 'Berat') NOT NULL,
    tanggal_laporan  DATE NOT NULL,
    keterangan       TEXT,
    foto_url         VARCHAR(500) DEFAULT NULL COMMENT 'Path foto kerusakan yang diupload',
    geojson          LONGTEXT NOT NULL COMMENT 'GeoJSON Feature Point [lng, lat]',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jalan_id (jalan_id),
    INDEX idx_tanggal (tanggal_laporan),
    INDEX idx_kategori (kategori_rusak)
);

-- ============================================
-- Sample Data Jalan
-- Catatan: GeoJSON menggunakan [lng, lat] (bukan [lat, lng])
-- ============================================
INSERT INTO jalan (nama_jalan, status_jalan, panjang_meter, geojson) VALUES
(
  'Jalan Ahmad Yani',
  'Jalan Nasional',
  1250.5,
  '{
    "type": "Feature",
    "properties": {
      "nama_jalan": "Jalan Ahmad Yani",
      "status_jalan": "Jalan Nasional",
      "panjang_meter": 1250.5
    },
    "geometry": {
      "type": "LineString",
      "coordinates": [
        [109.3300, -0.0220],
        [109.3340, -0.0235],
        [109.3375, -0.0250],
        [109.3410, -0.0263]
      ]
    }
  }'
),
(
  'Jalan Gajah Mada',
  'Jalan Provinsi',
  870.3,
  '{
    "type": "Feature",
    "properties": {
      "nama_jalan": "Jalan Gajah Mada",
      "status_jalan": "Jalan Provinsi",
      "panjang_meter": 870.3
    },
    "geometry": {
      "type": "LineString",
      "coordinates": [
        [109.3280, -0.0280],
        [109.3330, -0.0270],
        [109.3380, -0.0260]
      ]
    }
  }'
),
(
  'Jalan Pahlawan',
  'Jalan Kabupaten',
  540.7,
  '{
    "type": "Feature",
    "properties": {
      "nama_jalan": "Jalan Pahlawan",
      "status_jalan": "Jalan Kabupaten",
      "panjang_meter": 540.7
    },
    "geometry": {
      "type": "LineString",
      "coordinates": [
        [109.3420, -0.0300],
        [109.3450, -0.0290],
        [109.3480, -0.0280]
      ]
    }
  }'
);

-- ============================================
-- Sample Data Parsil Tanah
-- Polygon: koordinat pertama dan terakhir harus sama (closed ring)
-- ============================================
INSERT INTO parsil_tanah (nomor_sertifikat, status_kepemilikan, luas_meter2, geojson, nama_pemilik) VALUES
(
  'SHM-2024-001',
  'SHM',
  450.0,
  '{
    "type": "Feature",
    "properties": {
      "nomor_sertifikat": "SHM-2024-001",
      "status_kepemilikan": "SHM",
      "luas_meter2": 450.0,
      "nama_pemilik": "Budi Santoso"
    },
    "geometry": {
      "type": "Polygon",
      "coordinates": [[
        [109.3355, -0.0255],
        [109.3370, -0.0255],
        [109.3370, -0.0245],
        [109.3355, -0.0245],
        [109.3355, -0.0255]
      ]]
    }
  }',
  'Budi Santoso'
),
(
  'HGB-2024-002',
  'HGB',
  620.5,
  '{
    "type": "Feature",
    "properties": {
      "nomor_sertifikat": "HGB-2024-002",
      "status_kepemilikan": "HGB",
      "luas_meter2": 620.5,
      "nama_pemilik": "PT. Maju Jaya"
    },
    "geometry": {
      "type": "Polygon",
      "coordinates": [[
        [109.3395, -0.0270],
        [109.3415, -0.0270],
        [109.3415, -0.0258],
        [109.3395, -0.0258],
        [109.3395, -0.0270]
      ]]
    }
  }',
  'PT. Maju Jaya'
),
(
  'HGU-2024-003',
  'HGU',
  1200.0,
  '{
    "type": "Feature",
    "properties": {
      "nomor_sertifikat": "HGU-2024-003",
      "status_kepemilikan": "HGU",
      "luas_meter2": 1200.0,
      "nama_pemilik": "CV. Agro Lestari"
    },
    "geometry": {
      "type": "Polygon",
      "coordinates": [[
        [109.3310, -0.0285],
        [109.3335, -0.0285],
        [109.3335, -0.0268],
        [109.3310, -0.0268],
        [109.3310, -0.0285]
      ]]
    }
  }',
  'CV. Agro Lestari'
);
