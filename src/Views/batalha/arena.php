<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../verifica_login.php';

$meuTime = $_SESSION['meu_time'] ?? [];
$inimigosRestantes = $_SESSION['inimigos_restantes'] ?? [];

// Mapeamento visual e tradução dos tipos para a Arena
$mapaTipos = [
    'grass' => ['nome' => 'Planta', 'icone' => '🌿', 'classe' => 'grass'],
    'fire' => ['nome' => 'Fogo', 'icone' => '🔥', 'classe' => 'fire'],
    'water' => ['nome' => 'Água', 'icone' => '💧', 'classe' => 'water'],
    'electric' => ['nome' => 'Elétrico', 'icone' => '⚡', 'classe' => 'electric'],
    'normal' => ['nome' => 'Normal', 'icone' => '⚪', 'classe' => 'normal'],
    'psychic' => ['nome' => 'Psíquico', 'icone' => '🔮', 'classe' => 'psychic'],
    'ghost' => ['nome' => 'Fantasma', 'icone' => '👻', 'classe' => 'ghost'],
    'poison' => ['nome' => 'Venenoso', 'icone' => '☠️', 'classe' => 'poison'],
    'ground' => ['nome' => 'Terra', 'icone' => '🏜️', 'classe' => 'ground'],
    'rock' => ['nome' => 'Pedra', 'icone' => '🪨', 'classe' => 'rock'],
    'bug' => ['nome' => 'Inseto', 'icone' => '🐛', 'classe' => 'bug'],
    'dragon' => ['nome' => 'Dragão', 'icone' => '🐉', 'classe' => 'dragon'],
    'ice' => ['nome' => 'Gelo', 'icone' => '❄️', 'classe' => 'ice'],
    'fighting' => ['nome' => 'Lutador', 'icone' => '🥊', 'classe' => 'fighting']
];

// Mapeamento rápido por ID ou Nome para Pokémons previamente armazenados na sessão
$tipoPorPokemon = [
    1 => 'grass', 'bulbasaur' => 'grass',
    252 => 'grass', 'treecko' => 'grass',
    4 => 'fire', 'charmander' => 'fire',
    255 => 'fire', 'torchic' => 'fire',
    7 => 'water', 'squirtle' => 'water',
    258 => 'water', 'mudkip' => 'water',
    25 => 'electric', 'pikachu' => 'electric',
    179 => 'electric', 'mareep' => 'electric',
    133 => 'normal', 'eevee' => 'normal',
    143 => 'normal', 'snorlax' => 'normal',
    63 => 'psychic', 'abra' => 'psychic',
    92 => 'ghost', 'gastly' => 'ghost'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokeArena - Arena de Batalha</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/arena.css">
</head>
<body>

    <div class="arena-wrapper">

        <!-- Barra superior / Cabeçalho em tela cheia -->
        <header class="arena-header">
            <div class="titulo-box">
                <h1>Arena de Batalha</h1>
                <p>Prepare-se para o combate na PokeArena!</p>
            </div>
            <div class="arena-header-acoes">
                <?php if (!empty($_SESSION['usuario'])): ?>
                    <div class="treinador-badge">
                        Treinador: <b><?php echo htmlspecialchars($_SESSION['usuario']); ?></b>
                    </div>
                <?php endif; ?>
                <a href="index.php?rota=builder" class="btn-link">⚙ Modificar Time</a>
                <a href="index.php?rota=loja" class="btn-link btn-verde">🏪 Poké Mart</a>
                <a href="index.php?rota=menu" class="btn-link">← Menu</a>
            </div>
        </header>

        <!-- Conteúdo Principal em Tela Cheia -->
        <div class="arena-conteudo">
             <!-- Seção do Time Selecionado -->
            <h2 class="secao-titulo">Seu Time Selecionado</h2>
            <?php if (empty($meuTime)): ?>
                <div class="aviso-vazio">
                    Nenhum Pokémon foi selecionado ainda. <a href="index.php?rota=builder">Clique aqui para montar sua equipe no Team Builder</a>.
                </div>
            <?php else: ?>
                <div class="grid-time">
                    <?php foreach ($meuTime as $p): ?>
                        <?php
                            $idOrName = strtolower(trim($p['name'] ?? ''));
                            $idPoke = $p['id'] ?? null;
                            $tipoKey = $p['tipo'] ?? ($idPoke && isset($tipoPorPokemon[$idPoke]) ? $tipoPorPokemon[$idPoke] : ($tipoPorPokemon[$idOrName] ?? 'normal'));
                            $dadosTipo = $mapaTipos[$tipoKey] ?? ['nome' => ucfirst($tipoKey), 'icone' => '✨', 'classe' => $tipoKey];
                        ?>
                        <div class="card-poke card-tipo-<?php echo htmlspecialchars($dadosTipo['classe']); ?>">
                            <img src="<?php echo htmlspecialchars($p['img'] ?? ''); ?>" alt="<?php echo htmlspecialchars($p['name'] ?? ''); ?>" onerror="this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png'">
                            <h3><?php echo htmlspecialchars($p['name'] ?? ''); ?></h3>
                            <div class="badge-tipo-pill badge-pill-<?php echo htmlspecialchars($dadosTipo['classe']); ?>">
                                <?php echo $dadosTipo['icone']; ?> <?php echo htmlspecialchars($dadosTipo['nome']); ?>
                            </div>
                            <div class="stats-mini">
                                <?php if (isset($p['hp'])): ?><div class="stat-hp"><b>HP:</b> <span><?php echo (int)$p['hp']; ?></span></div><?php endif; ?>
                                <?php if (isset($p['attack'])): ?><div><b>Ataque:</b> <span><?php echo (int)$p['attack']; ?></span></div><?php endif; ?>
                                <?php if (isset($p['defense'])): ?><div><b>Defesa:</b> <span><?php echo (int)$p['defense']; ?></span></div><?php endif; ?>
                                <?php if (isset($p['speed'])): ?><div><b>Velocidade:</b> <span><?php echo (int)$p['speed']; ?></span></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Seção de Inimigos Restantes -->
            <div class="painel-inimigos">
                <h2 class="secao-titulo" style="margin-top: 0;">Inimigos Disponíveis (<?php echo count($inimigosRestantes); ?>)</h2>
                <div class="inimigos-chips">
                    <?php foreach ($inimigosRestantes as $inimigoId): ?>
                        <span class="inimigo-chip">🔴 Pokémon #<?php echo (int)$inimigoId; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Rodapé em Barra de Largura Total -->
        <footer class="arena-footer">
            <span><b>PokeArena</b> - Arena de Batalha Oficial</span>
            <a class="btn-link" href="index.php?rota=menu">← Voltar ao Menu Principal</a>
        </footer>

    </div>

</body>
</html>
