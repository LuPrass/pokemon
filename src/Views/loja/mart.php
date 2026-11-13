<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redireciona para o login se a sessão não estiver ativa
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../Autoload.php';

$pdo = Conexao::getConnection();

// Identifica o usuário logado
$usuarioId = $_SESSION['usuario_id'] ?? 1;
$usuarioNome = $_SESSION['usuario'] ?? 'Treinador';

// Busca dados atualizados do usuário (saldo em pokédollars)
$usuario = new Usuario();
$usuario->load($usuarioId);
$saldo = $usuario->getDinheiro() ?? 100;

$categorias = [
    'restaurador' => ['nome' => 'Restauradores de HP', 'icone' => '🧪'],
    'cura' => ['nome' => 'Cura de Status', 'icone' => '💊'],
    'reviver' => ['nome' => 'Reviver', 'icone' => '💖'],
    'evolucao' => ['nome' => 'Evolução', 'icone' => '✨']
];
$itensCatalogo = [];

foreach (Item::all($pdo) as $item) {
    $tipo = $item['tipo'];
    $categoria = $categorias[$tipo]['nome'] ?? ucfirst($tipo);
    $item['icone'] = $categorias[$tipo]['icone'] ?? '✨';
    $itensCatalogo[$categoria][] = $item;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - PokéMart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/mart.css?v=<?= time() ?>">
</head>
<body>

    <div class="mart-viewport">
        <div class="mart-container">

            <!-- Cabeçalho PokéMart -->
            <header class="mart-header-card">
                <div class="mart-brand">
                    <img src="assets/img/pokearena_title.png" alt="PokéArena" class="mart-logo">
                    <div class="mart-title-text">
                        <span class="mart-badge-title">POKÉMART - LOJA DE ITENS</span>
                        <span class="mart-welcome">Treinador: <b><?php echo htmlspecialchars($usuarioNome); ?></b></span>
                    </div>
                </div>

                <div class="mart-actions">
                    <div class="carteira-hud">
                        <span class="carteira-label">Saldo:</span>
                        <span class="carteira-valor" id="carteira-saldo">₽ <?php echo number_format($saldo, 0, ',', '.'); ?></span>
                    </div>
                    <a class="btn-nav-mart btn-nav-mochila" href="index.php?rota=mochila" style="background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);">🎒 Mochila</a>
                    <a class="btn-nav-mart btn-nav-batalha" href="index.php?rota=batalha">⚔ Batalha</a>
                    <a class="btn-nav-mart btn-nav-menu" href="index.php?rota=menu">← Menu</a>
                </div>
            </header>

            <!-- Vitrine de Itens Organizados por Categoria -->
            <main class="vitrine-itens">
                <?php foreach ($itensCatalogo as $categoria => $itens): ?>
                    <section class="secao-categoria">
                        <h2 class="categoria-titulo"><?php echo htmlspecialchars($categoria); ?></h2>
                        <div class="grid-itens">
                            <?php foreach ($itens as $item): ?>
                                <div class="item-card" id="card-item-<?php echo (int)($item['id'] ?? 0); ?>" data-item-id="<?php echo htmlspecialchars($item['id'] ?? ''); ?>" data-item-tipo="<?php echo htmlspecialchars($item['tipo']); ?>">
                                    <div>
                                        <div class="item-topo">
                                            <span class="item-icone"><?php echo $item['icone']; ?></span>
                                            <span class="item-nome"><?php echo htmlspecialchars($item['nome']); ?></span>
                                        </div>
                                        <div class="item-descricao">
                                            <?php echo htmlspecialchars($item['descricao']); ?>
                                        </div>
                                    </div>
                                    <div class="item-rodape">
                                        <span class="item-preco">₽ <?php echo number_format($item['preco'], 0, ',', '.'); ?></span>
                                        <button type="button" 
                                                class="btn-comprar" 
                                                data-id="<?php echo (int)($item['id'] ?? 0); ?>" 
                                                data-nome="<?php echo htmlspecialchars($item['nome']); ?>" 
                                                data-preco="<?php echo (int)$item['preco']; ?>"
                                                title="Comprar <?php echo htmlspecialchars($item['nome']); ?>">
                                            Comprar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </main>

        </div>
    </div>

    <!-- Container de Toasts de Notificação -->
    <div class="mart-toast-container" id="toast-container"></div>

    <!-- Script de Compra e Integração com a Mochila -->
    <script>
        // Dados do Treinador e Saldo Atual
        let saldoAtual = <?php echo (int)$saldo; ?>;
        const usuarioId = <?php echo (int)$usuarioId; ?>;

        const elementoSaldo = document.getElementById('carteira-saldo');
        const toastContainer = document.getElementById('toast-container');

        // Formata valor em padrão Pokédollars brasileiro (ex: 100 -> 100, 1500 -> 1.500)
        function formatarPokedollars(valor) {
            return new Intl.NumberFormat('pt-BR').format(valor);
        }

        // Exibe notificação toast na tela
        function exibirToast(tipo, titulo, mensagem) {
            const toast = document.createElement('div');
            toast.className = `mart-toast ${tipo}`;

            const icone = tipo === 'sucesso' ? '✅' : '⚠️';

            toast.innerHTML = `
                <span class="mart-toast-icone">${icone}</span>
                <div class="mart-toast-conteudo">
                    <strong>${titulo}</strong><br>
                    <span>${mensagem}</span>
                </div>
                <button type="button" class="mart-toast-fechar" aria-label="Fechar">&times;</button>
            `;

            toast.querySelector('.mart-toast-fechar').addEventListener('click', () => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(50px)';
                setTimeout(() => toast.remove(), 300);
            });

            toastContainer.appendChild(toast);

            // Auto-remove após 4.5 segundos
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(50px)';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 4500);
        }

        // Configura o evento de clique em todos os botões de compra
        document.querySelectorAll('.btn-comprar').forEach(btn => {
            btn.addEventListener('click', async () => {
                const itemId = parseInt(btn.getAttribute('data-id'), 10);
                const itemNome = btn.getAttribute('data-nome');
                const itemPreco = parseInt(btn.getAttribute('data-preco'), 10);
                const card = document.getElementById(`card-item-${itemId}`);

                // Validação imediata de saldo no Front-End
                if (saldoAtual < itemPreco) {
                    if (card) {
                        card.classList.remove('shake');
                        void card.offsetWidth; // Força reflow para reiniciar animação
                        card.classList.add('shake');
                    }
                    exibirToast(
                        'erro',
                        'Saldo Insuficiente!',
                        `Você possui ₽ ${formatarPokedollars(saldoAtual)}, mas ${itemNome} custa ₽ ${formatarPokedollars(itemPreco)}. Vença batalhas para ganhar mais dinheiro!`
                    );
                    return;
                }

                // Desabilita temporariamente o botão durante a requisição
                const textoOriginal = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Comprando...';

                try {
                    const resposta = await fetch('index.php?rota=comprar', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            usuario_id: usuarioId,
                            item_id: itemId
                        })
                    });

                    const dados = await resposta.json();

                    if (!resposta.ok || !dados.sucesso) {
                        throw new Error(dados.mensagem || 'Não foi possível concluir a compra.');
                    }

                    // Sucesso na compra! Atualiza o saldo local e na interface
                    saldoAtual = parseInt(dados.dinheiro, 10);
                    elementoSaldo.textContent = `₽ ${dados.saldo_formatado || formatarPokedollars(saldoAtual)}`;

                    // Efeito de sucesso na interface
                    exibirToast(
                        'sucesso',
                        'Compra Concluída!',
                        `Você comprou 1x ${itemNome}! O item já está disponível na sua Mochila.`
                    );

                } catch (erro) {
                    if (card) {
                        card.classList.remove('shake');
                        void card.offsetWidth;
                        card.classList.add('shake');
                    }
                    exibirToast('erro', 'Aviso', erro.message || 'Erro ao processar compra.');
                } finally {
                    btn.disabled = false;
                    btn.textContent = textoOriginal;
                }
            });
        });
    </script>
</body>
</html>
