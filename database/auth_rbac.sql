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

ALTER TABLE propriedades
    ADD COLUMN proprietario_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN cultura_principal VARCHAR(100) NULL AFTER area_total,
    ADD INDEX idx_propriedades_proprietario (proprietario_id),
    ADD CONSTRAINT fk_propriedade_proprietario
        FOREIGN KEY (proprietario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;

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
