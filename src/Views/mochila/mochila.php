<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!class_exists('Conexao')) {
    require_once __DIR__ . '/../../../Autoload.php';
}

$pdo = Conexao::getConnection();

// Usuário atual
$usuarioId = (int)($_SESSION['usuario_id'] ?? 1);
$usuarioNome = $_SESSION['usuario'] ?? 'Treinador';

// 1. Itens da mochila: atualmente vazia pois o usuário não adquiriu nenhum item ainda
$itensMochila = $_SESSION['mochila'] ?? [];

// 2. Busca o time atual do usuário (seja da sessão ou do banco de dados)
$meuTime = $_SESSION['meu_time'] ?? [];

if (empty($meuTime) && $usuarioId > 0) {
    $stmtTime = $pdo->prepare("
        SELECT p.id, p.nome, p.nivel, p.hp_atual, p.status_condicao
        FROM time t
        JOIN pokemon p ON t.pokemon_id = p.id
        WHERE t.usuario_id = :uid
        ORDER BY t.posicao ASC
    ");
    $stmtTime->execute([':uid' => $usuarioId]);
    $timeBanco = $stmtTime->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($timeBanco)) {
        $meuTime = $timeBanco;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mochila - PokéArena</title>
    <link rel="stylesheet" href="assets/css/mochila.css">
</head>
<body class="mochila-page">

    <header>
        <h1>🎒 Mochila do Treinador</h1>
        <p>Treinador: <strong><?php echo htmlspecialchars($usuarioNome); ?></strong></p>
        <nav>
            <a href="index.php?rota=loja">Ir ao PokéMart</a>
            <a href="index.php?rota=menu">Menu Principal</a>
            <a href="index.php?rota=batalha">Batalha</a>
        </nav>
    </header>

    <hr>

    <section>
        <h2>Itens na Mochila</h2>
        <p>Os itens comprados no PokéMart (mart.php) serão guardados aqui.</p>

        <?php if (empty($itensMochila)): ?>
            <p><em>Sua mochila está vazia. Você ainda não adquiriu nenhum item no PokéMart.</em></p>
            <p><a href="index.php?rota=loja">🛒 Ir às compras no PokéMart</a></p>
        <?php else: ?>
            <table border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>Ícone</th>
                        <th>Nome do Item</th>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                        <th>Efeito / Descrição</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itensMochila as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['icone'] ?? '📦'); ?></td>
                            <td><strong><?php echo htmlspecialchars($item['nome'] ?? 'Item'); ?></strong></td>
                            <td><?php echo htmlspecialchars(ucfirst($item['tipo'] ?? 'Geral')); ?></td>
                            <td>x<?php echo (int)($item['quantidade'] ?? 1); ?></td>
                            <td><?php echo htmlspecialchars($item['descricao'] ?? ''); ?></td>
                            <td>
                                <button type="button">Usar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <br>
    <hr>

    <!-- Área de escolha de Pokémon para uso de item -->
    <section>
        <h3>Usar Item em um Pokémon</h3>

        <?php if (!empty($meuTime)): ?>
            <form>
                <p>
                    <label for="pokemon_alvo">Escolha o Pokémon da sua equipe:</label>
                    <select id="pokemon_alvo" name="pokemon_id">
                        <option value="">-- Selecione um Pokémon do seu time --</option>
                        <?php foreach ($meuTime as $indice => $poke): ?>
                            <?php
                                $nome = $poke['nome'] ?? $poke['name'] ?? "Pokémon #" . ($indice + 1);
                                $nivel = (int)($poke['nivel'] ?? 5);
                                $hpAtual = (int)($poke['hpAtual'] ?? $poke['hp_atual'] ?? 0);
                                $status = $poke['statusCondicao'] ?? $poke['status_condicao'] ?? 'Normal';
                            ?>
                            <option value="<?php echo htmlspecialchars($poke['id'] ?? $indice); ?>">
                                <?php echo htmlspecialchars($nome); ?> (Nv. <?php echo $nivel; ?> | HP: <?php echo $hpAtual; ?> | Status: <?php echo htmlspecialchars($status); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <button type="button" disabled title="Você não possui itens na mochila para usar">
                        Confirmar Uso do Item (Mochila Vazia)
                    </button>
                </p>
            </form>
        <?php else: ?>
            <p>
                <strong>Aviso:</strong> Você ainda não formou nenhum time de Pokémon.
                A opção de aplicar itens está <strong>indisponível</strong>.
            </p>
            <p>
                <a href="index.php?rota=builder">⚔ Montar um time no Builder</a>
            </p>
            <form>
                <p>
                    <label for="pokemon_alvo">Escolha o Pokémon da sua equipe:</label>
                    <select id="pokemon_alvo" name="pokemon_id" disabled>
                        <option value="">Nenhum time formado</option>
                    </select>
                </p>
                <p>
                    <button type="button" disabled>
                        Confirmar Uso do Item (Indisponível)
                    </button>
                </p>
            </form>
        <?php endif; ?>
    </section>

</body>
</html>
