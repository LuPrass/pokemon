<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Validações de acesso básicas
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['meu_time'])) {
    header('Location: index.php?rota=builder');
    exit;
}

if (!class_exists('Conexao')) {
    require_once __DIR__ . '/../../../Autoload.php';
}

require_once __DIR__ . '/../../Services/PokeApiService.php';

// Conexão com o banco de dados
$pdo = Conexao::getConnection();
$usuarioId = (int)($_SESSION['usuario_id'] ?? 1);

// Consulta o saldo atual de dinheiro do usuário no banco
$stmtUser = $pdo->prepare("SELECT nome, dinheiro FROM usuario WHERE id = :id");
$stmtUser->execute([':id' => $usuarioId]);
$dadosUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
$usuarioNome = $dadosUser['nome'] ?? ($_SESSION['usuario'] ?? 'Treinador');
$usuarioDinheiro = (int)($dadosUser['dinheiro'] ?? 1000);

// Sincroniza dados da equipe do jogador
$meuTime = array_values($_SESSION['meu_time']);

// 2. Inicialização da Campanha de 9 Batalhas (caso não exista)
if (empty($_SESSION['campanha']) || !isset($_SESSION['campanha']['batalha_atual'])) {
    $inimigosTime = array_values($_SESSION['inimigos_time'] ?? []);

    // Se a lista de 9 inimigos ainda não estiver montada, cria a partir dos IDs restantes do catálogo
    if (empty($inimigosTime)) {
        $idsEscolhidos = array_map(fn($p) => (int)($p['id'] ?? 0), $meuTime);
        $idsRestantes = array_values(array_diff(PokeApiService::IDS, $idsEscolhidos));
        $inimigosTime = array_map(function($id) {
            $info = PokeApiService::buscarPorId($id) ?? [];
            return [
                'id'       => $id,
                'nome'     => $info['nome'] ?? "Pokémon #{$id}",
                'tipo'     => $info['tipo'] ?? 'normal',
                'hpMaximo' => (int)($info['hp'] ?? 45),
                'hpAtual'  => (int)($info['hp'] ?? 45),
                'ataque'   => (int)($info['ataque'] ?? 40),
                'defesa'   => (int)($info['defesa'] ?? 40),
                'velocidade'=> (int)($info['velocidade'] ?? 45),
                'nivel'    => 5
            ];
        }, $idsRestantes);
        $_SESSION['inimigos_time'] = $inimigosTime;
    }

    // Sorteia 1 Pokémon do seu time para iniciar a primeira batalha (conforme a lógica solicitada)
    $indicesVivos = [];
    foreach ($meuTime as $idx => $pk) {
        $hp = (int)($pk['hpAtual'] ?? $pk['hpMaximo'] ?? $pk['hp'] ?? 40);
        if ($hp > 0) {
            $indicesVivos[] = $idx;
        }
    }
    $idxJogadorSorteado = !empty($indicesVivos) ? $indicesVivos[array_rand($indicesVivos)] : 0;

    // Sorteia 1 Pokémon dentro dos 9 restantes para a Batalha 1
    $inimigosPool = $inimigosTime;
    $idxInimigoSorteado = array_rand($inimigosPool);
    $inimigoAtual = $inimigosPool[$idxInimigoSorteado];
    unset($inimigosPool[$idxInimigoSorteado]);

    $_SESSION['campanha'] = [
        'batalha_atual'      => 1,
        'total_batalhas'     => 9,
        'vitorias'           => 0,
        'pokemon_ativo_idx'  => $idxJogadorSorteado,
        'inimigo_atual'      => $inimigoAtual,
        'inimigos_restantes' => array_values($inimigosPool),
        'status'             => 'em_combate', // 'em_combate', 'vitoria_rodada', 'campeao'
        'mensagens'          => [
            "Início da Campanha! Batalha 1 de 9.",
            "O seu Pokémon sorteado foi: " . ($meuTime[$idxJogadorSorteado]['nome'] ?? $meuTime[$idxJogadorSorteado]['name']) . "!",
            "O adversário sorteado entre os 9 restantes foi: " . ($inimigoAtual['nome'] ?? $inimigoAtual['name']) . "!"
        ]
    ];
}

// 3. Processamento das Ações em PHP Puro (POST)
$avisoErro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $campanha = &$_SESSION['campanha'];
    $ativoIdx = (int)$campanha['pokemon_ativo_idx'];
    $pokemonJogador = &$meuTime[$ativoIdx];
    $pokemonInimigo = &$campanha['inimigo_atual'];

    // Helper: função para calcular multiplicador de tipos elemental
    $calcularMultiplicador = function($tipoAtacante, $tipoDefensor) {
        $tipoAtacante = strtolower(trim($tipoAtacante));
        $tipoDefensor = strtolower(trim($tipoDefensor));

        $vantagens = [
            'fogo'     => ['planta' => 2.0, 'agua' => 0.5, 'fogo' => 0.5],
            'fire'     => ['grass' => 2.0, 'water' => 0.5, 'fire' => 0.5],
            'agua'     => ['fogo' => 2.0, 'planta' => 0.5, 'agua' => 0.5],
            'water'    => ['fire' => 2.0, 'grass' => 0.5, 'water' => 0.5],
            'planta'   => ['agua' => 2.0, 'fogo' => 0.5, 'planta' => 0.5],
            'grass'    => ['water' => 2.0, 'fire' => 0.5, 'grass' => 0.5],
            'eletrico' => ['agua' => 2.0, 'eletrico' => 0.5, 'terra' => 0.0],
            'electric' => ['water' => 2.0, 'electric' => 0.5, 'ground' => 0.0]
        ];

        return $vantagens[$tipoAtacante][$tipoDefensor] ?? 1.0;
    };

    // Helper: cálculo do dano numérico
    $calcularDano = function($atacante, $defensor) use ($calcularMultiplicador) {
        $nivel = (int)($atacante['nivel'] ?? 5);
        $atk = (int)($atacante['ataque'] ?? $atacante['attack'] ?? 40);
        $def = max(1, (int)($defensor['defesa'] ?? $defensor['defense'] ?? 40));
        $tipoAtk = $atacante['tipo'] ?? 'normal';
        $tipoDef = $defensor['tipo'] ?? 'normal';

        $base = ((($nivel * 2 / 5) + 2) * 40 * $atk / $def) / 50 + 2;
        $mult = $calcularMultiplicador($tipoAtk, $tipoDef);
        return max(1, (int)round($base * $mult));
    };

    // Helper: salvar o estado de HP do Pokémon do jogador no banco de dados
    $salvarHpBanco = function($pk) use ($pdo, $usuarioId) {
        $nomePk = $pk['nome'] ?? $pk['name'] ?? '';
        $hpAtual = (int)($pk['hpAtual'] ?? 0);
        if ($nomePk !== '') {
            $stmt = $pdo->prepare("UPDATE pokemon SET hp_atual = :hp WHERE usuario_id = :usuario_id AND LOWER(nome) = LOWER(:nome)");
            $stmt->execute([
                ':hp'         => $hpAtual,
                ':usuario_id' => $usuarioId,
                ':nome'       => $nomePk
            ]);
        }
    };

    // Helper: verificar se todos do time do jogador desmaiaram e checar condição de Game Over
    $verificarGameOver = function() use (&$meuTime, $usuarioDinheiro) {
        $todosDesmaiados = true;
        foreach ($meuTime as $pk) {
            $hp = (int)($pk['hpAtual'] ?? $pk['hpMaximo'] ?? $pk['hp'] ?? 40);
            if ($hp > 0) {
                $todosDesmaiados = false;
                break;
            }
        }

        if ($todosDesmaiados) {
            $custoMinimoCura = 200; // Valor de uma poção básica de recuperação
            if ($usuarioDinheiro < $custoMinimoCura) {
                // Game Over: Sem Pokémon e sem dinheiro para curar/recuperar!
                unset($_SESSION['campanha']);
                $_SESSION['aviso_derrota'] = "FIM DE JOGO: Todos os seus Pokémon desmaiaram e você não possui dinheiro suficiente para comprar HP ou curá-los! Você foi retirado da batalha.";
                header("Location: index.php?rota=menu");
                exit;
            }
        }
        return $todosDesmaiados;
    };

    // -------------------------------------------------------------
    // AÇÃO 1: ATACAR
    // -------------------------------------------------------------
    if ($acao === 'atacar' && $campanha['status'] === 'em_combate') {
        $campanha['mensagens'] = [];
        $nomeJogador = $pokemonJogador['nome'] ?? $pokemonJogador['name'];
        $nomeInimigo = $pokemonInimigo['nome'] ?? $pokemonInimigo['name'];

        // Jogador desfere ataque
        $danoJogador = $calcularDano($pokemonJogador, $pokemonInimigo);
        $hpInimigo = max(0, (int)($pokemonInimigo['hpAtual'] ?? $pokemonInimigo['hpMaximo'] ?? 45) - $danoJogador);
        $pokemonInimigo['hpAtual'] = $hpInimigo;

        $campanha['mensagens'][] = "{$nomeJogador} atacou {$nomeInimigo} e causou {$danoJogador} de dano! (HP restante do inimigo: {$hpInimigo})";

        // Verifica se o adversário foi derrotado
        if ($hpInimigo <= 0) {
            $campanha['mensagens'][] = "O {$nomeInimigo} selvagem desmaiou! Você venceu a Batalha {$campanha['batalha_atual']} de {$campanha['total_batalhas']}!";
            $campanha['vitorias']++;

            // Recompensa: +300 Pokédollars
            $recompensa = 300;
            $stmtMoney = $pdo->prepare("UPDATE usuario SET dinheiro = dinheiro + :recompensa WHERE id = :id");
            $stmtMoney->execute([':recompensa' => $recompensa, ':id' => $usuarioId]);
            $usuarioDinheiro += $recompensa;
            $campanha['mensagens'][] = "Recompensa: +{$recompensa} Pokédollars adicionados à sua conta!";

            // Registra no banco de dados na tabela log_batalha
            try {
                $stmtLog = $pdo->prepare("
                    INSERT INTO log_batalha (usuario_id, pokemon_id, adversario, resultado, dinheiro_ganho)
                    VALUES (:usuario_id, :pokemon_id, :adversario, 'vitoria', :dinheiro)
                ");
                $stmtLog->execute([
                    ':usuario_id' => $usuarioId,
                    ':pokemon_id' => $pokemonJogador['pokemon_db_id'] ?? null,
                    ':adversario' => $nomeInimigo,
                    ':dinheiro'   => $recompensa
                ]);
            } catch (Exception $e) {
                // Log opcional se a tabela permitir
            }

            // Verifica se completou as 9 batalhas
            if ($campanha['batalha_atual'] >= $campanha['total_batalhas']) {
                $campanha['status'] = 'campeao';
                $campanha['mensagens'][] = "PARABÉNS! Você derrotou todos os 9 adversários e consagrou-se o GRANDE CAMPEÃO da PokéArena!";
            } else {
                $campanha['status'] = 'vitoria_rodada';
            }

        } else {
            // Adversário contra-ataca
            $danoInimigo = $calcularDano($pokemonInimigo, $pokemonJogador);
            $hpJogador = max(0, (int)($pokemonJogador['hpAtual'] ?? $pokemonJogador['hpMaximo'] ?? 40) - $danoInimigo);
            $pokemonJogador['hpAtual'] = $hpJogador;
            $salvarHpBanco($pokemonJogador);

            $campanha['mensagens'][] = "{$nomeInimigo} contra-atacou {$nomeJogador} e causou {$danoInimigo} de dano! (Seu HP restante: {$hpJogador})";

            if ($hpJogador <= 0) {
                $campanha['mensagens'][] = "Seu Pokémon {$nomeJogador} desmaiou!";
                
                // Se todos desmaiarem e não tiver dinheiro -> GAME OVER com redirecionamento ao menu
                $todosDesmaiados = $verificarGameOver();
                if (!$todosDesmaiados) {
                    $campanha['mensagens'][] = "Seu Pokémon ativo foi derrotado. Troque por outro Pokémon consciente da sua equipe para continuar lutando!";
                } else {
                    $campanha['status'] = 'derrota_sem_pokemon';
                    $campanha['mensagens'][] = "Todos os seus Pokémon desmaiaram! Mas você ainda tem P$ {$usuarioDinheiro}. Vá até a Loja ou Centro Pokémon para recuperá-los.";
                }
            }
        }

        // Salva estado do time na sessão
        $_SESSION['meu_time'] = $meuTime;
    }

    // -------------------------------------------------------------
    // AÇÃO 2: TROCAR DE POKÉMON (Com validação estrita de desmaio)
    // -------------------------------------------------------------
    elseif ($acao === 'trocar' && ($campanha['status'] === 'em_combate' || $campanha['status'] === 'derrota_sem_pokemon')) {
        $novoIdx = isset($_POST['novo_idx']) ? (int)$_POST['novo_idx'] : -1;

        if (!isset($meuTime[$novoIdx])) {
            $avisoErro = "Pokémon inválido selecionado para troca!";
        } else {
            $candidato = $meuTime[$novoIdx];
            $hpCandidato = (int)($candidato['hpAtual'] ?? $candidato['hpMaximo'] ?? $candidato['hp'] ?? 40);

            // Regra crucial solicitada pelo usuário: não pode trocar se estiver desmaiado
            if ($hpCandidato <= 0) {
                $avisoErro = "O Pokémon " . ($candidato['nome'] ?? $candidato['name']) . " está desmaiado (HP = 0) e não pode entrar em combate!";
            } elseif ($novoIdx === $campanha['pokemon_ativo_idx']) {
                $avisoErro = "Este Pokémon já está na batalha!";
            } else {
                $campanha['pokemon_ativo_idx'] = $novoIdx;
                $campanha['status'] = 'em_combate';
                $nomeNovo = $candidato['nome'] ?? $candidato['name'];
                $nomeInimigo = $pokemonInimigo['nome'] ?? $pokemonInimigo['name'];
                $campanha['mensagens'] = ["Você trocou para {$nomeNovo}!"];

                // O adversário aproveita a troca para atacar o novo Pokémon que entrou
                $danoInimigo = $calcularDano($pokemonInimigo, $candidato);
                $hpRestante = max(0, $hpCandidato - $danoInimigo);
                $meuTime[$novoIdx]['hpAtual'] = $hpRestante;
                $salvarHpBanco($meuTime[$novoIdx]);

                $campanha['mensagens'][] = "{$nomeInimigo} atacou {$nomeNovo} na entrada e causou {$danoInimigo} de dano! (HP restante: {$hpRestante})";

                if ($hpRestante <= 0) {
                    $campanha['mensagens'][] = "{$nomeNovo} desmaiou logo na entrada!";
                    $verificarGameOver();
                }

                $_SESSION['meu_time'] = $meuTime;
            }
        }
    }

    // -------------------------------------------------------------
    // AÇÃO 3: PRÓXIMA BATALHA (Avança de 1 a 9 e sorteia o próximo)
    // -------------------------------------------------------------
    elseif ($acao === 'proxima_batalha' && $campanha['status'] === 'vitoria_rodada') {
        if (!empty($campanha['inimigos_restantes'])) {
            $campanha['batalha_atual']++;
            $idxSorteado = array_rand($campanha['inimigos_restantes']);
            $novoInimigo = $campanha['inimigos_restantes'][$idxSorteado];
            unset($campanha['inimigos_restantes'][$idxSorteado]);
            $campanha['inimigos_restantes'] = array_values($campanha['inimigos_restantes']);

            // Reinicia o HP do novo adversário sorteado
            $novoInimigo['hpAtual'] = (int)($novoInimigo['hpMaximo'] ?? $novoInimigo['hp'] ?? 45);
            $campanha['inimigo_atual'] = $novoInimigo;
            $campanha['status'] = 'em_combate';

            $campanha['mensagens'] = [
                "Batalha {$campanha['batalha_atual']} de {$campanha['total_batalhas']} iniciada!",
                "Um novo adversário sorteado surgiu: " . ($novoInimigo['nome'] ?? $novoInimigo['name']) . "!"
            ];
        }
    }

    // -------------------------------------------------------------
    // AÇÃO 4: RECUPERAR HP COM POÇÃO (Custo de P$ 200)
    // -------------------------------------------------------------
    elseif ($acao === 'comprar_pocao') {
        $custoPocao = 200;
        $curaValor = 20;

        if ($usuarioDinheiro < $custoPocao) {
            $avisoErro = "Você não possui dinheiro suficiente para comprar uma poção (Custo: P$ {$custoPocao})!";
        } else {
            $hpMax = (int)($pokemonJogador['hpMaximo'] ?? $pokemonJogador['hp'] ?? 40);
            $hpAtual = (int)($pokemonJogador['hpAtual'] ?? $hpMax);

            if ($hpAtual >= $hpMax) {
                $avisoErro = "O Pokémon já está com a vida cheia!";
            } else {
                $usuarioDinheiro -= $custoPocao;
                $stmtGasto = $pdo->prepare("UPDATE usuario SET dinheiro = :dinheiro WHERE id = :id");
                $stmtGasto->execute([':dinheiro' => $usuarioDinheiro, ':id' => $usuarioId]);

                $novoHp = min($hpMax, $hpAtual + $curaValor);
                $pokemonJogador['hpAtual'] = $novoHp;
                $salvarHpBanco($pokemonJogador);
                $_SESSION['meu_time'] = $meuTime;

                $campanha['mensagens'][] = "Você comprou e usou uma Poção por P$ 200! {$pokemonJogador['nome']} recuperou {$curaValor} de HP (Atual: {$novoHp}/{$hpMax}).";
            }
        }
    }

    // -------------------------------------------------------------
    // AÇÃO 5: REINICIAR CAMPANHA
    // -------------------------------------------------------------
    elseif ($acao === 'reiniciar') {
        unset($_SESSION['campanha']);
        header("Location: index.php?rota=batalha");
        exit;
    }
}

// 4. Preparação das Variáveis de Exibição
$campanha = $_SESSION['campanha'];
$batalhaAtual = (int)$campanha['batalha_atual'];
$totalBatalhas = (int)$campanha['total_batalhas'];
$ativoIdx = (int)$campanha['pokemon_ativo_idx'];
$pokemonJogador = $meuTime[$ativoIdx] ?? $meuTime[0];
$pokemonInimigo = $campanha['inimigo_atual'];

$hpMaxJog = (int)($pokemonJogador['hpMaximo'] ?? $pokemonJogador['hp'] ?? 40);
$hpAtualJog = (int)($pokemonJogador['hpAtual'] ?? $hpMaxJog);

$hpMaxIni = (int)($pokemonInimigo['hpMaximo'] ?? $pokemonInimigo['hp'] ?? 45);
$hpAtualIni = (int)($pokemonInimigo['hpAtual'] ?? $hpMaxIni);

$statusCampanha = $campanha['status'] ?? 'em_combate';
$mensagensLog = $campanha['mensagens'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>PokéArena - Batalha PHP Puro</title>
</head>
<body>

    <h1>PokéArena - Batalha PHP Puro</h1>
    <p>
        <strong>Treinador:</strong> <?= htmlspecialchars($usuarioNome) ?> | 
        <strong>Saldo:</strong> P$ <?= number_format($usuarioDinheiro, 0, ',', '.') ?> | 
        <a href="index.php?rota=menu">Voltar ao Menu Principal</a>
    </p>

    <hr>

    <h2>Progresso: Batalha <?= $batalhaAtual ?> de <?= $totalBatalhas ?></h2>
    <p>
        <strong>Adversários derrotados:</strong> <?= $campanha['vitorias'] ?> | 
        <strong>Adversários restantes no catálogo:</strong> <?= count($campanha['inimigos_restantes']) ?>
    </p>

    <?php if ($avisoErro): ?>
        <p><strong>[AVISO DE ERRO]:</strong> <?= htmlspecialchars($avisoErro) ?></p>
    <?php endif; ?>

    <hr>

    <!-- ÁREA DE COMBATE: POKÉMON ATIVO VS INIMIGO SORTEADO -->
    <table border="1" cellpadding="10" cellspacing="0" width="100%">
        <tr>
            <th width="50%">SEU POKÉMON (ATIVO)</th>
            <th width="50%">POKÉMON ADVERSÁRIO (SORTEADO)</th>
        </tr>
        <tr>
            <td valign="top">
                <h3><?= htmlspecialchars($pokemonJogador['nome'] ?? $pokemonJogador['name']) ?> (Nv. 5)</h3>
                <p><strong>Tipo:</strong> <?= ucfirst($pokemonJogador['tipo'] ?? 'normal') ?></p>
                <p><strong>HP:</strong> <?= $hpAtualJog ?> / <?= $hpMaxJog ?></p>
                <p><strong>Ataque:</strong> <?= $pokemonJogador['ataque'] ?? $pokemonJogador['attack'] ?? 40 ?> | <strong>Defesa:</strong> <?= $pokemonJogador['defesa'] ?? $pokemonJogador['defense'] ?? 40 ?></p>
                <p><strong>Status:</strong> <?= $hpAtualJog > 0 ? 'Consciente / Apto' : 'DESMAIADO' ?></p>
            </td>
            <td valign="top">
                <h3><?= htmlspecialchars($pokemonInimigo['nome'] ?? $pokemonInimigo['name']) ?> (Nv. 5)</h3>
                <p><strong>Tipo:</strong> <?= ucfirst($pokemonInimigo['tipo'] ?? 'normal') ?></p>
                <p><strong>HP:</strong> <?= $hpAtualIni ?> / <?= $hpMaxIni ?></p>
                <p><strong>Ataque:</strong> <?= $pokemonInimigo['ataque'] ?? 40 ?> | <strong>Defesa:</strong> <?= $pokemonInimigo['defesa'] ?? 40 ?></p>
                <p><strong>Status:</strong> <?= $hpAtualIni > 0 ? 'Consciente' : 'DERROTADO' ?></p>
            </td>
        </tr>
    </table>

    <hr>

    <!-- NARRADOR DE EVENTOS DA BATALHA -->
    <h3>Registro de Turnos / Mensagens:</h3>
    <ul>
        <?php foreach ($mensagensLog as $msg): ?>
            <li><?= htmlspecialchars($msg) ?></li>
        <?php endforeach; ?>
    </ul>

    <hr>

    <!-- AÇÕES DE COMBATE VIA FORMULÁRIOS PHP PURO -->
    <h3>Ações Disponíveis:</h3>

    <?php if ($statusCampanha === 'em_combate'): ?>

        <?php if ($hpAtualJog > 0): ?>
            <!-- Ação de Atacar -->
            <form method="POST" style="display: inline-block; margin-right: 15px;">
                <input type="hidden" name="acao" value="atacar">
                <button type="submit">⚔️ Atacar com <?= htmlspecialchars($pokemonJogador['nome'] ?? $pokemonJogador['name']) ?></button>
            </form>
        <?php else: ?>
            <p><strong>Seu Pokémon ativo desmaiou! Você precisa trocá-lo abaixo.</strong></p>
        <?php endif; ?>

        <!-- Ação de Recuperar HP (comprar poção com Pokédollars) -->
        <form method="POST" style="display: inline-block; margin-right: 15px;">
            <input type="hidden" name="acao" value="comprar_pocao">
            <button type="submit">🧪 Comprar e Usar Poção (+20 HP por P$ 200)</button>
        </form>

    <?php elseif ($statusCampanha === 'vitoria_rodada'): ?>

        <p><strong>Vitória! Você venceu a Batalha <?= $batalhaAtual ?>.</strong></p>
        <form method="POST">
            <input type="hidden" name="acao" value="proxima_batalha">
            <button type="submit">Avançar para a Próxima Batalha (<?= $batalhaAtual + 1 ?> de <?= $totalBatalhas ?>) →</button>
        </form>

    <?php elseif ($statusCampanha === 'campeao'): ?>

        <p><strong>🏆 PARABÉNS! VOCÊ COMPLETOU AS 9 BATALHAS E DERROTOU TODOS OS ADVERSÁRIOS!</strong></p>
        <form method="POST">
            <input type="hidden" name="acao" value="reiniciar">
            <button type="submit">Iniciar Nova Campanha de 9 Batalhas</button>
        </form>

    <?php elseif ($statusCampanha === 'derrota_sem_pokemon'): ?>

        <p><strong>Todos os seus Pokémon desmaiaram!</strong></p>
        <p>Você ainda tem P$ <?= $usuarioDinheiro ?>. Use a Loja ou Centro Pokémon para recuperá-los:</p>
        <p>
            <a href="index.php?rota=loja">Ir para a Loja (PokéMart)</a> | 
            <a href="index.php?rota=centro">Ir para o Centro Pokémon</a>
        </p>

    <?php endif; ?>

    <hr>

    <!-- SELEÇÃO E TROCA DE POKÉMON DA EQUIPE (Validação em PHP puro) -->
    <h3>Sua Equipe (3 Pokémon) - Troca a Qualquer Momento:</h3>
    <table border="1" cellpadding="8" cellspacing="0" width="100%">
        <tr>
            <th>Posição</th>
            <th>Nome</th>
            <th>Tipo</th>
            <th>HP Atual / Máximo</th>
            <th>Situação</th>
            <th>Ação</th>
        </tr>
        <?php foreach ($meuTime as $idx => $pk): ?>
            <?php 
                $pkHpMax = (int)($pk['hpMaximo'] ?? $pk['hp'] ?? 40);
                $pkHpAtual = (int)($pk['hpAtual'] ?? $pkHpMax);
                $desmaiado = ($pkHpAtual <= 0);
                $emCampo = ($idx === $ativoIdx);
            ?>
            <tr>
                <td><?= $idx + 1 ?></td>
                <td><strong><?= htmlspecialchars($pk['nome'] ?? $pk['name']) ?></strong></td>
                <td><?= ucfirst($pk['tipo'] ?? 'normal') ?></td>
                <td><?= $pkHpAtual ?> / <?= $pkHpMax ?></td>
                <td>
                    <?php if ($emCampo): ?>
                        <em>[EM CAMPO]</em>
                    <?php elseif ($desmaiado): ?>
                        <strong>[DESMAIADO]</strong>
                    <?php else: ?>
                        Pronto para Luta
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($emCampo): ?>
                        (Ativo)
                    <?php elseif ($desmaiado): ?>
                        <em>Bloqueado (HP = 0)</em>
                    <?php else: ?>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="acao" value="trocar">
                            <input type="hidden" name="novo_idx" value="<?= $idx ?>">
                            <button type="submit">Colocar em Campo</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <hr>

    <form method="POST">
        <input type="hidden" name="acao" value="reiniciar">
        <button type="submit" onclick="return confirm('Deseja reiniciar toda a campanha de 9 batalhas?');">Reiniciar Campanha do Início</button>
    </form>

</body>
</html>
