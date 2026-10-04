-- ==============================================================================
-- PROJETO MONSTROBOLSO - 3º TRIMESTRE
-- Disciplinas: Programação Orientada a Objetos I, Linguagens para a Web II, BD II
-- Banco de Dados: jogo_pokemon (MySQL / MariaDB)
-- ==============================================================================

-- ==============================================================================
-- 1. DDL (Data Definition Language) - Criação do Banco, Tabelas e Restrições
-- ==============================================================================

DROP DATABASE IF EXISTS jogo_pokemon;
CREATE DATABASE jogo_pokemon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jogo_pokemon;

-- Tabela de Perfis/Usuários (Controle de Usuário / Troca de Perfis à la Arnaldo)
CREATE TABLE usuario (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    dinheiro INT NOT NULL DEFAULT 1000,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Tabela de Pokémons de cada Treinador
-- Atende ao requisito de variáveis mínimas (10 variáveis, incluindo HP, prioridade e velocidade)
CREATE TABLE pokemon (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nome VARCHAR(50) NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    nivel INT NOT NULL DEFAULT 5,
    hp_maximo INT NOT NULL,
    hp_atual INT NOT NULL,
    ataque INT NOT NULL,
    defesa INT NOT NULL,
    velocidade INT NOT NULL,
    prioridade INT NOT NULL DEFAULT 0,
    status_condicao VARCHAR(20) NOT NULL DEFAULT 'Normal',
    PRIMARY KEY (id),
    CONSTRAINT fk_pokemon_usuario 
        FOREIGN KEY (usuario_id) REFERENCES usuario(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabela da Loja (Poké Mart) - Catálogo dos Itens Obrigatórios
CREATE TABLE loja (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    descricao VARCHAR(150) NOT NULL,
    preco INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Tabela do Time de Batalha de cada Treinador (Team Builder)
CREATE TABLE time (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    pokemon_id INT NOT NULL,
    posicao INT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_time_usuario 
        FOREIGN KEY (usuario_id) REFERENCES usuario(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_time_pokemon 
        FOREIGN KEY (pokemon_id) REFERENCES pokemon(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Garante que o mesmo Pokémon não pode ser escalado em duplicidade no time
ALTER TABLE time ADD UNIQUE KEY uk_time_pokemon (pokemon_id);

-- Tabela de Log de Batalhas (Obrigatória: todas as batalhas devem ser registradas)
CREATE TABLE log_batalha (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    pokemon_id INT NULL,
    adversario VARCHAR(50) NOT NULL,
    resultado VARCHAR(10) NOT NULL,
    dinheiro_ganho INT NOT NULL DEFAULT 0,
    data_batalha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_log_batalha_usuario 
        FOREIGN KEY (usuario_id) REFERENCES usuario(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_log_batalha_pokemon 
        FOREIGN KEY (pokemon_id) REFERENCES pokemon(id) 
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabela de Log da Loja / Histórico de Compras
-- Atende ao requisito de "tabelas de logs de batalhas, perfis ou usuários e loja"
CREATE TABLE log_loja (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    item_id INT NOT NULL,
    pokemon_id INT NULL,
    preco_pago INT NOT NULL,
    data_compra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_log_loja_usuario 
        FOREIGN KEY (usuario_id) REFERENCES usuario(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_log_loja_item 
        FOREIGN KEY (item_id) REFERENCES loja(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_log_loja_pokemon 
        FOREIGN KEY (pokemon_id) REFERENCES pokemon(id) 
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- ==============================================================================
-- 2. DML (Data Manipulation Language) - Inserção, Atualização e Deleção
-- ==============================================================================

-- Inserção de todos os itens mínimos exigidos no Poké Mart
INSERT INTO loja (nome, descricao, preco, tipo) VALUES
-- Restauradores de Vida (HP)
('Potion', 'Recupera 20 HP', 200, 'restaurador'),
('Super Potion', 'Recupera 50 HP', 700, 'restaurador'),
('Hyper Potion', 'Recupera 200 HP', 1200, 'restaurador'),
('Max Potion', 'Recupera 100% do HP', 2500, 'restaurador'),
('Full Restore', 'Recupera 100% do HP e cura todas as condições de status', 3000, 'restaurador'),
-- Cura de Condição
('Antidote', 'Cura envenenamento', 100, 'cura'),
('Burn Heal', 'Cura queimadura', 250, 'cura'),
('Ice Heal', 'Cura congelamento', 250, 'cura'),
('Awakening', 'Cura sono', 250, 'cura'),
('Paralyze Heal', 'Cura paralisia', 200, 'cura'),
('Full Heal', 'Cura todas as condições de status', 600, 'cura'),
-- Reviver
('Revive', 'Revive um Pokémon desmaiado com metade da vida', 1500, 'reviver'),
-- Evolução (Item obrigatório para evolução conforme requisito)
('Pedra de Evolução', 'Evolui um Pokémon para o seu próximo estágio', 2100, 'evolucao');

-- Inserção de Treinadores (Controle de Usuários / Perfis)
INSERT INTO usuario (nome, dinheiro) VALUES
('Ash', 1000),
('Misty', 1000),
('Brock', 1000);

-- Inserção dos Pokémons Iniciais (compatíveis com as classes PHP do projeto)
-- 10 variáveis: nome, tipo, nivel, hp_maximo, hp_atual, ataque, defesa, velocidade, prioridade, status_condicao
INSERT INTO pokemon (usuario_id, nome, tipo, nivel, hp_maximo, hp_atual, ataque, defesa, velocidade, prioridade, status_condicao) VALUES
(1, 'Charmander', 'Fogo', 5, 39, 39, 52, 43, 65, 0, 'Normal'),
(1, 'Pikachu', 'Raio', 5, 35, 35, 55, 40, 90, 0, 'Normal'),
(2, 'Squirtle', 'Agua', 5, 44, 44, 48, 65, 43, 0, 'Normal'),
(3, 'Bulbassauro', 'Planta', 5, 45, 45, 49, 49, 45, 0, 'Normal');

-- Inserção no Time de Batalha (Ash inicia com Charmander e Pikachu)
INSERT INTO time (usuario_id, pokemon_id, posicao) VALUES
(1, 1, 1),
(1, 2, 2);

-- Exemplo de UPDATE (DML): Simulação de dano sofrido em batalha
UPDATE pokemon 
SET hp_atual = 15, status_condicao = 'Envenenado' 
WHERE id = 1;

-- Exemplo de DELETE (DML): Remoção de um Pokémon do time (necessário para demonstrar o agrupamento DML completo)
DELETE FROM time 
WHERE usuario_id = 1 AND pokemon_id = 2;

-- Reinsere o Pokémon 2 no time para os testes posteriores
INSERT INTO time (usuario_id, pokemon_id, posicao) VALUES
(1, 2, 2);


-- ==============================================================================
-- 3. VIEWS (Mínimo de 3 Views exigidas)
-- ==============================================================================

-- View 1: Dados completos do time de cada usuário para a tela de Team Builder e Batalha
CREATE OR REPLACE VIEW view_time_usuario AS
SELECT 
    t.usuario_id,
    u.nome AS usuario,
    t.posicao,
    p.id AS pokemon_id,
    p.nome AS pokemon,
    p.tipo,
    p.nivel,
    p.hp_atual,
    p.hp_maximo,
    p.ataque,
    p.defesa,
    p.velocidade,
    p.prioridade,
    p.status_condicao
FROM time t
INNER JOIN usuario u ON u.id = t.usuario_id
INNER JOIN pokemon p ON p.id = t.pokemon_id
ORDER BY t.usuario_id, t.posicao;

-- View 2: Histórico detalhado de batalhas com informações do treinador e Pokémon
CREATE OR REPLACE VIEW view_historico_batalhas AS
SELECT 
    l.id AS log_id,
    l.usuario_id,
    u.nome AS usuario,
    p.id AS pokemon_id,
    p.nome AS pokemon,
    l.adversario,
    l.resultado,
    l.dinheiro_ganho,
    l.data_batalha
FROM log_batalha l
INNER JOIN usuario u ON u.id = l.usuario_id
LEFT JOIN pokemon p ON p.id = l.pokemon_id
ORDER BY l.data_batalha DESC;

-- View 3: Resumo estatístico do desempenho de cada treinador (Vitórias, Batalhas, Dinheiro ganho)
-- Demonstra agregação (COUNT, SUM) e agrupamento (GROUP BY) para Banco de Dados II
CREATE OR REPLACE VIEW view_estatisticas_treinadores AS
SELECT 
    u.id AS usuario_id,
    u.nome AS usuario,
    u.dinheiro,
    COUNT(l.id) AS total_batalhas,
    SUM(CASE WHEN LOWER(l.resultado) = 'vitoria' THEN 1 ELSE 0 END) AS vitorias,
    SUM(CASE WHEN LOWER(l.resultado) = 'derrota' THEN 1 ELSE 0 END) AS derrotas,
    COALESCE(SUM(l.dinheiro_ganho), 0) AS total_dinheiro_ganho
FROM usuario u
LEFT JOIN log_batalha l ON u.id = l.usuario_id
GROUP BY u.id, u.nome, u.dinheiro;


-- ==============================================================================
-- 4. TRIGGERS (Gatilhos de Integridade e Regras de Negócio)
-- ==============================================================================

DELIMITER //

-- Trigger 1: Vitória em batalha gera dinheiro automaticamente para o banco do usuário
-- Conecta log_batalha ao saldo do usuário (Requisito: "Ganhar batalhas geram dinheiro para o banco")
DROP TRIGGER IF EXISTS trigger_dinheiro_vitoria //
CREATE TRIGGER trigger_dinheiro_vitoria
AFTER INSERT ON log_batalha
FOR EACH ROW
BEGIN
    IF LOWER(NEW.resultado) = 'vitoria' AND NEW.dinheiro_ganho > 0 THEN
        UPDATE usuario
        SET dinheiro = dinheiro + NEW.dinheiro_ganho
        WHERE id = NEW.usuario_id;
    END IF;
END //

-- Trigger 2: Garante a integridade dos pontos de vida do Pokémon
-- O HP atual nunca pode ultrapassar o hp_maximo nem ser menor que 0
DROP TRIGGER IF EXISTS trigger_limite_hp //
CREATE TRIGGER trigger_limite_hp
BEFORE UPDATE ON pokemon
FOR EACH ROW
BEGIN
    IF NEW.hp_atual > NEW.hp_maximo THEN
        SET NEW.hp_atual = NEW.hp_maximo;
    END IF;
    IF NEW.hp_atual < 0 THEN
        SET NEW.hp_atual = 0;
    END IF;
END //

DELIMITER ;


-- ==============================================================================
-- 5. DTL (Data Transaction Language) - Transações da Compra e Uso na Loja
-- ==============================================================================

-- Teste 1: Transação confirmada com sucesso (COMMIT)
-- Ash compra uma Potion (R$ 200), cura o Charmander machucado e registra no log da loja
START TRANSACTION;

-- 1. Debita o valor do saldo do jogador
UPDATE usuario 
SET dinheiro = dinheiro - 200 
WHERE id = 1 AND dinheiro >= 200;

-- 2. Cura 20 HP do Pokémon
UPDATE pokemon 
SET hp_atual = hp_atual + 20 
WHERE id = 1;

-- 3. Registra a compra no log da loja
INSERT INTO log_loja (usuario_id, item_id, pokemon_id, preco_pago)
VALUES (1, 1, 1, 200);

COMMIT;


-- Teste 2: Transação cancelada / desfeita (ROLLBACK)
-- Simulação de tentativa de compra de Hyper Potion (1200) onde o usuário cancela ou ocorre falha
START TRANSACTION;

UPDATE usuario 
SET dinheiro = dinheiro - 1200 
WHERE id = 1;

UPDATE pokemon 
SET hp_atual = hp_atual + 200 
WHERE id = 1;

-- Desfaz todas as alterações desta transação
ROLLBACK;


-- ==============================================================================
-- 6. DQL (Data Query Language) - Consultas de Validação e Testes dos Requisitos
-- ==============================================================================

-- 1. Validação de Perfis e Saldo do Ash após as transações (Deve ser 800)
SELECT id, nome, dinheiro FROM usuario WHERE id = 1;

-- 2. Validação do HP do Charmander (Era 15, curou 20 com Potion -> Deve ser 35)
SELECT id, nome, hp_atual, hp_maximo, status_condicao FROM pokemon WHERE id = 1;

-- 3. Teste do Trigger 2 (Limite de HP): Tentativa de forçar HP para 999 (Deve travar em 39)
UPDATE pokemon SET hp_atual = 999 WHERE id = 1;
SELECT id, nome, hp_atual, hp_maximo FROM pokemon WHERE id = 1;

-- 4. Teste do Trigger 1 (Crédito de Vitória): Ash vence o Geodude e ganha 300
INSERT INTO log_batalha (usuario_id, pokemon_id, adversario, resultado, dinheiro_ganho)
VALUES (1, 1, 'Geodude', 'vitoria', 300);

-- O saldo do Ash deve ter subido de 800 para 1100 automaticamente via Trigger
SELECT id, nome, dinheiro FROM usuario WHERE id = 1;

-- 5. Teste da View 1: Montagem do Time
SELECT * FROM view_time_usuario WHERE usuario_id = 1;

-- 6. Teste da View 2: Histórico de Batalhas
SELECT * FROM view_historico_batalhas;

-- 7. Teste da View 3: Estatísticas Agrupadas dos Treinadores
SELECT * FROM view_estatisticas_treinadores;

-- 8. Validação do Log da Loja
SELECT * FROM log_loja;
