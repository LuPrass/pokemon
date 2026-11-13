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

// Verifica se o usuário já iniciou a batalha nesta sessão
$batalhaIniciada = !empty($_SESSION['combate']) || !empty($_SESSION['batalha_iniciada']);

// 1. Processamento da ação de Usar Item em um Pokémon
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'usar_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $pokemonId = (int)($_POST['pokemon_id'] ?? 0);

    // Valida se a batalha já foi iniciada
    if (!$batalhaIniciada || empty($_SESSION['meu_time']) || !is_array($_SESSION['meu_time'])) {
        $_SESSION['msg_erro'] = "Você precisa iniciar a batalha antes de poder usar itens na sua equipe.";
        header("Location: index.php?rota=mochila");
        exit;
    }

    // Localiza o item na mochila (sessão)
    $indiceItem = null;
    $itemDados = null;
    if (isset($_SESSION['mochila']) && is_array($_SESSION['mochila'])) {
        foreach ($_SESSION['mochila'] as $idx => $it) {
            if ((int)($it['id'] ?? 0) === $itemId) {
                $indiceItem = $idx;
                $itemDados = $it;
                break;
            }
        }
    }

    if ($itemDados === null || ($itemDados['quantidade'] ?? 0) <= 0) {
        $_SESSION['msg_erro'] = "Você não possui este item na mochila.";
        header("Location: index.php?rota=mochila");
        exit;
    }

    // Localiza o Pokémon na equipe ativa do usuário (busca por índice ou ID persistido)
    $pkSessao = null;
    $indicePkSessao = null;
    foreach ($_SESSION['meu_time'] as $idx => &$pkRef) {
        $dbIdRef = (int)($pkRef['pokemon_db_id'] ?? $pkRef['id'] ?? -1);
        if ($dbIdRef === $pokemonId || $idx === $pokemonId) {
            $pkSessao = &$pkRef;
            $indicePkSessao = $idx;
            break;
        }
    }
    unset($pkRef);

    if ($pkSessao === null) {
        $_SESSION['msg_erro'] = "Pokémon não encontrado na sua equipe ativa.";
        header("Location: index.php?rota=mochila");
        exit;
    }

    $nomeItem = $itemDados['nome'];
    $tipoItem = $itemDados['tipo'] ?? '';
    $nomePoke = $pkSessao['name'] ?? $pkSessao['nome'] ?? 'Pokémon';
    $hpMax = (int)($pkSessao['hpMaximo'] ?? $pkSessao['hp_maximo'] ?? 35);
    $hpAtual = (int)($pkSessao['hpAtual'] ?? $pkSessao['hp_atual'] ?? $hpMax);
    $statusAtual = $pkSessao['statusCondicao'] ?? $pkSessao['status_condicao'] ?? 'Normal';
    $pokemonDbId = (int)($pkSessao['pokemon_db_id'] ?? $pkSessao['id'] ?? 0);

    $sucessoUso = false;
    $msgResultado = '';

    if ($tipoItem === 'evolucao') {
        $_SESSION['msg_erro'] = "A evolução ainda não está disponível nesta versão.";
        header("Location: index.php?rota=mochila");
        exit;
    } elseif ($tipoItem === 'reviver') {
        if ($hpAtual > 0) {
            $_SESSION['msg_erro'] = "{$nomePoke} não está desmaiado! Revive só pode ser usado em Pokémon desmaiados.";
            header("Location: index.php?rota=mochila");
            exit;
        }
        $novoHp = (int)ceil($hpMax / 2);
        $novoStatus = 'Normal';
        $sucessoUso = true;
        $msgResultado = "{$nomePoke} foi revivido com {$novoHp} HP (50%)!";
    } elseif ($hpAtual <= 0) {
        $_SESSION['msg_erro'] = "{$nomePoke} está desmaiado! Use um Revive para reanimá-lo.";
        header("Location: index.php?rota=mochila");
        exit;
    } elseif ($tipoItem === 'restaurador') {
        if ($hpAtual >= $hpMax && $nomeItem !== 'Full Restore') {
            $_SESSION['msg_erro'] = "{$nomePoke} já está com a vida máxima ({$hpMax}/{$hpMax} HP)!";
            header("Location: index.php?rota=mochila");
            exit;
        }

        $novoHp = $hpAtual;
        $novoStatus = $statusAtual;

        if ($nomeItem === 'Potion') $novoHp = min($hpMax, $hpAtual + 20);
        elseif ($nomeItem === 'Super Potion') $novoHp = min($hpMax, $hpAtual + 50);
        elseif ($nomeItem === 'Hyper Potion') $novoHp = min($hpMax, $hpAtual + 200);
        elseif ($nomeItem === 'Max Potion') $novoHp = $hpMax;
        elseif ($nomeItem === 'Full Restore') {
            $novoHp = $hpMax;
            $novoStatus = 'Normal';
        } else {
            $novoHp = min($hpMax, $hpAtual + 20);
        }

        $cura = $novoHp - $hpAtual;
        $sucessoUso = true;
        $msgResultado = "{$nomeItem} recuperou +{$cura} HP de {$nomePoke} (HP: {$novoHp}/{$hpMax})!";
        if ($nomeItem === 'Full Restore' && $statusAtual !== 'Normal') {
            $msgResultado .= " E curou a condição {$statusAtual}!";
        }
    } elseif ($tipoItem === 'cura') {
        $curas = [
            'Antidote' => 'Envenenado',
            'Burn Heal' => 'Queimado',
            'Ice Heal' => 'Congelado',
            'Awakening' => 'Dormindo',
            'Paralyze Heal' => 'Paralisado'
        ];

        if ($nomeItem === 'Full Heal') {
            if ($statusAtual === 'Normal') {
                $_SESSION['msg_erro'] = "{$nomePoke} não possui nenhuma condição de status alterada!";
                header("Location: index.php?rota=mochila");
                exit;
            }
            $novoHp = $hpAtual;
            $novoStatus = 'Normal';
            $sucessoUso = true;
            $msgResultado = "Full Heal curou a condição {$statusAtual} de {$nomePoke}!";
        } elseif (isset($curas[$nomeItem])) {
            $condicaoEsperada = $curas[$nomeItem];
            if (mb_strtolower($statusAtual, 'UTF-8') !== mb_strtolower($condicaoEsperada, 'UTF-8')) {
                $_SESSION['msg_erro'] = "{$nomeItem} só cura Pokémon {$condicaoEsperada}. O status atual de {$nomePoke} é {$statusAtual}.";
                header("Location: index.php?rota=mochila");
                exit;
            }
            $novoHp = $hpAtual;
            $novoStatus = 'Normal';
            $sucessoUso = true;
            $msgResultado = "{$nomeItem} curou a condição {$condicaoEsperada} de {$nomePoke}!";
        }
    }

    if ($sucessoUso) {
        // Atualiza na sessão do time
        $_SESSION['meu_time'][$indicePkSessao]['hpAtual'] = $novoHp;
        $_SESSION['meu_time'][$indicePkSessao]['hp_atual'] = $novoHp;
        $_SESSION['meu_time'][$indicePkSessao]['statusCondicao'] = $novoStatus;
        $_SESSION['meu_time'][$indicePkSessao]['status_condicao'] = $novoStatus;

        // Atualiza no banco de dados se o Pokémon estiver persistido
        if ($pokemonDbId > 0) {
            $stmtUp = $pdo->prepare("UPDATE pokemon SET hp_atual = :hp, status_condicao = :st WHERE id = :id AND usuario_id = :uid");
            $stmtUp->execute([':hp' => $novoHp, ':st' => $novoStatus, ':id' => $pokemonDbId, ':uid' => $usuarioId]);
        }

        // Se estiver em combate ativo, reativa o combate e coloca o Pokémon revivido/curado em campo!
        if (isset($_SESSION['combate'])) {
            $_SESSION['combate']['status'] = 'em_combate';
            $_SESSION['combate']['menu_aberto'] = 'principal';

            $ativoIdxAtual = (int)($_SESSION['combate']['pokemon_ativo_idx'] ?? 0);
            $hpAtivo = (int)($_SESSION['meu_time'][$ativoIdxAtual]['hpAtual'] ?? 0);
            if ($hpAtivo <= 0 || $indicePkSessao === $ativoIdxAtual) {
                $_SESSION['combate']['pokemon_ativo_idx'] = $indicePkSessao;
            }
            $_SESSION['combate']['mensagens'][] = "✨ {$nomePoke} foi revivido e está em campo pronto para lutar!";
        }

        // Desconta 1 unidade do item na mochila
        $_SESSION['mochila'][$indiceItem]['quantidade']--;
        if ($_SESSION['mochila'][$indiceItem]['quantidade'] <= 0) {
            unset($_SESSION['mochila'][$indiceItem]);
            $_SESSION['mochila'] = array_values($_SESSION['mochila']);
        }

        $_SESSION['msg_sucesso'] = $msgResultado;
    }

    header("Location: index.php?rota=mochila");
    exit;
}

// 2. Itens da mochila (somente itens com quantidade > 0)
$itensMochila = array_filter($_SESSION['mochila'] ?? [], function($item) {
    return isset($item['quantidade']) && (int)$item['quantidade'] > 0;
});

// 3. Busca o time atual do usuário
// O time só fica disponível se o usuário já tiver iniciado a batalha
$meuTime = [];
if ($batalhaIniciada && !empty($_SESSION['meu_time']) && is_array($_SESSION['meu_time'])) {
    $meuTime = array_values($_SESSION['meu_time']);
}

// Mensagens de feedback
$msgSucesso = $_SESSION['msg_sucesso'] ?? '';
$msgErro = $_SESSION['msg_erro'] ?? '';
unset($_SESSION['msg_sucesso'], $_SESSION['msg_erro']);
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

    <?php if (!empty($msgSucesso)): ?>
        <div class="alerta alerta-sucesso">
            <p>✅ <?php echo htmlspecialchars($msgSucesso); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($msgErro)): ?>
        <div class="alerta alerta-erro">
            <p>⚠️ <?php echo htmlspecialchars($msgErro); ?></p>
        </div>
    <?php endif; ?>

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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Área de escolha de Pokémon para uso de item -->
    <?php if ($batalhaIniciada && !empty($meuTime) && !empty($itensMochila)): ?>
    <br>
    <hr>
    <section id="secao-usar-item">
        <h3>Usar Item em um Pokémon</h3>

        <form method="POST" action="index.php?rota=mochila" id="form-usar-item">
            <input type="hidden" name="acao" value="usar_item">

            <p>
                <label for="item_alvo">Escolha o item da mochila:</label>
                <select id="item_alvo" name="item_id" required>
                    <option value="">-- Selecione o item que deseja usar --</option>
                    <?php foreach ($itensMochila as $it): ?>
                        <option value="<?php echo (int)$it['id']; ?>">
                            <?php echo htmlspecialchars($it['icone'] ?? '📦'); ?> <?php echo htmlspecialchars($it['nome']); ?> (x<?php echo (int)$it['quantidade']; ?>) - <?php echo htmlspecialchars($it['descricao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="pokemon_alvo">Escolha o Pokémon da sua equipe:</label>
                <select id="pokemon_alvo" name="pokemon_id" required>
                    <option value="">-- Selecione um Pokémon do seu time --</option>
                    <?php foreach ($meuTime as $indice => $poke): ?>
                        <?php
                            $pokeId = (int)($poke['pokemon_db_id'] ?? $poke['id'] ?? $indice);
                            $nome = $poke['nome'] ?? $poke['name'] ?? "Pokémon #" . ($indice + 1);
                            $nivel = (int)($poke['nivel'] ?? 5);
                            $hpMax = (int)($poke['hp_maximo'] ?? $poke['hpMaximo'] ?? 35);
                            $hpAtual = (int)($poke['hp_atual'] ?? $poke['hpAtual'] ?? 0);
                            $status = $poke['status_condicao'] ?? $poke['statusCondicao'] ?? 'Normal';
                        ?>
                        <option value="<?php echo $pokeId; ?>">
                            <?php echo htmlspecialchars($nome); ?> (Nv. <?php echo $nivel; ?> | HP: <?php echo $hpAtual; ?>/<?php echo $hpMax; ?> | Status: <?php echo htmlspecialchars($status); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <button type="submit" id="btn_confirmar_uso">
                    Confirmar Uso do Item
                </button>
            </p>
        </form>
    </section>
    <?php endif; ?>

    <script>
        // Integração dos botões "Usar" da tabela com o formulário
        document.querySelectorAll('.btn-usar-tabela').forEach(btn => {
            btn.addEventListener('click', () => {
                <?php if (!$batalhaIniciada): ?>
                // Não faz nada enquanto a batalha não for iniciada
                return;
                <?php else: ?>
                const itemId = btn.getAttribute('data-item-id');
                const selectItem = document.getElementById('item_alvo');
                if (selectItem) {
                    selectItem.value = itemId;
                }
                const secaoUso = document.getElementById('secao-usar-item');
                if (secaoUso) {
                    secaoUso.scrollIntoView({ behavior: 'smooth' });
                }
                const selectPoke = document.getElementById('pokemon_alvo');
                if (selectPoke) {
                    selectPoke.focus();
                }
                <?php endif; ?>
            });
        });
    </script>

</body>
</html>
