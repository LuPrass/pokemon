<?php
require_once __DIR__ . '/../../../Autoload.php';
require_once __DIR__ . '/../../Services/PokeApiService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Validações de acesso
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['meu_time'])) {
    header('Location: index.php?rota=builder');
    exit;
}



// Conexão com o banco de dados
$pdo = Conexao::getConnection();
$usuarioId = (int)($_SESSION['usuario_id'] ?? 1);

// Consulta o saldo atual de dinheiro do usuário no banco
$stmtUser = $pdo->prepare("SELECT nome, dinheiro FROM usuario WHERE id = :id");
$stmtUser->execute([':id' => $usuarioId]);
$dadosUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
$usuarioNome = $dadosUser['nome'] ?? ($_SESSION['usuario'] ?? 'Treinador');
$usuarioDinheiro = (int)($dadosUser['dinheiro'] ?? 100);

// Deixa o pokémon no formato do jogo (nome, hpMaximo, ataque...) e garante que ele tenha golpes
function prepararPokemon($pokemon) {
    $pokemon = Pokemon::normalizar($pokemon);

    if (empty($pokemon['movimentos']) && !empty($pokemon['id'])) {
        $pokemon['movimentos'] = PokeApiService::obterMovimentosPokemon((int)$pokemon['id']);
    }
    if (empty($pokemon['movimentos'])) {
        $pokemon['movimentos'] = PokeApiService::obterMovimentosPadrao(0, $pokemon['nome'], $pokemon['tipo']);
    }
    return $pokemon;
}

// O inimigo sorteia um dos golpes que ele tem
function escolherGolpe($pokemon) {
    $golpes = array_values($pokemon['movimentos']);
    return $golpes[array_rand($golpes)];
}

// Prepara o time do jogador
$meuTime = array_values($_SESSION['meu_time']);
foreach ($meuTime as $i => $pk) {
    $meuTime[$i] = prepararPokemon($pk);
}
$_SESSION['meu_time'] = $meuTime;
$_SESSION['batalha_iniciada'] = true;

// 2. Inicialização do combate de 9 batalhas (caso não exista)
if (empty($_SESSION['combate']) || !isset($_SESSION['combate']['batalha_atual'])) {
    $inimigosTime = array_values($_SESSION['inimigos_time'] ?? []);

    // Se não tem inimigos guardados, usa os pokémons do catálogo que o jogador não escolheu
    if (empty($inimigosTime)) {
        $idsEscolhidos = array_map(fn($p) => (int)($p['id'] ?? 0), $meuTime);
        foreach (array_diff(PokeApiService::IDS, $idsEscolhidos) as $id) {
            $info = PokeApiService::buscarPorId($id);
            if ($info) {
                $info['id'] = $id;
                $inimigosTime[] = $info;
            }
        }
    }

    foreach ($inimigosTime as $i => $inimigo) {
        $inimigosTime[$i] = prepararPokemon($inimigo);
    }
    $_SESSION['inimigos_time'] = $inimigosTime;

    // Sorteia 1 Pokémon do seu time para iniciar a primeira batalha
    $indicesVivos = [];
    foreach ($meuTime as $idx => $pk) {
        if ($pk['hpAtual'] > 0) {
            $indicesVivos[] = $idx;
        }
    }
    $idxJogadorSorteado = !empty($indicesVivos) ? $indicesVivos[array_rand($indicesVivos)] : 0;

    // Sorteia 1 adversário para a Batalha 1
    $inimigosPool = $inimigosTime;
    $idxInimigoSorteado = array_rand($inimigosPool);
    $inimigoAtual = $inimigosPool[$idxInimigoSorteado];
    unset($inimigosPool[$idxInimigoSorteado]);

    $_SESSION['combate'] = [
        'batalha_atual'      => 1,
        'total_batalhas'     => 9,
        'vitorias'           => 0,
        'pokemon_ativo_idx'  => $idxJogadorSorteado,
        'inimigo_atual'      => $inimigoAtual,
        'inimigos_restantes' => array_values($inimigosPool),
        'status'             => 'em_combate',
        'menu_aberto'        => 'principal', // 'principal', 'lutar', 'trocar'
        'mensagens'          => [
            "Início do combate! Batalha 1 de 9.",
            "O seu Pokémon sorteado foi: " . $meuTime[$idxJogadorSorteado]['nome'] . "!",
            "O adversário sorteado entre os 9 restantes foi: " . $inimigoAtual['nome'] . "!"
        ]
    ];
}

// 3. Processamento das Ações em PHP Puro (POST)
$avisoErro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $combate = &$_SESSION['combate'];
    $ativoIdx = (int)$combate['pokemon_ativo_idx'];

    // Salva o HP, o status e o nível do pokémon no banco
    $salvarNoBanco = function($pk) use ($pdo, $usuarioId) {
        $stmt = $pdo->prepare("UPDATE pokemon SET hp_atual = :hp, status_condicao = :status, nivel = :nivel WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->execute([
            ':hp'         => $pk['hpAtual'],
            ':status'     => $pk['statusCondicao'],
            ':nivel'      => $pk['nivel'],
            ':id'         => $pk['pokemon_db_id'],
            ':usuario_id' => $usuarioId
        ]);
    };

    // Registra o resultado da batalha (vitoria ou derrota) na tabela log_batalha
    // Obs: quando é vitória, o trigger do banco soma o dinheiro no saldo do usuário
    $registrarBatalha = function($pk, $adversario, $resultado, $dinheiro) use ($pdo, $usuarioId) {
        $stmt = $pdo->prepare("INSERT INTO log_batalha (usuario_id, pokemon_id, adversario, resultado, dinheiro_ganho) VALUES (:usuario_id, :pokemon_id, :adversario, :resultado, :dinheiro)");
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':pokemon_id' => $pk['pokemon_db_id'],
            ':adversario' => $adversario,
            ':resultado'  => $resultado,
            ':dinheiro'   => $dinheiro
        ]);
    };

    // Helper: verificar condição de Game Over (sem Pokémon e sem dinheiro para curar)
    $verificarGameOver = function() use (&$meuTime, $usuarioDinheiro) {
        $todosDesmaiados = true;
        foreach ($meuTime as $pk) {
            if ($pk['hpAtual'] > 0) {
                $todosDesmaiados = false;
                break;
            }
        }

        if ($todosDesmaiados) {
            $custoMinimoCura = 25;
            if ($usuarioDinheiro < $custoMinimoCura) {
                // Encerra o ciclo: limpa batalha, time e inimigos para montar um novo time
                unset($_SESSION['combate'], $_SESSION['meu_time'], $_SESSION['inimigos_time'], $_SESSION['batalha_iniciada']);
                $_SESSION['aviso_derrota'] = "FIM DE JOGO: Todos os seus Pokémon desmaiaram e você não possui dinheiro suficiente para comprar HP ou curá-los! Você foi retirado da batalha. Monte um novo time para recomeçar.";
                header("Location: index.php?rota=menu");
                exit;
            }
        }
        return $todosDesmaiados;
    };

    // -------------------------------------------------------------
    // CONTROLE DE NAVEGAÇÃO DO MINI MENU ("O que o pokemon deve fazer?")
    // -------------------------------------------------------------
    if ($acao === 'abrir_menu') {
        $menuAlvo = $_POST['menu_alvo'] ?? 'principal';
        $combate['menu_aberto'] = $menuAlvo;
    }

    // -------------------------------------------------------------
    // AÇÃO DE LUTAR: um turno completo (o jogador e o inimigo atacam)
    // -------------------------------------------------------------
    elseif ($acao === 'executar_ataque' && $combate['status'] === 'em_combate') {
        $tipoAtaqueEscolhido = $_POST['tipo_ataque'] ?? 'fisico';
        if (!in_array($tipoAtaqueEscolhido, ['fisico', 'especial', 'status'], true)) {
            $tipoAtaqueEscolhido = 'fisico';
        }

        $jogador = Pokemon::fromArray($meuTime[$ativoIdx]);
        $inimigo = Pokemon::fromArray($combate['inimigo_atual']);

        $golpesJogador = $meuTime[$ativoIdx]['movimentos'];
        $golpeJogador = $golpesJogador[$tipoAtaqueEscolhido] ?? reset($golpesJogador);
        $golpeInimigo = escolherGolpe($combate['inimigo_atual']);

        // A classe Batalha calcula o dano e os status
        $batalha = new Batalha($jogador, $inimigo);
        $batalha->turno($golpeJogador, $golpeInimigo);
        $combate['mensagens'] = $batalha->getTurnos();

        // Guarda como cada um ficou (HP e status)
        $meuTime[$ativoIdx] = array_merge($meuTime[$ativoIdx], $jogador->paraArray());
        $combate['inimigo_atual'] = array_merge($combate['inimigo_atual'], $inimigo->paraArray());
        $salvarNoBanco($meuTime[$ativoIdx]);

        if ($inimigo->estaDesmaiado()) {
            $combate['vitorias']++;

            // O Pokémon que venceu sobe 1 nível
            $jogador->subirNivel();
            $meuTime[$ativoIdx]['nivel'] = $jogador->getNivel();
            $salvarNoBanco($meuTime[$ativoIdx]);
            $combate['mensagens'][] = "⬆️ {$jogador->getNome()} subiu para o nível {$jogador->getNivel()}!";

            $recompensa = 100;
            $registrarBatalha($meuTime[$ativoIdx], $combate['inimigo_atual']['nome'], 'vitoria', $recompensa);
            $usuarioDinheiro += $recompensa;
            $combate['mensagens'][] = "💰 Recompensa: +{$recompensa} Pokédollars adicionados ao seu saldo!";

            if ($combate['batalha_atual'] >= $combate['total_batalhas']) {
                $combate['status'] = 'campeao';
                $combate['mensagens'][] = "🏆 PARABÉNS! Você derrotou todos os 9 adversários da PokéArena e se tornou o GRANDE CAMPEÃO!";
            } else {
                $combate['status'] = 'vitoria_rodada';
            }
        } elseif ($jogador->estaDesmaiado()) {
            // Nosso Pokémon desmaiou: fica registrado como derrota
            $registrarBatalha($meuTime[$ativoIdx], $combate['inimigo_atual']['nome'], 'derrota', 0);

            if ($verificarGameOver()) {
                $combate['status'] = 'derrota_sem_pokemon';
            } else {
                $combate['mensagens'][] = "Vá até 'Trocar' para escolher outro Pokémon consciente.";
            }
        }

        $combate['menu_aberto'] = 'principal';
        $_SESSION['meu_time'] = $meuTime;
    }

    // -------------------------------------------------------------
    // AÇÃO: TROCAR DE POKÉMON
    // -------------------------------------------------------------
    elseif ($acao === 'trocar' && ($combate['status'] === 'em_combate' || $combate['status'] === 'derrota_sem_pokemon')) {
        $novoIdx = isset($_POST['novo_idx']) ? (int)$_POST['novo_idx'] : -1;

        if (!isset($meuTime[$novoIdx])) {
            $avisoErro = "Pokémon inválido selecionado para troca!";
        } elseif ($meuTime[$novoIdx]['hpAtual'] <= 0) {
            $avisoErro = "O Pokémon " . $meuTime[$novoIdx]['nome'] . " está desmaiado (HP = 0) e não pode entrar em combate!";
        } elseif ($novoIdx === $combate['pokemon_ativo_idx']) {
            $avisoErro = "Este Pokémon já está na batalha!";
        } else {
            $anteriorIdx = $combate['pokemon_ativo_idx'];
            $anteriorVivo = $meuTime[$anteriorIdx]['hpAtual'] > 0;

            // Quem sai do combate perde as reduções de ataque e defesa
            $meuTime[$anteriorIdx]['modAtaque'] = 1.0;
            $meuTime[$anteriorIdx]['modDefesa'] = 1.0;

            $combate['pokemon_ativo_idx'] = $novoIdx;
            $combate['status'] = 'em_combate';
            $combate['menu_aberto'] = 'principal';
            $combate['mensagens'] = ["🔄 Você colocou " . $meuTime[$novoIdx]['nome'] . " em campo!"];

            // Se o anterior ainda estava vivo, o inimigo ataca enquanto você troca
            if ($anteriorVivo) {
                $novo = Pokemon::fromArray($meuTime[$novoIdx]);
                $inimigo = Pokemon::fromArray($combate['inimigo_atual']);

                $batalha = new Batalha($novo, $inimigo);
                $batalha->agir($inimigo, $novo, escolherGolpe($combate['inimigo_atual']));
                $combate['mensagens'] = array_merge($combate['mensagens'], $batalha->getTurnos());

                $meuTime[$novoIdx] = array_merge($meuTime[$novoIdx], $novo->paraArray());
                $combate['inimigo_atual'] = array_merge($combate['inimigo_atual'], $inimigo->paraArray());
                $salvarNoBanco($meuTime[$novoIdx]);

                if ($novo->estaDesmaiado()) {
                    $registrarBatalha($meuTime[$novoIdx], $combate['inimigo_atual']['nome'], 'derrota', 0);

                    if ($verificarGameOver()) {
                        $combate['status'] = 'derrota_sem_pokemon';
                    }
                }
            }

            $_SESSION['meu_time'] = $meuTime;
        }
    }

    // -------------------------------------------------------------
    // AÇÃO: AVANÇAR PARA A PRÓXIMA BATALHA
    // -------------------------------------------------------------
    elseif ($acao === 'proxima_batalha' && $combate['status'] === 'vitoria_rodada') {
        if (!empty($combate['inimigos_restantes'])) {
            $combate['batalha_atual']++;
            $idxSorteado = array_rand($combate['inimigos_restantes']);
            $novoInimigo = $combate['inimigos_restantes'][$idxSorteado];
            unset($combate['inimigos_restantes'][$idxSorteado]);
            $combate['inimigos_restantes'] = array_values($combate['inimigos_restantes']);

            // Escalonamento simples de nivel (Batalha 1 = Nv. 5 ate Batalha 9 = Nv. 13)
            $nivelInimigo = 4 + $combate['batalha_atual'];
            $fator = $nivelInimigo / 5.0;

            $novoInimigo['nivel'] = $nivelInimigo;
            $novoInimigo['hpMaximo'] = (int)round($novoInimigo['hpMaximo'] * $fator);
            $novoInimigo['hpAtual'] = $novoInimigo['hpMaximo'];
            $novoInimigo['ataque'] = (int)round($novoInimigo['ataque'] * $fator);
            $novoInimigo['defesa'] = (int)round($novoInimigo['defesa'] * $fator);
            $novoInimigo['velocidade'] = (int)round($novoInimigo['velocidade'] * $fator);

            $combate['inimigo_atual'] = $novoInimigo;
            $combate['status'] = 'em_combate';
            $combate['menu_aberto'] = 'principal';

            // Os pokémons do time começam a nova batalha sem reduções de ataque e defesa
            foreach ($meuTime as $i => $pk) {
                $meuTime[$i]['modAtaque'] = 1.0;
                $meuTime[$i]['modDefesa'] = 1.0;
            }
            $_SESSION['meu_time'] = $meuTime;

            $combate['mensagens'] = [
                "Batalha {$combate['batalha_atual']} de {$combate['total_batalhas']} iniciada!",
                "Um novo adversário surgiu: {$novoInimigo['nome']} (Nível {$nivelInimigo})!"
            ];
        }
    }

    elseif ($acao === 'fugir') {
        header("Location: index.php?rota=menu");
        exit;
    }

    // -------------------------------------------------------------
    // AÇÃO: REINICIAR COMBATE
    // -------------------------------------------------------------
    elseif ($acao === 'reiniciar') {
        unset($_SESSION['combate']);
        header("Location: index.php?rota=batalha");
        exit;
    }
}

// 4. Preparação das Variáveis de Exibição
unset($pokemonJogador, $pokemonInimigo, $combate);
$combate = $_SESSION['combate'];

// Se o time foi curado (PokéMart/Mochila) depois de ter desmaiado por completo, retoma o combate
if (($combate['status'] ?? '') === 'derrota_sem_pokemon') {
    foreach ($meuTime as $pkVivo) {
        if ((int)($pkVivo['hpAtual'] ?? $pkVivo['hpMaximo'] ?? $pkVivo['hp'] ?? 40) > 0) {
            $_SESSION['combate']['status'] = 'em_combate';
            $_SESSION['combate']['menu_aberto'] = 'principal';
            $combate = $_SESSION['combate'];
            break;
        }
    }
    unset($pkVivo);
}
$batalhaAtual = (int)$combate['batalha_atual'];
$totalBatalhas = (int)$combate['total_batalhas'];
$ativoIdx = (int)$combate['pokemon_ativo_idx'];
$pokemonJogador = $meuTime[$ativoIdx] ?? $meuTime[0];
$pokemonInimigo = $combate['inimigo_atual'];

$hpMaxJog = (int)$pokemonJogador['hpMaximo'] ?? $pokemonJogador['hp'];
$hpAtualJog = (int)($pokemonJogador['hpAtual'] ?? $hpMaxJog);

$hpMaxIni = (int)$pokemonInimigo['hpMaximo'] ?? $pokemonInimigo['hp'];
$hpAtualIni = (int)($pokemonInimigo['hpAtual'] ?? $hpMaxIni);

$statusCombate = $combate['status'] ?? 'em_combate';
$menuAberto = $combate['menu_aberto'] ?? 'principal';
$mensagensLog = $combate['mensagens'] ?? [];

$nomeJog = $pokemonJogador['nome'] ?? $pokemonJogador['name'];
$nomeIni = $pokemonInimigo['nome'] ?? $pokemonInimigo['name'];

// Lê os movimentos armazenados no próprio Pokémon
$movimentosJog = $pokemonJogador['movimentos'] ?? PokeApiService::obterMovimentosPokemon((int)($pokemonJogador['id'] ?? 0), $nomeJog, $pokemonJogador['tipo'] ?? 'normal');

$porcHpJog = max(0, min(100, (int)round(($hpAtualJog / max(1, $hpMaxJog)) * 100)));
$porcHpIni = max(0, min(100, (int)round(($hpAtualIni / max(1, $hpMaxIni)) * 100)));

$corHpJog = ($porcHpJog > 50) ? '#10b981' : (($porcHpJog > 20) ? '#f59e0b' : '#ef4444');
$corHpIni = ($porcHpIni > 50) ? '#10b981' : (($porcHpIni > 20) ? '#f59e0b' : '#ef4444');

// IDs dos Pokémon para buscar os sprites na PokéAPI através do serviço
$idJog = (int)($pokemonJogador['id'] ?? $pokemonJogador['id_pokedex'] ?? 0);
$idIni = (int)($pokemonInimigo['id'] ?? $pokemonInimigo['id_pokedex'] ?? 0);

$spriteJogador = $idJog > 0 ? PokeApiService::buscarPorId($idJog) : null;
$spriteInimigo = $idIni > 0 ? PokeApiService::buscarPorId($idIni) : null;

$imgJog = $spriteJogador['gif'] ?? ($pokemonJogador['img'] ?? '');
$imgIni = $spriteInimigo['gif'] ?? ($pokemonInimigo['img'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Batalha <?= $batalhaAtual ?> de <?= $totalBatalhas ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/arena.css?v=<?= time() ?>">
</head>
<body>

    <div class="arena-viewport">
        <div class="arena-container">

            <!-- Cabeçalho (Mesmo padrão visual do Builder e Menu) -->
            <header class="arena-header-card">
                <div class="arena-brand">
                    <img src="assets/img/pokearena_title.png" alt="PokéArena" class="arena-logo">
                    <div class="arena-title-text">
                        <span class="arena-badge-title">BATALHA <?= $batalhaAtual ?> DE <?= $totalBatalhas ?></span>
                        <span class="arena-subtitle">Vitórias na Campanha: <b><?= $combate['vitorias'] ?></b></span>
                    </div>
                </div>

                <div class="arena-header-acoes">
                    <div class="treinador-badge">
                        Treinador: <b><?= htmlspecialchars($usuarioNome) ?></b>
                    </div>
                    <div class="saldo-badge">
                        Saldo: <b>P$ <?= number_format($usuarioDinheiro, 0, ',', '.') ?></b>
                    </div>
                    <a href="index.php?rota=menu" class="btn-header-nav btn-nav-blue">Menu</a>
                    <a href="index.php?rota=loja" class="btn-header-nav btn-nav-green">PokéMart</a>
                </div>
            </header>

            <?php if ($avisoErro): ?>
                <div style="background: rgba(239, 68, 68, 0.2); border: 1.5px solid #ef4444; color: #fca5a5; padding: 14px 20px; border-radius: 12px; font-weight: 700; text-align: center;">
                    <?= htmlspecialchars($avisoErro) ?>
                </div>
            <?php endif; ?>

            <!-- Card Principal da Arena (Mesmo padrão dark glass) -->
            <div class="arena-card">

                <!-- Campo de Batalha (Cenário Central) -->
                <div class="campo-batalha">
                    
                    <!-- LADO OPONENTE -->
                    <div class="lado lado-oponente">
                        <div class="caixa-status">
                            <div class="status-topo">
                                <span class="status-nome"><?= htmlspecialchars($nomeIni) ?></span>
                                <span class="status-nivel">Nv. <?= (int)($pokemonInimigo['nivel'] ?? 5) ?></span>
                            </div>
                            <div class="hp-linha">
                                <span class="hp-rotulo">HP</span>
                                <div class="hp-barra-bg">
                                    <div class="hp-barra" style="width: <?= $porcHpIni ?>%; background: <?= $corHpIni ?>;"></div>
                                </div>
                            </div>
                            <div class="hp-numeros">
                                <?= $hpAtualIni ?> / <?= $hpMaxIni ?> HP (<?= ucfirst($pokemonInimigo['tipo'] ?? 'normal') ?>)
                                <?php if ($pokemonInimigo['statusCondicao'] !== 'Normal'): ?>
                                    <span style="color: #f59e0b; font-weight: 800;">[<?= strtoupper(htmlspecialchars($pokemonInimigo['statusCondicao'])) ?>]</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sprite-box">
                            <?php if (!empty($imgIni)): ?>
                                <img src="<?= htmlspecialchars($imgIni, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($nomeIni) ?>">
                            <?php endif; ?>
                            <div class="sombra"></div>
                        </div>
                    </div>

                    <!-- LADO JOGADOR -->
                    <div class="lado lado-jogador">
                        <div class="sprite-box">
                            <?php if (!empty($imgJog)): ?>
                                <img src="<?= htmlspecialchars($imgJog, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($nomeJog) ?>">
                            <?php endif; ?>
                            <div class="sombra"></div>
                        </div>

                        <?php $ehEvoluido = !empty($pokemonJogador['evoluido']); ?>
                        <div class="caixa-status <?= $ehEvoluido ? 'caixa-evoluido' : '' ?>">
                            <div class="status-topo">
                                <span class="status-nome">
                                    <?= htmlspecialchars($nomeJog) ?>
                                    <?php if ($ehEvoluido): ?>
                                        <span class="badge-evoluido">✨ Evoluído</span>
                                    <?php endif; ?>
                                </span>
                                <span class="status-nivel">Nv. <?= (int)$pokemonJogador['nivel'] ?></span>
                            </div>
                            <div class="hp-linha">
                                <span class="hp-rotulo">HP</span>
                                <div class="hp-barra-bg">
                                    <div class="hp-barra" style="width: <?= $porcHpJog ?>%; background: <?= $corHpJog ?>;"></div>
                                </div>
                            </div>
                            <div class="hp-numeros">
                                <?= $hpAtualJog ?> / <?= $hpMaxJog ?> HP (<?= ucfirst($pokemonJogador['tipo'] ?? 'normal') ?>)
                                <?php if ($pokemonJogador['statusCondicao'] !== 'Normal'): ?>
                                    <span style="color: #f59e0b; font-weight: 800;">[<?= strtoupper(htmlspecialchars($pokemonJogador['statusCondicao'])) ?>]</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Painel Inferior: Log de Combate + Ações -->
                <div class="painel-combate">

                    <!-- Log da Batalha (Estilo Terminal RPG) -->
                    <div class="battle-log">
                        <div class="battle-log-header">Histórico da Batalha</div>
                        <?php if (empty($mensagensLog)): ?>
                            <p style="margin: 0; color: #94a3b8; font-size: 0.88rem;"><em>A batalha começou! Escolha uma ação abaixo.</em></p>
                        <?php else: ?>
                            <ul class="battle-log-list">
                                <?php foreach ($mensagensLog as $msg): ?>
                                    <?php 
                                        $cor = '#e2e8f0';
                                        if (str_contains($msg, 'super eficaz')) $cor = '#fbbf24';
                                        elseif (str_contains($msg, 'de dano')) $cor = '#34d399';
                                        elseif (str_contains($msg, 'recuperou')) $cor = '#38bdf8';
                                        elseif (str_contains($msg, 'desmaiou')) $cor = '#f43f5e';
                                        elseif (str_contains($msg, 'foi ') || str_contains($msg, 'adormeceu') || str_contains($msg, 'estar ')) $cor = '#f59e0b';
                                    ?>
                                    <li style="color: <?= $cor ?>;">• <?= htmlspecialchars($msg) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Painel de Ações / Comandos -->
                    <div class="painel-acoes">

                        <?php if ($statusCombate === 'vitoria_rodada'): ?>

                            <div class="banner-fim banner-vitoria">
                                <h3>🎉 Vitória na Batalha <?= $batalhaAtual ?>!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Você derrotou o <b><?= htmlspecialchars($nomeIni) ?></b>!</p>
                                <form method="POST" style="width: 100%;">
                                    <input type="hidden" name="acao" value="proxima_batalha">
                                    <button type="submit" class="btn-acao btn-trocar" style="font-size: 1rem;">
                                        Avançar para a Batalha <?= $batalhaAtual + 1 ?> de <?= $totalBatalhas ?> →
                                    </button>
                                </form>
                            </div>

                        <?php elseif ($statusCombate === 'campeao'): ?>

                            <div class="banner-fim banner-campeao">
                                <h3>🏆 GRANDE CAMPEÃO!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Parabéns! Você venceu todos os 9 adversários da PokéArena!</p>
                                <form method="POST" style="width: 100%;">
                                    <input type="hidden" name="acao" value="reiniciar">
                                    <button type="submit" class="btn-acao btn-lutar">Jogar Novamente</button>
                                </form>
                            </div>

                        <?php elseif ($statusCombate === 'derrota_sem_pokemon'): ?>

                            <div class="banner-fim banner-derrota">
                                <h3>💀 Toda a sua equipe desmaiou!</h3>
                                <p style="margin: 0; color: #cbd5e1;">Você precisa de poções ou curar seu time:</p>
                                <div style="display: flex; gap: 10px; width: 100%;">
                                    <a href="index.php?rota=loja" class="btn-acao btn-trocar" style="flex:1;">PokéMart</a>
                                    <a href="index.php?rota=centro" class="btn-acao btn-voltar" style="flex:1;">Centro</a>
                                </div>
                            </div>

                        <?php elseif ($statusCombate === 'em_combate'): ?>

                            <?php if ($hpAtualJog <= 0): ?>

                                <div class="banner-fim banner-derrota" style="padding: 16px;">
                                    <h4 style="margin: 0; color: #f87171;">Seu Pokémon desmaiou!</h4>
                                    <p style="margin: 0; color: #cbd5e1; font-size: 0.88rem;">Escolha outro Pokémon consciente na lista abaixo.</p>
                                </div>

                            <?php elseif ($menuAberto === 'principal'): ?>

                                <!-- Mini Menu de Ações (Estilo Oficial dos Botões do Menu) -->
                                <div class="acoes-grid">
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="acao" value="abrir_menu">
                                        <input type="hidden" name="menu_alvo" value="lutar">
                                        <button type="submit" class="btn-acao btn-lutar">⚔️ Lutar</button>
                                    </form>

                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="acao" value="abrir_menu">
                                        <input type="hidden" name="menu_alvo" value="trocar">
                                        <button type="submit" class="btn-acao btn-trocar">🔄 Trocar</button>
                                    </form>

                                    <form method="POST" style="grid-column: span 2; margin:0;">
                                        <input type="hidden" name="acao" value="fugir">
                                        <button type="submit" class="btn-acao btn-fugir" onclick="return confirm('Deseja fugir do combate?');">🏃 Fugir</button>
                                    </form>
                                </div>

                            <?php elseif ($menuAberto === 'lutar'): ?>

                                <!-- Submenu Lutar: Botões com visual estilo PokéRogue (CSS de Eficácia) -->
                                <div class="painel-lutar-pokeprogue">
                                    <div class="acoes-grid-golpes">
                                        <?php foreach (['fisico', 'especial', 'status'] as $chave): ?>
                                            <?php if (!empty($movimentosJog[$chave]['nome'])): ?>
                                                <?php
                                                    $mov = $movimentosJog[$chave];
                                                    $nomeGolpe = $mov['nome'];
                                                    $tipoGolpe = strtolower($mov['tipo'] ?? 'normal');
                                                    $catGolpe = strtolower($mov['damage_class'] ?? $mov['categoria'] ?? $chave);
                                                    $poderGolpe = (int)($mov['poder'] ?? 0);
                                                    $tipoInimigo = strtolower($pokemonInimigo['tipo'] ?? 'normal');

                                                    // Indicador visual de eficácia para o CSS (estilo PokéRogue)
                                                    if ($catGolpe === 'status') {
                                                        $badgeTexto = 'Efeito';
                                                        $badgeClasse = 'eficacia-status';
                                                        $badgeDesc = 'Movimento de Status';
                                                    } else {
                                                         // A vantagem de tipo vem da classe Batalha (traits)
                                                         $mult = Batalha::multiplicador($tipoGolpe, $tipoInimigo);

                                                        if ($mult >= 2.0) {
                                                            $badgeTexto = 'x' . (int)$mult;
                                                            $badgeClasse = 'eficacia-super';
                                                            $badgeDesc = 'Superfetivo (2x)';
                                                        } elseif ($mult == 1.0) {
                                                            $badgeTexto = 'x1';
                                                            $badgeClasse = 'eficacia-neutro';
                                                            $badgeDesc = 'Dano Normal (1x)';
                                                        } elseif ($mult > 0.0) {
                                                            $badgeTexto = 'x0.5';
                                                            $badgeClasse = 'eficacia-pouco';
                                                            $badgeDesc = 'Pouco Efetivo (0.5x)';
                                                        } else {
                                                            $badgeTexto = 'x0';
                                                            $badgeClasse = 'eficacia-imune';
                                                            $badgeDesc = 'Imune (0 dano)';
                                                        }
                                                    }
                                                ?>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="acao" value="executar_ataque">
                                                    <input type="hidden" name="tipo_ataque" value="<?= $chave ?>">
                                                    <button type="submit" class="btn-golpe-card">
                                                        <div class="golpe-topo">
                                                            <span class="golpe-nome"><?= htmlspecialchars($nomeGolpe) ?></span>
                                                            <span class="badge-eficacia <?= $badgeClasse ?>" title="<?= htmlspecialchars($badgeDesc) ?>">
                                                                <?= htmlspecialchars($badgeTexto) ?>
                                                            </span>
                                                        </div>
                                                        <div class="golpe-rodape">
                                                            <span class="badge-tipo-mini badge-tipo-<?= htmlspecialchars($tipoGolpe) ?>">
                                                                <?= strtoupper(htmlspecialchars($tipoGolpe)) ?>
                                                            </span>
                                                            <span class="golpe-poder-info">
                                                                <?php if ($catGolpe === 'status'): ?>
                                                                    Efeito
                                                                <?php else: ?>
                                                                    Poder: <b><?= $poderGolpe > 0 ? $poderGolpe : 40 ?></b>
                                                                <?php endif; ?>
                                                            </span>
                                                        </div>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <form method="POST" style="margin:0; grid-column: 1 / -1;">
                                            <input type="hidden" name="acao" value="abrir_menu">
                                            <input type="hidden" name="menu_alvo" value="principal">
                                            <button type="submit" class="btn-acao btn-voltar">← Voltar</button>
                                        </form>
                                    </div>
                                </div>

                            <?php elseif ($menuAberto === 'trocar'): ?>

                                <form method="POST">
                                    <input type="hidden" name="acao" value="abrir_menu">
                                    <input type="hidden" name="menu_alvo" value="principal">
                                    <button type="submit" class="btn-acao btn-voltar">← Voltar ao Combate</button>
                                </form>

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <!-- TABELA DE TROCA DE EQUIPE (ESTILO DARK GLASS DO BUILDER) -->
            <?php if ($menuAberto === 'trocar' || $hpAtualJog <= 0): ?>
                <div class="team-swap-panel">
                    <div class="team-swap-title">
                        Equipe Pokémon - Escolha quem entra em campo:
                    </div>
                    <table class="swap-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pokémon</th>
                                <th>Tipo</th>
                                <th>HP</th>
                                <th>Situação</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meuTime as $idx => $pk): ?>
                                <?php 
                                    $pkHpMax = (int)($pk['hpMaximo'] ?? $pk['hp'] ?? 40);
                                    $pkHpAtual = (int)($pk['hpAtual'] ?? $pkHpMax);
                                    $desmaiado = ($pkHpAtual <= 0);
                                    $emCampo = ($idx === $ativoIdx);
                                ?>
                                <tr>
                                    <td><strong><?= $idx + 1 ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($pk['nome'] ?? $pk['name']) ?></strong>
                                        <?php if (!empty($pk['evoluido'])): ?>
                                            <span style="color: #f59e0b; font-size: 0.75rem; font-weight: 800;">✨ [EVO]</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= ucfirst($pk['tipo'] ?? 'normal') ?></td>
                                    <td><?= $pkHpAtual ?> / <?= $pkHpMax ?></td>
                                    <td>
                                        <?php if ($desmaiado): ?>
                                            <span class="badge-status badge-desmaiado">Desmaiado</span>
                                        <?php elseif ($emCampo): ?>
                                            <span class="badge-status badge-ativo">Em Campo</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-pronto">Pronto</span>
                                        <?php endif; ?>
                                        <?php if (!$desmaiado && $pk['statusCondicao'] !== 'Normal'): ?>
                                            <span style="color: #f59e0b; font-weight: 800;">[<?= strtoupper(htmlspecialchars($pk['statusCondicao'])) ?>]</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($desmaiado): ?>
                                            <span style="color: #64748b;">Sem HP</span>
                                        <?php elseif ($emCampo): ?>
                                            <em>(Ativo)</em>
                                        <?php else: ?>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="acao" value="trocar">
                                                <input type="hidden" name="novo_idx" value="<?= $idx ?>">
                                                <button type="submit" class="btn-acao btn-trocar btn-entrar">Entrar em Campo</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div style="text-align: right; margin-top: 10px;">
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="acao" value="reiniciar">
                    <button type="submit" style="background: none; border: none; color: #64748b; text-decoration: underline; cursor: pointer; font-size: 0.82rem;" onclick="return confirm('Deseja reiniciar a campanha de 9 batalhas?');">
                        Reiniciar Campanha
                    </button>
                </form>
            </div>

        </div>
    </div>

</body>
</html>
