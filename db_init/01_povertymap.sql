CREATE DATABASE IF NOT EXISTS webgis_poverty_mapping
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE webgis_poverty_mapping;

CREATE TABLE users (
  id            INT           AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)   NOT NULL UNIQUE,
  password      VARCHAR(255)  NOT NULL,
  nama_lengkap  VARCHAR(150)  NOT NULL,
  role          ENUM('admin','surveyor','pemangku') NOT NULL DEFAULT 'surveyor',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin default — password: admin123
-- Hash di-generate dengan password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO users (username, password, nama_lengkap, role) VALUES
  ('admin', '$2y$10$Xb5FU6uo67rVETXAlSYJ..wn8IudyYwGzUbyMe8yQ1TLFlvt.S9F6', 'Administrator', 'admin');

CREATE TABLE rumah_ibadah (
  id                INT           AUTO_INCREMENT PRIMARY KEY,
  nama              VARCHAR(200)  NOT NULL,
  jenis             ENUM('masjid','gereja','pura','klenteng','vihara','lain') NOT NULL DEFAULT 'masjid',
  alamat            TEXT,
  pic               VARCHAR(150)  NOT NULL,
  no_wa             VARCHAR(30)   DEFAULT NULL,
  radius            INT           NOT NULL DEFAULT 500,   -- meter
  lat               DOUBLE        NOT NULL,
  lng               DOUBLE        NOT NULL,
  foto_path         VARCHAR(255)  DEFAULT NULL,
  surveyor_id       INT           DEFAULT NULL,
  status            ENUM('pending','verified','rejected') NOT NULL DEFAULT 'verified',
  alasan_penolakan  TEXT          DEFAULT NULL,
  kecamatan         VARCHAR(100)  DEFAULT NULL,
  created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ri_surveyor FOREIGN KEY (surveyor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warga_miskin (
  id                INT           AUTO_INCREMENT PRIMARY KEY,
  nama_kk           VARCHAR(200)  NOT NULL,
  nik               VARCHAR(16)   DEFAULT NULL,
  jumlah_anggota    INT           NOT NULL DEFAULT 1,
  keterangan        TEXT,
  alamat            TEXT          DEFAULT NULL,
  kecamatan         VARCHAR(100)  DEFAULT NULL,
  lat               DOUBLE        NOT NULL,
  lng               DOUBLE        NOT NULL,
  ibadah_id         INT           DEFAULT NULL,           -- NULL = belum terjangkau
  foto_path         VARCHAR(255)  DEFAULT NULL,
  surveyor_id       INT           DEFAULT NULL,
  status            ENUM('pending','verified','rejected') NOT NULL DEFAULT 'verified',
  alasan_penolakan  TEXT          DEFAULT NULL,
  bantuan_diterima  ENUM('sembako','beasiswa','modal','tunai','tidak_ada') NOT NULL DEFAULT 'tidak_ada',
  created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_warga_nik    UNIQUE (nik),
  CONSTRAINT fk_wm_ibadah    FOREIGN KEY (ibadah_id)   REFERENCES rumah_ibadah(id) ON DELETE SET NULL,
  CONSTRAINT fk_wm_surveyor  FOREIGN KEY (surveyor_id) REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id           INT           AUTO_INCREMENT PRIMARY KEY,
  user_id      INT           NOT NULL,
  aksi         VARCHAR(100)  NOT NULL,
  target_type  VARCHAR(50)   DEFAULT NULL,
  target_id    INT           DEFAULT NULL,
  data_before  JSON          DEFAULT NULL,
  data_after   JSON          DEFAULT NULL,
  ip_address   VARCHAR(45)   DEFAULT NULL,
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_ri_status      ON rumah_ibadah(status);
CREATE INDEX idx_ri_kecamatan   ON rumah_ibadah(kecamatan);
CREATE INDEX idx_wm_status      ON warga_miskin(status);
CREATE INDEX idx_wm_kecamatan   ON warga_miskin(kecamatan);
CREATE INDEX idx_wm_ibadah      ON warga_miskin(ibadah_id);
CREATE INDEX idx_audit_user     ON audit_logs(user_id);
CREATE INDEX idx_audit_aksi     ON audit_logs(aksi);
CREATE INDEX idx_audit_created  ON audit_logs(created_at);