<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../verifica_login.php';
require_once __DIR__ . '/../../Services/PokemonCatalogo.php';

$meuTime  = $_SESSION['meu_time'] ?? [];
$inimigos = $_SESSION['inimigos_time'] ?? ($_SESSION['inimigos_restantes'] ?? []);

// Sem time montado, volta para a arena
if (empty($meuTime)) {
    header('Location: index.php?rota=batalha');
    exit;
}

// Extrai o ID da Pokédex de um item da sessão (pode ser array com 'id' ou só o número)
function idDoItem($item): int {
    return is_array($item) ? (int)($item['id'] ?? 0) : (int)$item;
}

// Busca os dados do Pokémon na PokéAPI (via catálogo) no formato usado pela tela
function pokemonDoCombate(int $id): ?array {
    $p = PokemonCatalogo::buscarPorId($id);
    if ($p === null) {
        return null;
    }
    return [
        'id'    => $p['id_pokedex'],
        'name'  => $p['nome'],
        'tipo'  => $p['tipo'],
        'nivel' => $p['nivel'],
        'img'   => $p['imagemArtwork'],
        'hp'    => $p['hpMaximo']
    ];
}

// Passo 1: apenas exibição — primeiro Pokémon do time contra o primeiro inimigo
$idMeu = idDoItem($meuTime[0]);

$idInimigo = !empty($inimigos) ? idDoItem($inimigos[0]) : 0;
if ($idInimigo === 0) {
    // Sem inimigos na sessão: usa o primeiro do catálogo que não está no time
    $idsTime = array_map('idDoItem', $meuTime);
    foreach (PokemonCatalogo::IDS as $id) {
        if (!in_array($id, $idsTime)) {
            $idInimigo = $id;
            break;
        }
    }
}

$meuPoke     = pokemonDoCombate($idMeu);
$pokeInimigo = pokemonDoCombate($idInimigo);

// Se a PokéAPI estiver indisponível, volta para a arena com aviso
if ($meuPoke === null || $pokeInimigo === null) {
    echo "<script>alert('Não foi possível carregar os dados da PokéAPI. Tente novamente.'); window.location.href='index.php?rota=batalha';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokeArena - Combate</title>
    <link rel="stylesheet" href="assets/css/arena.css">
    <link rel="stylesheet" href="assets/css/combate.css">
</head>
<body>

    <div class="arena-wrapper">

        <!-- Cabeçalho -->
        <header class="arena-header">
            <div class="titulo-box">
                <h1>Combate</h1>
                <p>Escolha sua ação e derrote o adversário!</p>
            </div>
            <div class="arena-header-acoes">
                <?php if (!empty($_SESSION['usuario'])): ?>
                    <div class="treinador-badge">
                        Treinador: <b><?php echo htmlspecialchars($_SESSION['usuario']); ?></b>
                    </div>
                <?php endif; ?>
                <a href="index.php?rota=batalha" class="btn-link">← Voltar à Arena</a>
            </div>
        </header>

        <div class="arena-conteudo">

            <!-- Campo de batalha -->
            <div class="campo-batalha">

                <!-- Inimigo (topo direito) -->
                <div class="lado lado-inimigo">
                    <div class="caixa-status">
                        <div class="status-topo">
                            <span class="status-nome"><?php echo htmlspecialchars(ucfirst($pokeInimigo['name'] ?? '')); ?></span>
                            <span class="status-nivel">Nv 5</span>
                        </div>
                        <div class="hp-linha">
                            <span class="hp-rotulo">HP</span>
                            <div class="hp-barra-bg">
                                <div class="hp-barra" style="width: 100%;"></div>
                            </div>
                        </div>
                        <div class="hp-numeros"><?php echo (int)($pokeInimigo['hp'] ?? 50); ?> / <?php echo (int)($pokeInimigo['hp'] ?? 50); ?></div>
                    </div>
                    <div class="sprite-box">
                        <img src="<?php echo htmlspecialchars($pokeInimigo['img'] ?? ''); ?>" alt="<?php echo htmlspecialchars($pokeInimigo['name'] ?? ''); ?>">
                        <div class="sombra"></div>
                    </div>
                </div>

                <!-- Jogador (base esquerda) -->
                <div class="lado lado-jogador">
                    <div class="sprite-box">
                        <img src="<?php echo htmlspecialchars($meuPoke['img'] ?? ''); ?>" alt="<?php echo htmlspecialchars($meuPoke['name'] ?? ''); ?>">
                        <div class="sombra"></div>
                    </div>
                    <div class="caixa-status">
                        <div class="status-topo">
                            <span class="status-nome"><?php echo htmlspecialchars(ucfirst($meuPoke['name'] ?? '')); ?></span>
                            <span class="status-nivel">Nv 5</span>
                        </div>
                        <div class="hp-linha">
                            <span class="hp-rotulo">HP</span>
                            <div class="hp-barra-bg">
                                <div class="hp-barra" style="width: 100%;"></div>
                            </div>
                        </div>
                        <div class="hp-numeros"><?php echo (int)($meuPoke['hp'] ?? 50); ?> / <?php echo (int)($meuPoke['hp'] ?? 50); ?></div>
                    </div>
                </div>

            </div>

            <!-- Painel inferior: mensagem + ações -->
            <div class="painel-combate">
                <div class="caixa-mensagem">
                    O que <b><?php echo htmlspecialchars(ucfirst($meuPoke['name'] ?? '')); ?></b> vai fazer?
                </div>
                <div class="acoes-combate">
                    <button type="button" class="btn-acao btn-atacar">Atacar</button>
                    <button type="button" class="btn-acao btn-trocar">Trocar</button>
                    <button type="button" class="btn-acao btn-item">Itens</button>
                    <button type="button" class="btn-acao btn-fugir">Fugir</button>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
