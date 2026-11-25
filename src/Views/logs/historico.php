<?php
require_once __DIR__ . '/../../../Autoload.php';

// 1. Inicia a sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Verifica se o usuário está logado
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

// 3. Verifica se o usuário tem permissão de administrador
$adminsPermitidos = require __DIR__ . '/../../../config/Administradores.php';

$usuarioAtual = '';
if (isset($_SESSION['usuario'])) {
    $usuarioAtual = $_SESSION['usuario'];
}

if (!in_array($usuarioAtual, $adminsPermitidos, true)) {
    header('Location: index.php?rota=menu');
    exit;
}

// 4. Conexão com o banco de dados
$pdo = Conexao::getConnection();

// 5. Consulta estatísticas dos treinadores (View: view_estatisticas_treinadores)
$sqlUsuarios = "SELECT * FROM view_estatisticas_treinadores";
$stmtUsuarios = $pdo->query($sqlUsuarios);
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

// 6. Consulta do histórico de atividades/login dos usuários (log_usuario)
$logUsuarios = [];
try {
    $sqlLogUsuarios = "SELECT * FROM log_usuario ORDER BY id DESC LIMIT 100";
    $stmtLogUsuarios = $pdo->query($sqlLogUsuarios);
    if ($stmtLogUsuarios) {
        $logUsuarios = $stmtLogUsuarios->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $logUsuarios = [];
}

// 7. Consulta dos times escalados ativos (View: view_time_usuario)
$sqlTimes = "SELECT * FROM view_time_usuario";
$stmtTimes = $pdo->query($sqlTimes);
$times = $stmtTimes->fetchAll(PDO::FETCH_ASSOC);

// 8. Consulta do histórico permanente de montagem de times (log_time)
$logTimes = [];
try {
    $sqlLogTimes = "
        SELECT 
            lt.id,
            u.nome AS usuario,
            lt.posicao,
            p.nome AS pokemon,
            p.tipo,
            lt.data_criacao
        FROM log_time lt
        INNER JOIN usuario u ON u.id = lt.usuario_id
        INNER JOIN pokemon p ON p.id = lt.pokemon_id
        ORDER BY lt.id DESC
        LIMIT 100
    ";
    $stmtLogTimes = $pdo->query($sqlLogTimes);
    if ($stmtLogTimes) {
        $logTimes = $stmtLogTimes->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $logTimes = [];
}

// 9. Consulta do histórico de compras na loja (log_loja)
$sqlCompras = "
    SELECT
        log_loja.id,
        usuario.nome AS usuario,
        loja.nome AS item,
        pokemon.nome AS pokemon,
        log_loja.preco_pago,
        log_loja.data_compra
    FROM log_loja
    INNER JOIN usuario ON usuario.id = log_loja.usuario_id
    INNER JOIN loja ON loja.id = log_loja.item_id
    LEFT JOIN pokemon ON pokemon.id = log_loja.pokemon_id
    ORDER BY log_loja.id DESC
    LIMIT 100
";
$stmtCompras = $pdo->query($sqlCompras);
$compras = $stmtCompras->fetchAll(PDO::FETCH_ASSOC);

// 10. Consulta do histórico de batalhas (View: view_historico_batalhas)
$sqlBatalhas = "SELECT * FROM view_historico_batalhas LIMIT 100";
$stmtBatalhas = $pdo->query($sqlBatalhas);
$batalhas = $stmtBatalhas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Histórico e Registros</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/logs.css?v=<?= time() ?>">
</head>
<body>
<main class="logs-viewport">
    <div class="logs-container">

        <!-- Cabeçalho da página -->
        <header class="logs-header">
            <div>
                <span class="logs-eyebrow">PAINEL DE ADMINISTRAÇÃO</span>
                <h1>📜 Histórico e Registros do Jogo</h1>
            </div>
            <a class="logs-back" href="index.php?rota=menu">← Voltar ao Menu</a>
        </header>

        <!-- Atalhos rápidos para navegar entre as seções -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="logs-back" href="#sec-usuarios" style="background: #1e293b; box-shadow: none;">👤 Desempenho</a>
            <a class="logs-back" href="#sec-log-usuarios" style="background: #1e293b; box-shadow: none;">🔑 Log de Usuários</a>
            <a class="logs-back" href="#sec-times" style="background: #1e293b; box-shadow: none;">👥 Times Ativos</a>
            <a class="logs-back" href="#sec-log-times" style="background: #1e293b; box-shadow: none;">📜 Log de Times</a>
            <a class="logs-back" href="#sec-compras" style="background: #1e293b; box-shadow: none;">🛒 Compras</a>
            <a class="logs-back" href="#sec-batalhas" style="background: #1e293b; box-shadow: none;">⚔️ Batalhas</a>
        </div>

        <!-- 1. Tabela de Desempenho dos Treinadores -->
        <section class="logs-section" id="sec-usuarios">
            <h2>👤 Treinadores / Desempenho (View: view_estatisticas_treinadores)</h2>
            <?php if (empty($usuarios)): ?>
                <p class="logs-empty">Nenhum usuário cadastrado.</p>
            <?php else: ?>
                <div class="logs-table-wrap">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th># ID</th>
                                <th>Nome do Treinador</th>
                                <th>Saldo</th>
                                <th>Vitórias</th>
                                <th>Derrotas</th>
                                <th>Total de Batalhas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?php echo $u['usuario_id']; ?></td>
                                    <td><b><?php echo htmlspecialchars($u['usuario']); ?></b></td>
                                    <td>₽ <?php echo number_format($u['dinheiro'], 0, ',', '.'); ?></td>
                                    <td style="color: #34d399; font-weight: bold;"><?php echo $u['vitorias']; ?></td>
                                    <td style="color: #f87171; font-weight: bold;"><?php echo $u['derrotas']; ?></td>
                                    <td><?php echo $u['total_batalhas']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- 2. Tabela de Log de Acessos dos Usuários -->
        <section class="logs-section" id="sec-log-usuarios">
            <h2>🔑 Histórico de Acesso e Atividades de Treinadores (Tabela: log_usuario)</h2>
            <?php if (empty($logUsuarios)): ?>
                <p class="logs-empty">Nenhum registro de acesso registrado ainda.</p>
            <?php else: ?>
                <div class="logs-table-wrap">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th># ID</th>
                                <th>Data / Hora</th>
                                <th>Treinador</th>
                                <th>Ação / Evento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logUsuarios as $logUsuario): ?>
                                <tr>
                                    <td><?php echo $logUsuario['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($logUsuario['data_registro'])); ?></td>
                                    <td><b><?php echo htmlspecialchars($logUsuario['nome_usuario']); ?></b></td>
                                    <td>
                                        <?php if ($logUsuario['acao'] === 'Login'): ?>
                                            <span style="color: #34d399; font-weight: bold;">🟢 Login Realizado</span>
                                        <?php elseif ($logUsuario['acao'] === 'Logout'): ?>
                                            <span style="color: #94a3b8; font-weight: bold;">⚪ Logout (Desconectou)</span>
                                        <?php elseif ($logUsuario['acao'] === 'Cadastro'): ?>
                                            <span style="color: #60a5fa; font-weight: bold;">⭐ Novo Cadastro</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- 3. Tabela de Times Ativos -->
        <section class="logs-section" id="sec-times">
            <h2>👥 Times Ativos / Escalações Atuais (View: view_time_usuario)</h2>
            <?php if (empty($times)): ?>
                <p class="logs-empty">Nenhum Pokémon escalado em times.</p>
            <?php else: ?>
                <div class="logs-table-wrap">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Treinador</th>
                                <th>Vaga / Posição</th>
                                <th>Pokémon</th>
                                <th>Tipo</th>
                                <th>Nível</th>
                                <th>HP Atual / Máximo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($times as $t): ?>
                                <tr>
                                    <td><b><?php echo htmlspecialchars($t['usuario']); ?></b></td>
                                    <td>Posição <?php echo $t['posicao']; ?></td>
                                    <td><?php echo htmlspecialchars($t['pokemon']); ?></td>
                                    <td><?php echo htmlspecialchars($t['tipo']); ?></td>
                                    <td>Nível <?php echo $t['nivel']; ?></td>
                                    <td>
                                        <?php echo $t['hp_atual']; ?> / <?php echo $t['hp_maximo']; ?>
                                        <?php if ($t['hp_atual'] <= 0): ?>
                                            <span style="color: #f87171; font-weight: bold;">(Desmaiado)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- 4. Histórico de Montagem de Times (log_time) -->
        <section class="logs-section" id="sec-log-times">
            <h2>📜 Histórico de Montagem de Times (Tabela: log_time)</h2>
            <?php if (empty($logTimes)): ?>
                <p class="logs-empty">Nenhum histórico de time registrado ainda.</p>
            <?php else: ?>
                <div class="logs-table-wrap">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th># ID</th>
                                <th>Data / Hora</th>
                                <th>Treinador</th>
                                <th>Posição</th>
                                <th>Pokémon</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logTimes as $logTime): ?>
                                <tr>
                                    <td><?php echo $logTime['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($logTime['data_criacao'])); ?></td>
                                    <td><b><?php echo htmlspecialchars($logTime['usuario']); ?></b></td>
                                    <td>Posição <?php echo $logTime['posicao']; ?></td>
                                    <td><?php echo htmlspecialchars($logTime['pokemon']); ?></td>
                                    <td><?php echo htmlspecialchars($logTime['tipo']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- 5. Tabela de Compras -->
        <section class="logs-section" id="sec-compras">
            <h2>🛒 Histórico de Compras na Loja (Tabela: log_loja)</h2>
            <?php if (empty($compras)): ?>
                <p class="logs-empty">Nenhuma compra registrada.</p>
            <?php else: ?>
                <div class="logs-table-wrap">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th># ID</th>
                                <th>Data da Compra</th>
                                <th>Treinador</th>
                                <th>Item Comprado</th>
                                <th>Pokémon que usou o item</th>
                                <th>Preço Pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($compras as $c): ?>
                                <tr>
                                    <td><?php echo $c['id']; ?></td>
                                    <td><?php echo $c['data_compra']; ?></td>
                                    <td><?php echo htmlspecialchars($c['usuario']); ?></td>
                                    <td><b><?php echo htmlspecialchars($c['item']); ?></b></td>
                                    <td>
                                        <?php 
                                        if (!empty($c['pokemon'])) {
                                            echo htmlspecialchars($c['pokemon']);
                                        } else {
                                            echo 'Ainda na mochila';
                                        }
                                        ?>
                                    </td>
                                    <td>P$ <?php echo $c['preco_pago']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- 6. Tabela de Batalhas -->
        <section class="logs-section" id="sec-batalhas">
            <h2>⚔️ Histórico de Batalhas (View: view_historico_batalhas / log_batalha)</h2>
            <?php if (empty($batalhas)): ?>
                <p class="logs-empty">Nenhuma batalha registrada.</p>
            <?php else: ?>
                <div class="logs-table-wrap">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th># ID</th>
                                <th>Data</th>
                                <th>Treinador</th>
                                <th>Pokémon</th>
                                <th>Adversário</th>
                                <th>Resultado</th>
                                <th>Dinheiro Ganho</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batalhas as $b): ?>
                                <tr>
                                    <td><?php echo isset($b['log_id']) ? $b['log_id'] : $b['id']; ?></td>
                                    <td><?php echo $b['data_batalha']; ?></td>
                                    <td><?php echo htmlspecialchars($b['usuario']); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($b['pokemon'])) {
                                            echo htmlspecialchars($b['pokemon']);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($b['adversario']); ?></td>
                                    <td>
                                        <?php if (strtolower($b['resultado']) === 'vitoria'): ?>
                                            <span style="color: #34d399; font-weight: bold;">Vitória</span>
                                        <?php else: ?>
                                            <span style="color: #f87171; font-weight: bold;">Derrota</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>P$ <?php echo $b['dinheiro_ganho']; ?></td>
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
