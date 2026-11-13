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

// 1. Processamento da ação de Usar Item em um Pokémon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'usar_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $pokemonId = (int)($_POST['pokemon_id'] ?? 0);

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

    // Busca o Pokémon pertencente ao usuário
    $stmtPk = $pdo->prepare("SELECT * FROM pokemon WHERE id = :id AND usuario_id = :uid");
    $stmtPk->execute([':id' => $pokemonId, ':uid' => $usuarioId]);
    $pokeRow = $stmtPk->fetch(PDO::FETCH_ASSOC);

    if (!$pokeRow) {
        $_SESSION['msg_erro'] = "Pokémon não encontrado na sua equipe.";
        header("Location: index.php?rota=mochila");
        exit;
    }

    $nomeItem = $itemDados['nome'];
    $tipoItem = $itemDados['tipo'] ?? '';
    $hpAtual = (int)$pokeRow['hp_atual'];
    $hpMax = (int)$pokeRow['hp_maximo'];
    $statusAtual = $pokeRow['status_condicao'] ?? 'Normal';
    $nomePoke = $pokeRow['nome'];

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
        $stmtUp = $pdo->prepare("UPDATE pokemon SET hp_atual = :hp, status_condicao = :st WHERE id = :id");
        $stmtUp->execute([':hp' => $novoHp, ':st' => $novoStatus, ':id' => $pokemonId]);
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

        $stmtUp = $pdo->prepare("UPDATE pokemon SET hp_atual = :hp, status_condicao = :st WHERE id = :id");
        $stmtUp->execute([':hp' => $novoHp, ':st' => $novoStatus, ':id' => $pokemonId]);
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
            $stmtUp = $pdo->prepare("UPDATE pokemon SET status_condicao = 'Normal' WHERE id = :id");
            $stmtUp->execute([':id' => $pokemonId]);
            $sucessoUso = true;
            $msgResultado = "Full Heal curou a condição {$statusAtual} de {$nomePoke}!";
        } elseif (isset($curas[$nomeItem])) {
            $condicaoEsperada = $curas[$nomeItem];
            if (mb_strtolower($statusAtual, 'UTF-8') !== mb_strtolower($condicaoEsperada, 'UTF-8')) {
                $_SESSION['msg_erro'] = "{$nomeItem} só cura Pokémon {$condicaoEsperada}. O status atual de {$nomePoke} é {$statusAtual}.";
                header("Location: index.php?rota=mochila");
                exit;
            }
            $stmtUp = $pdo->prepare("UPDATE pokemon SET status_condicao = 'Normal' WHERE id = :id");
            $stmtUp->execute([':id' => $pokemonId]);
            $sucessoUso = true;
            $msgResultado = "{$nomeItem} curou a condição {$condicaoEsperada} de {$nomePoke}!";
        }
    }

    if ($sucessoUso) {
        // Desconta 1 unidade do item na mochila
        $_SESSION['mochila'][$indiceItem]['quantidade']--;
        if ($_SESSION['mochila'][$indiceItem]['quantidade'] <= 0) {
            unset($_SESSION['mochila'][$indiceItem]);
            $_SESSION['mochila'] = array_values($_SESSION['mochila']);
        }

        // Sincroniza $_SESSION['meu_time'] se existir
        if (!empty($_SESSION['meu_time'])) {
            foreach ($_SESSION['meu_time'] as &$pkS) {
                $idS = (int)($pkS['id'] ?? $pkS['pokemon_db_id'] ?? 0);
                if ($idS === $pokemonId) {
                    if (isset($novoHp)) {
                        $pkS['hpAtual'] = $novoHp;
                        $pkS['hp_atual'] = $novoHp;
                    }
                    if (isset($novoStatus)) {
                        $pkS['statusCondicao'] = $novoStatus;
                        $pkS['status_condicao'] = $novoStatus;
                    }
                    break;
                }
            }
            unset($pkS);
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

// 3. Busca o time atual do usuário (banco de dados ou sessão)
$meuTime = [];
if ($usuarioId > 0) {
    $stmtTime = $pdo->prepare("
        SELECT p.id, p.nome, p.tipo, p.nivel, p.hp_maximo, p.hp_atual, p.status_condicao
        FROM time t
        JOIN pokemon p ON t.pokemon_id = p.id
        WHERE t.usuario_id = :uid
        ORDER BY t.posicao ASC
    ");
    $stmtTime->execute([':uid' => $usuarioId]);
    $meuTime = $stmtTime->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($meuTime) && !empty($_SESSION['meu_time'])) {
    $meuTime = $_SESSION['meu_time'];
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
                                <button type="button" 
                                        class="btn-usar-tabela"
                                        data-item-id="<?php echo (int)($item['id'] ?? 0); ?>"
                                        data-item-nome="<?php echo htmlspecialchars($item['nome'] ?? ''); ?>"
                                        <?php if (empty($meuTime)): ?>disabled title="Você precisa formar um time no Builder antes de usar itens"<?php endif; ?>>
                                    Usar
                                </button>
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
    <section id="secao-usar-item">
        <h3>Usar Item em um Pokémon</h3>

        <?php if (!empty($meuTime)): ?>
            <form method="POST" action="index.php?rota=mochila" id="form-usar-item">
                <input type="hidden" name="acao" value="usar_item">

                <p>
                    <label for="item_alvo">Escolha o item da mochila:</label>
                    <select id="item_alvo" name="item_id" required <?php if (empty($itensMochila)) echo 'disabled'; ?>>
                        <?php if (empty($itensMochila)): ?>
                            <option value="">Nenhum item na mochila</option>
                        <?php else: ?>
                            <option value="">-- Selecione o item que deseja usar --</option>
                            <?php foreach ($itensMochila as $it): ?>
                                <option value="<?php echo (int)$it['id']; ?>">
                                    <?php echo htmlspecialchars($it['icone'] ?? '📦'); ?> <?php echo htmlspecialchars($it['nome']); ?> (x<?php echo (int)$it['quantidade']; ?>) - <?php echo htmlspecialchars($it['descricao']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </p>

                <p>
                    <label for="pokemon_alvo">Escolha o Pokémon da sua equipe:</label>
                    <select id="pokemon_alvo" name="pokemon_id" required>
                        <option value="">-- Selecione um Pokémon do seu time --</option>
                        <?php foreach ($meuTime as $indice => $poke): ?>
                            <?php
                                $pokeId = (int)($poke['id'] ?? $poke['pokemon_db_id'] ?? $indice);
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
                    <?php if (empty($itensMochila)): ?>
                        <button type="button" disabled title="Você não possui itens na mochila para usar">
                            Confirmar Uso do Item (Mochila Vazia)
                        </button>
                    <?php else: ?>
                        <button type="submit" id="btn_confirmar_uso">
                            Confirmar Uso do Item
                        </button>
                    <?php endif; ?>
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

    <script>
        // Integração dos botões "Usar" da tabela com o formulário
        document.querySelectorAll('.btn-usar-tabela').forEach(btn => {
            btn.addEventListener('click', () => {
                <?php if (empty($meuTime)): ?>
                // Se não tiver time formado não faz nada
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