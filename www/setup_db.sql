-- ============================================
-- AstaHunter Milano - Schema Database
-- Database: b8_41171820_asta
-- ============================================

CREATE TABLE IF NOT EXISTS `fonti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(100) NOT NULL,
    `url` VARCHAR(500) DEFAULT NULL,
    `tipo` ENUM('api', 'scraping', 'rss') DEFAULT 'scraping',
    `attiva` TINYINT(1) DEFAULT 1,
    `ultimo_check` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `aste` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_esterno` VARCHAR(255) DEFAULT NULL,
    `fonte_id` INT DEFAULT NULL,
    `titolo` VARCHAR(500) DEFAULT NULL,
    `descrizione` TEXT,
    `tipo_immobile` ENUM('appartamento', 'villa', 'box', 'negozio', 'ufficio', 'capannone', 'terreno', 'altro') DEFAULT 'appartamento',
    `indirizzo` VARCHAR(500) DEFAULT NULL,
    `citta` VARCHAR(100) DEFAULT 'Milano',
    `zona` VARCHAR(200) DEFAULT NULL,
    `cap` VARCHAR(10) DEFAULT NULL,
    `prezzo_base` DECIMAL(15,2) DEFAULT NULL,
    `offerta_minima` DECIMAL(15,2) DEFAULT NULL,
    `prezzo_stimato` DECIMAL(15,2) DEFAULT NULL,
    `metri_quadri` DECIMAL(10,2) DEFAULT NULL,
    `num_vani` INT DEFAULT NULL,
    `data_asta` DATE DEFAULT NULL,
    `ora_asta` TIME DEFAULT NULL,
    `tribunale` VARCHAR(200) DEFAULT NULL,
    `url_originale` VARCHAR(1000) DEFAULT NULL,
    `url_immagine` VARCHAR(1000) DEFAULT NULL,
    `latitudine` DECIMAL(10,7) DEFAULT NULL,
    `longitudine` DECIMAL(10,7) DEFAULT NULL,
    `stato` ENUM('nuovo', 'visto', 'interessante', 'archiviato') DEFAULT 'nuovo',
    `is_nuovo` TINYINT(1) DEFAULT 1,
    `hash_unico` VARCHAR(64) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_hash` (`hash_unico`),
    INDEX `idx_data_asta` (`data_asta`),
    INDEX `idx_citta` (`citta`),
    INDEX `idx_prezzo` (`prezzo_base`),
    INDEX `idx_stato` (`stato`),
    INDEX `idx_is_nuovo` (`is_nuovo`),
    FOREIGN KEY (`fonte_id`) REFERENCES `fonti`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `log_scraping` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fonte_id` INT DEFAULT NULL,
    `data_esecuzione` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `aste_trovate` INT DEFAULT 0,
    `aste_nuove` INT DEFAULT 0,
    `errore` TEXT DEFAULT NULL,
    `durata_secondi` DECIMAL(6,2) DEFAULT NULL,
    FOREIGN KEY (`fonte_id`) REFERENCES `fonti`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `alert_filtri` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(200) DEFAULT 'Default',
    `citta` VARCHAR(100) DEFAULT 'Milano',
    `zone` VARCHAR(500) DEFAULT NULL,
    `prezzo_min` DECIMAL(15,2) DEFAULT NULL,
    `prezzo_max` DECIMAL(15,2) DEFAULT NULL,
    `metri_min` DECIMAL(10,2) DEFAULT NULL,
    `tipologie` VARCHAR(500) DEFAULT NULL,
    `attivo` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserisci fonti predefinite
INSERT INTO `fonti` (`nome`, `url`, `tipo`) VALUES
('PVP - Portale Vendite Pubbliche', 'https://pvp.giustizia.it/api/v1/aste', 'api'),
('Tribunale Milano', 'https://www.tribunale.milano.it/aste', 'scraping'),
('AstaLegale', 'https://www.astalegale.it/aste-milano', 'scraping'),
('AsteGiudiziarie', 'https://www.astegiudiziarie.it/immobili/milano', 'scraping'),
('GoBetwins', 'https://www.gobetwins.it/aste/milano', 'scraping');

-- Filtro alert predefinito (Milano, tutte le tipologie, qualsiasi prezzo)
INSERT INTO `alert_filtri` (`nome`, `citta`, `prezzo_min`, `prezzo_max`) VALUES
('Default Milano', 'Milano', 0, 10000000);
