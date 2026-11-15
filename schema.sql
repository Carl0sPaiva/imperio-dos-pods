USE if0_43418592_vape_club;

CREATE TABLE IF NOT EXISTS configuracoes (
    chave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- WhatsApp padrão e senha inicial do painel ('admin123')
INSERT IGNORE INTO configuracoes (chave, valor) VALUES 
('whatsapp', '5563999999999'),
('admin_senha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO categorias (slug, nome) VALUES 
('pods', 'Pods Descartáveis'),
('kits', 'Pod Systems / Kits'),
('juices', 'Juices Premium'),
('coils', 'Coils & Acessórios'),
('drinks', 'Bebidas');

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    preco_antigo DECIMAL(10,2) NULL,
    descricao TEXT NULL,
    imagem VARCHAR(255) NULL,
    badge VARCHAR(50) NULL,
    esgotado BOOLEAN DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS produto_sabores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    sabor VARCHAR(100) NOT NULL,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO produtos (categoria_id, nome, preco, preco_antigo, descricao, imagem, badge, esgotado) VALUES 
(1, 'Ignite V80 (8000 Puffs)', 120.00, 140.00, 'Bateria recarregável via USB-C com 8000 puxadas e visor em LED.', 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=600&q=80', 'Mais Vendido', 0),
(1, 'Elf Bar BC10000 (10000 Puffs)', 135.00, 150.00, 'Display digital de bateria e e-liquid com tecnologia Quaq Mesh.', 'https://images.unsplash.com/photo-1539185441755-769473a23570?auto=format&fit=crop&w=600&q=80', 'Lançamento', 0),
(1, 'Lost Mary OS5000', 95.00, 110.00, 'Design ergonômico, bateria de 650mAh com recarga rápida Type-C.', 'https://images.unsplash.com/photo-1527016016007-a75d7da6363d?auto=format&fit=crop&w=600&q=80', NULL, 0),
(2, 'Vaporesso XROS 3 Mini Kit', 190.00, 220.00, 'Bateria de 1000mAh integrada e ajuste de fluxo de ar de precisão.', 'https://images.unsplash.com/photo-1527016016007-a75d7da6363d?auto=format&fit=crop&w=600&q=80', 'Recomendado', 0),
(3, 'Juice Naked 100 NicSalt 30ml', 85.00, 95.00, 'Linha importada premium. Teor de nicotina: 35mg / 50mg.', 'https://images.unsplash.com/photo-1527016016007-a75d7da6363d?auto=format&fit=crop&w=600&q=80', 'Importado', 0),
(4, 'Cartucho Vaporesso XROS (Pack c/ 4)', 130.00, 150.00, 'Resistências de 0.6Ω, 0.8Ω ou 1.2Ω com tecnologia SSS Vaporesso.', 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=600&q=80', NULL, 0);

INSERT INTO produto_sabores (produto_id, sabor) VALUES 
(1, 'Icy Mint'), (1, 'Watermelon Ice'), (1, 'Grape Ice'), (1, 'Blue Razz Ice'),
(2, 'Strawberry Ice Cream'), (2, 'Blueberry Ice'), (2, 'Peach Mango'),
(3, 'Spearmint'), (3, 'Watermelon Lemonade'), (3, 'Juicy Peach'),
(4, 'Black / Preto Fosco'), (4, 'Space Grey'), (4, 'Rose Pink'),
(5, 'Hawaiian POG'), (5, 'Lava Flow'), (5, 'Brain Freeze (Strawberry Pom)'),
(6, '0.6 Ohms (Sub-Ohm RDL)'), (6, '0.8 Ohms (Mesh MTL)'), (6, '1.2 Ohms (NicSalt MTL)');