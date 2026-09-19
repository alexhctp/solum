CREATE DATABASE IF NOT EXISTS solum
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE solum;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('admin', 'tecnico', 'proprietario') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    INDEX idx_usuarios_perfil (perfil)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tecnico_cliente (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tecnico_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tecnico_cliente (tecnico_id, cliente_id),
    INDEX idx_tecnico_cliente_cliente (cliente_id),
    CONSTRAINT fk_tecnico_cliente_tecnico
        FOREIGN KEY (tecnico_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_tecnico_cliente_cliente
        FOREIGN KEY (cliente_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS propriedades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proprietario_id BIGINT UNSIGNED NULL,
    nome VARCHAR(150) NOT NULL,
    produtor VARCHAR(150) NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    uf CHAR(2) NOT NULL,
    area_total DECIMAL(12,2) NOT NULL,
    cultura_principal VARCHAR(100) NULL,
    PRIMARY KEY (id),
    INDEX idx_propriedades_cidade_uf (cidade, uf),
    INDEX idx_propriedades_proprietario (proprietario_id),
    CONSTRAINT fk_propriedade_proprietario
        FOREIGN KEY (proprietario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_propriedades_uf CHECK (uf REGEXP '^[A-Z]{2}$'),
    CONSTRAINT chk_propriedades_area_total CHECK (area_total > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS talhoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    propriedade_id BIGINT UNSIGNED NOT NULL,
    identificacao VARCHAR(100) NOT NULL,
    area_ha DECIMAL(12,2) NOT NULL,
    textura_solo ENUM('argiloso', 'medio', 'arenoso') NOT NULL,
    historico_manejo TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_talho_propriedade_identificacao (propriedade_id, identificacao),
    INDEX idx_talho_propriedade (propriedade_id),
    CONSTRAINT fk_talho_propriedade
        FOREIGN KEY (propriedade_id) REFERENCES propriedades (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_talho_area_ha CHECK (area_ha > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS analises_solo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    talhao_id BIGINT UNSIGNED NOT NULL,
    data_coleta DATE NOT NULL,
    camada_cm VARCHAR(20) NOT NULL,
    extrator_p ENUM('Mehlich-1', 'Resina') NOT NULL,
    ph_agua DECIMAL(4,2) NOT NULL,
    al_cmolc DECIMAL(8,3) NOT NULL DEFAULT 0,
    ca_cmolc DECIMAL(8,3) NOT NULL DEFAULT 0,
    mg_cmolc DECIMAL(8,3) NOT NULL DEFAULT 0,
    k_mgdm3 DECIMAL(10,2) NOT NULL DEFAULT 0,
    p_mgdm3 DECIMAL(10,2) NOT NULL DEFAULT 0,
    mo_gdm3 DECIMAL(10,2) NOT NULL DEFAULT 0,
    h_al_cmolc DECIMAL(8,3) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_analises_talhao_data (talhao_id, data_coleta),
    INDEX idx_analises_data_coleta (data_coleta),
    CONSTRAINT fk_analise_talhao
        FOREIGN KEY (talhao_id) REFERENCES talhoes (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_analise_camada CHECK (camada_cm REGEXP '^[0-9]+-[0-9]+$'),
    CONSTRAINT chk_analise_ph CHECK (ph_agua BETWEEN 0 AND 14),
    CONSTRAINT chk_analise_al CHECK (al_cmolc >= 0),
    CONSTRAINT chk_analise_ca CHECK (ca_cmolc >= 0),
    CONSTRAINT chk_analise_mg CHECK (mg_cmolc >= 0),
    CONSTRAINT chk_analise_k CHECK (k_mgdm3 >= 0),
    CONSTRAINT chk_analise_p CHECK (p_mgdm3 >= 0),
    CONSTRAINT chk_analise_mo CHECK (mo_gdm3 >= 0),
    CONSTRAINT chk_analise_h_al CHECK (h_al_cmolc >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recomendacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    analise_id BIGINT UNSIGNED NOT NULL,
    v_percent_atual DECIMAL(5,2) NOT NULL,
    v_percent_alvo DECIMAL(5,2) NOT NULL,
    nc_ton_ha DECIMAL(10,3) NOT NULL,
    relacao_ca_mg DECIMAL(8,3) NULL,
    parecer_tecnico TEXT NOT NULL,
    data_geracao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_recomendacoes_analise (analise_id),
    INDEX idx_recomendacoes_data_geracao (data_geracao),
    CONSTRAINT fk_recomendacao_analise
        FOREIGN KEY (analise_id) REFERENCES analises_solo (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_recomendacao_v_atual CHECK (v_percent_atual BETWEEN 0 AND 100),
    CONSTRAINT chk_recomendacao_v_alvo CHECK (v_percent_alvo BETWEEN 0 AND 100),
    CONSTRAINT chk_recomendacao_nc CHECK (nc_ton_ha >= 0),
    CONSTRAINT chk_recomendacao_relacao CHECK (relacao_ca_mg IS NULL OR relacao_ca_mg >= 0)
) ENGINE=InnoDB;

-- Senha de todos os usuarios de teste: Solum@123
INSERT INTO usuarios (id, nome, email, senha, perfil) VALUES
    (1, 'Admin Solum', 'admin@solum.test', '$2y$12$Vio2oCWGQwOoxzVPp.lvuOiPiEH5UBYUndL81LCxK7BHeuXcrkTqG', 'admin'),
    (2, 'Tecnico Ana Souza', 'tecnico@solum.test', '$2y$12$Vio2oCWGQwOoxzVPp.lvuOiPiEH5UBYUndL81LCxK7BHeuXcrkTqG', 'tecnico'),
    (3, 'Carlos Oliveira', 'carlos@solum.test', '$2y$12$Vio2oCWGQwOoxzVPp.lvuOiPiEH5UBYUndL81LCxK7BHeuXcrkTqG', 'proprietario'),
    (4, 'Marina Costa', 'marina@solum.test', '$2y$12$Vio2oCWGQwOoxzVPp.lvuOiPiEH5UBYUndL81LCxK7BHeuXcrkTqG', 'proprietario')
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    senha = VALUES(senha),
    perfil = VALUES(perfil);

INSERT INTO tecnico_cliente (tecnico_id, cliente_id) VALUES
    (2, 3),
    (2, 4)
ON DUPLICATE KEY UPDATE tecnico_id = VALUES(tecnico_id);

INSERT INTO propriedades (id, proprietario_id, nome, produtor, cidade, uf, area_total, cultura_principal) VALUES
    (1, 3, 'Fazenda Santa Rita', 'Carlos Oliveira', 'Varginha', 'MG', 42.50, 'cafe'),
    (2, 4, 'Sitio Boa Esperanca', 'Marina Costa', 'Alfenas', 'MG', 18.00, 'milho')
ON DUPLICATE KEY UPDATE
    proprietario_id = VALUES(proprietario_id),
    nome = VALUES(nome),
    produtor = VALUES(produtor),
    cidade = VALUES(cidade),
    uf = VALUES(uf),
    area_total = VALUES(area_total),
    cultura_principal = VALUES(cultura_principal);
