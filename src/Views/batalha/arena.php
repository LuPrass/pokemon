<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../verifica_login.php';
require_once __DIR__ . '/../../Services/PokemonCatalogo.php';

$meuTime = $_SESSION['meu_time'] ?? [];
$inimigosRestantes = $_SESSION['inimigos_restantes'] ?? [];
$inimigosTime = $_SESSION['inimigos_time'] ?? [];

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

// Busca um Pokémon do catálogo (dados da PokéAPI) no formato usado pelos cards
function buscarPokemonApi(int $id): array {
    $p = PokemonCatalogo::buscarPorId($id);
    if ($p === null) {
        // Falha na API: devolve dados mínimos
        return [
            'id'   => $id,
            'name' => "Pokémon #{$id}",
            'tipo' => 'normal',
            'img'  => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/{$id}.png"
        ];
    }
    return [
        'id'      => $p['id_pokedex'],
        'name'    => strtolower($p['nome']),
        'tipo'    => $p['tipo'],
        'img'     => $p['imagemArtwork'],
        'hp'      => $p['hpMaximo'],
        'attack'  => $p['ataque'],
        'defense' => $p['defesa'],
        'speed'   => $p['velocidade']
    ];
}

// Monta os Pokémons inimigos com os dados completos para renderizar os cards
$listaInimigos = [];
if (!empty($inimigosTime)) {
    $listaInimigos = $inimigosTime;
} elseif (!empty($inimigosRestantes)) {
    foreach ($inimigosRestantes as $item) {
        if (is_array($item) && isset($item['name'])) {
            $listaInimigos[] = $item;
        } else {
            $id = is_array($item) ? (int)($item['id'] ?? 0) : (int)$item;
            $listaInimigos[] = buscarPokemonApi($id);
        }
    }
} else {
    // Se o jogador ainda não montou, pega os que não estão no meuTime
    $idsEscolhidos = array_column($meuTime, 'id');
    foreach (PokemonCatalogo::IDS as $id) {
        if (!in_array($id, $idsEscolhidos)) {
            $listaInimigos[] = buscarPokemonApi($id);
        }
    }
}
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
                <a href="index.php?rota=builder&modificar=1" class="btn-link">⚙ Modificar Time</a>
                <a href="index.php?rota=loja" class="btn-link btn-verde">🏪 Poké Mart</a>
                <a href="index.php?rota=menu" class="btn-link">← Menu</a>
            </div>
        </header>

        <!-- Conteúdo Principal em Tela Cheia -->
        <div class="arena-conteudo">
            
            <!-- Seção do Time Selecionado do Jogador -->
            <div class="secao-titulo-linha">
                <h2 class="secao-titulo">⚔️ Seu Time Selecionado (<?php echo count($meuTime); ?>)</h2>
                <?php if (!empty($meuTime)): ?>
                    <a href="index.php?rota=combate" class="btn-link btn-verde">⚔ Ir para a Batalha</a>
                <?php endif; ?>
            </div>
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
                            $tipoKey = $p['tipo'] ?? 'normal';
                            $dadosTipo = $mapaTipos[$tipoKey] ?? ['nome' => ucfirst($tipoKey), 'icone' => '✨', 'classe' => $tipoKey];
                        ?>
                        <div class="card-poke card-tipo-<?php echo htmlspecialchars($dadosTipo['classe']); ?>">
                            <img src="<?php echo htmlspecialchars($p['img'] ?? ''); ?>" alt="<?php echo htmlspecialchars($p['name'] ?? ''); ?>" onerror="this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png'">
                            <h3><?php echo htmlspecialchars(ucfirst($p['name'] ?? '')); ?></h3>
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

            <!-- Seção dos Pokémons Inimigos (Cards completos) -->
            <div class="painel-inimigos" style="margin-top: 25px;">
                <h2 class="secao-titulo" style="margin-top: 0;">🔴 Pokémons Inimigos na Arena (<?php echo count($listaInimigos); ?>)</h2>
                <?php if (empty($listaInimigos)): ?>
                    <p style="color: #666; font-size: 14px;">Todos os Pokémons foram derrotados ou nenhum inimigo restante.</p>
                <?php else: ?>
                    <div class="grid-time" style="margin-bottom: 0;">
                        <?php foreach ($listaInimigos as $p): ?>
                            <?php
                                $idOrName = strtolower(trim($p['name'] ?? ''));
                                $idPoke = $p['id'] ?? null;
                                $tipoKey = $p['tipo'] ?? 'normal';
                                $dadosTipo = $mapaTipos[$tipoKey] ?? ['nome' => ucfirst($tipoKey), 'icone' => '✨', 'classe' => $tipoKey];
                            ?>
                            <div class="card-poke card-tipo-<?php echo htmlspecialchars($dadosTipo['classe']); ?>">
                                <img src="<?php echo htmlspecialchars($p['img'] ?? ''); ?>" alt="<?php echo htmlspecialchars($p['name'] ?? ''); ?>" onerror="this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png'">
                                <h3><?php echo htmlspecialchars(ucfirst($p['name'] ?? '')); ?></h3>
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
            </div>

        </div>

    </div>

</body>
</html>
