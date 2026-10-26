<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../verifica_login.php';

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

// Catálogo com os dados completos dos 12 Pokémons
$catalogoPokemons = [
    1 => ['id' => 1, 'name' => 'bulbasaur', 'tipo' => 'grass', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/1.png', 'hp' => 45, 'attack' => 49, 'defense' => 49, 'speed' => 45],
    252 => ['id' => 252, 'name' => 'treecko', 'tipo' => 'grass', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/252.png', 'hp' => 40, 'attack' => 45, 'defense' => 35, 'speed' => 70],
    4 => ['id' => 4, 'name' => 'charmander', 'tipo' => 'fire', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/4.png', 'hp' => 39, 'attack' => 52, 'defense' => 43, 'speed' => 65],
    255 => ['id' => 255, 'name' => 'torchic', 'tipo' => 'fire', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/255.png', 'hp' => 45, 'attack' => 60, 'defense' => 40, 'speed' => 45],
    7 => ['id' => 7, 'name' => 'squirtle', 'tipo' => 'water', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/7.png', 'hp' => 44, 'attack' => 48, 'defense' => 65, 'speed' => 43],
    258 => ['id' => 258, 'name' => 'mudkip', 'tipo' => 'water', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/258.png', 'hp' => 50, 'attack' => 70, 'defense' => 50, 'speed' => 40],
    25 => ['id' => 25, 'name' => 'pikachu', 'tipo' => 'electric', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/25.png', 'hp' => 35, 'attack' => 55, 'defense' => 40, 'speed' => 90],
    179 => ['id' => 179, 'name' => 'mareep', 'tipo' => 'electric', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/179.png', 'hp' => 55, 'attack' => 40, 'defense' => 40, 'speed' => 35],
    133 => ['id' => 133, 'name' => 'eevee', 'tipo' => 'normal', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/133.png', 'hp' => 55, 'attack' => 55, 'defense' => 50, 'speed' => 55],
    143 => ['id' => 143, 'name' => 'snorlax', 'tipo' => 'normal', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/143.png', 'hp' => 160, 'attack' => 110, 'defense' => 65, 'speed' => 30],
    63 => ['id' => 63, 'name' => 'abra', 'tipo' => 'psychic', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/63.png', 'hp' => 25, 'attack' => 20, 'defense' => 15, 'speed' => 90],
    92 => ['id' => 92, 'name' => 'gastly', 'tipo' => 'ghost', 'img' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/92.png', 'hp' => 30, 'attack' => 35, 'defense' => 30, 'speed' => 80]
];

// Monta os Pokémons inimigos com os dados completos para renderizar os cards
$listaInimigos = [];
if (!empty($inimigosTime)) {
    $listaInimigos = $inimigosTime;
} elseif (!empty($inimigosRestantes)) {
    foreach ($inimigosRestantes as $item) {
        $id = is_array($item) ? ($item['id'] ?? 0) : (int)$item;
        if (isset($catalogoPokemons[$id])) {
            $listaInimigos[] = (is_array($item) && isset($item['name'])) ? $item : $catalogoPokemons[$id];
        } else {
            $listaInimigos[] = [
                'id' => $id,
                'name' => "Pokémon #{$id}",
                'tipo' => 'normal',
                'img' => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/{$id}.png",
                'hp' => 50,
                'attack' => 50,
                'defense' => 50,
                'speed' => 50
            ];
        }
    }
} else {
    // Se o jogador ainda não montou, pega os que não estão no meuTime
    $idsEscolhidos = array_column($meuTime, 'id');
    foreach ($catalogoPokemons as $id => $p) {
        if (!in_array($id, $idsEscolhidos)) {
            $listaInimigos[] = $p;
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
            <h2 class="secao-titulo">⚔️ Seu Time Selecionado (<?php echo count($meuTime); ?>)</h2>
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
                                $tipoKey = $p['tipo'] ?? ($idPoke && isset($tipoPorPokemon[$idPoke]) ? $tipoPorPokemon[$idPoke] : ($tipoPorPokemon[$idOrName] ?? 'normal'));
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
