-- ============================================
-- WebGIS SPBU — Database Setup
-- ============================================

CREATE DATABASE IF NOT EXISTS webgis_spbu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE webgis_spbu;

CREATE TABLE IF NOT EXISTS spbu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    nomor_spbu VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'tidak',
    latitude DOUBLE NOT NULL,
    longitude DOUBLE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contoh data awal
INSERT INTO spbu (nama, nomor_spbu, status, latitude, longitude) VALUES
('SPBU Pontianak Kota', '64.761.01', 'buka 24 jam', -0.0263, 109.3425),
('SPBU Sungai Raya', '64.761.02', 'tidak', -0.0512, 109.3610),
('SPBU Ahmad Yani', '64.761.03', 'buka 24 jam', -0.0198, 109.3580);
