<?php
require_once __DIR__ . '/../../../Autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verificações de segurança e acesso
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

$adminsPermitidos = require __DIR__ . '/../../../config/Administradores.php';

if (!in_array($_SESSION['usuario'] ?? '', $adminsPermitidos, true)) {
    header('Location: index.php?rota=menu');
    exit;
}

// 3. Consultas ao banco de dados
$pdo = Conexao::getConnection();

function buscarLogs(PDO $pdo, string $sql): array {
    $consulta = $pdo->query($sql);
    return $consulta->fetchAll(PDO::FETCH_ASSOC);
}

$sqlBatalha = "
    SELECT
        log_batalha.id,
        usuario.nome AS usuario,
        pokemon.nome AS pokemon,
        log_batalha.adversario,
        log_batalha.resultado,
        log_batalha.dinheiro_ganho,
        log_batalha.data_batalha
    FROM log_batalha
    JOIN usuario ON usuario.id = log_batalha.usuario_id
    LEFT JOIN pokemon ON pokemon.id = log_batalha.pokemon_id
    ORDER BY log_batalha.id DESC
    LIMIT 200
";

$sqlLoja = "
    SELECT
        log_loja.id,
        usuario.nome AS usuario,
        loja.nome AS item,
        pokemon.nome AS pokemon,
        log_loja.preco_pago,
        log_loja.data_compra
    FROM log_loja
    JOIN usuario ON usuario.id = log_loja.usuario_id
    JOIN loja ON loja.id = log_loja.item_id
    LEFT JOIN pokemon ON pokemon.id = log_loja.pokemon_id
    ORDER BY log_loja.id DESC
    LIMIT 200
";

$logsBatalha = buscarLogs($pdo, $sqlBatalha);
$logsLoja    = buscarLogs($pdo, $sqlLoja);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Logs do Jogo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/logs.css?v=<?= time() ?>">
</head>
<body>
<main class="logs-viewport">
    <div class="logs-container">
        <header class="logs-header">
            <div>
                <span class="logs-eyebrow">PAINEL DE ADMINISTRAÇÃO</span>
                <h1>📜 Logs e movimentação do jogo</h1>
            </div>
            <a class="logs-back" href="index.php?rota=menu">← Voltar ao menu</a>
        </header>

        <section class="logs-section">
            <h2>⚔️ Batalhas</h2>
            <?php if (empty($logsBatalha)): ?>
                <p class="logs-empty">Nenhuma batalha registrada.</p>
            <?php else: ?>
                <div class="logs-table-wrap">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Data</th>
                                <th>Treinador</th>
                                <th>Pokémon</th>
                                <th>Adversário</th>
                                <th>Resultado</th>
                                <th>Dinheiro ganho</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logsBatalha as $batalha): ?>
                                <tr>
                                    <td><?= $batalha['id'] ?></td>
                                    <td><?= $batalha['data_batalha'] ?></td>
                                    <td><?= $batalha['usuario'] ?></td>
                                    <td><?= $batalha['pokemon'] ?? '-' ?></td>
                                    <td><?= $batalha['adversario'] ?></td>
                                    <td><?= $batalha['resultado'] ?></td>
                                    <td><?= $batalha['dinheiro_ganho'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="logs-section">
            <h2>🛒 Compras na PokéMart</h2>
            <?php if (empty($logsLoja)): ?>
                <p class="logs-empty">Nenhuma compra registrada.</p>
            <?php else: ?>
                <div class="logs-table-wrap">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Data</th>
                                <th>Treinador</th>
                                <th>Item</th>
                                <th>Pokémon alvo</th>
                                <th>Preço pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logsLoja as $compra): ?>
                                <tr>
                                    <td><?= $compra['id'] ?></td>
                                    <td><?= $compra['data_compra'] ?></td>
                                    <td><?= $compra['usuario'] ?></td>
                                    <td><?= $compra['item'] ?></td>
                                    <td><?= $compra['pokemon'] ?? 'Guardado na mochila' ?></td>
                                    <td><?= $compra['preco_pago'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
