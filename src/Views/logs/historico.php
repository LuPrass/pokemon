<?php
 require_once __DIR__ . '/../../../Autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

// Verifica se o usuário NÃO está logado OU se o login NÃO é 'adm'
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'adm') {
    header('Location: index.php?rota=menu');
    exit;
}

$pdo = Conexao::getConnection();

$logsBatalha = $pdo->query(
    "SELECT lb.id, u.nome AS usuario, p.nome AS pokemon, lb.adversario, lb.resultado, lb.dinheiro_ganho, lb.data_batalha
     FROM log_batalha lb
     JOIN usuario u ON u.id = lb.usuario_id
     LEFT JOIN pokemon p ON p.id = lb.pokemon_id
     ORDER BY lb.id DESC LIMIT 200"
)->fetchAll(PDO::FETCH_ASSOC);

$logsLoja = $pdo->query(
    "SELECT ll.id, u.nome AS usuario, l.nome AS item, p.nome AS pokemon, ll.preco_pago, ll.data_compra
     FROM log_loja ll
     JOIN usuario u ON u.id = ll.usuario_id
     JOIN loja l ON l.id = ll.item_id
     LEFT JOIN pokemon p ON p.id = ll.pokemon_id
     ORDER BY ll.id DESC LIMIT 200"
)->fetchAll(PDO::FETCH_ASSOC);

function h($v) { return htmlspecialchars((string)$v); }
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
                        <tr><th>#</th><th>Data</th><th>Treinador</th><th>Pokémon</th><th>Adversário</th><th>Resultado</th><th>Dinheiro ganho</th></tr>
                        <?php foreach ($logsBatalha as $l): ?>
                            <tr>
                                <td><?= h($l['id']) ?></td>
                                <td><?= h($l['data_batalha']) ?></td>
                                <td><?= h($l['usuario']) ?></td>
                                <td><?= h($l['pokemon'] ?? '-') ?></td>
                                <td><?= h($l['adversario']) ?></td>
                                <td><?= h($l['resultado']) ?></td>
                                <td><?= h($l['dinheiro_ganho']) ?></td>
                            </tr>
                        <?php endforeach; ?>
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
                        <tr><th>#</th><th>Data</th><th>Treinador</th><th>Item</th><th>Pokémon alvo</th><th>Preço pago</th></tr>
                        <?php foreach ($logsLoja as $l): ?>
                            <tr>
                                <td><?= h($l['id']) ?></td>
                                <td><?= h($l['data_compra']) ?></td>
                                <td><?= h($l['usuario']) ?></td>
                                <td><?= h($l['item']) ?></td>
                                <td><?= h($l['pokemon'] ?? 'Guardado na mochila') ?></td>
                                <td><?= h($l['preco_pago']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
