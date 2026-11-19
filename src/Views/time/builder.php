<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redireciona para o login se não estiver logado
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../Services/PokeApiService.php';

// Se já tem time e não está modificando, vai direto para a batalha
if (!empty($_SESSION['meu_time']) && empty($_GET['modificar'])) {
    header('Location: index.php?rota=batalha');
    exit;
}

// Carrega o catálogo dos 15 Pokémon diretamente pelo PokeApiService (Back-end)
// Assim, não fazemos requisições duplicadas para a PokeAPI pelo JavaScript
$catalogoPokemon = PokeApiService::listar();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Construtor de Time</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/builder.css?v=<?= time() ?>">
</head>
<body>

    <div class="builder-viewport">
        <div class="builder-container">

            <!-- Barra superior / Cabeçalho -->
            <header class="builder-header-card">
                <div class="builder-brand">
                    <img src="assets/img/pokearena_title.png" alt="PokéArena" class="builder-logo">
                    <div class="builder-title-text">
                        <span class="builder-badge-title">CONSTRUTOR DE EQUIPE</span>
                        <span class="builder-subtitle">Escolha exatamente 6 Pokémon para a sua equipe</span>
                    </div>
                </div>

                <div class="builder-header-acoes">
                    <?php if (!empty($_SESSION['usuario'])): ?>
                        <div class="treinador-badge">
                            Treinador: <b><?php echo htmlspecialchars($_SESSION['usuario']); ?></b>
                        </div>
                    <?php endif; ?>
                    <a href="index.php?rota=menu" class="btn-voltar">← Menu Principal</a>
                </div>
            </header>

            <!-- Painel do Time Selecionado -->
            <div class="team-panel">
                <h3>Seu Time (<span id="team-count">0</span>/6)</h3>
                <div class="team-slots" id="team-slots">
                    <div class="slot" id="slot-0"></div>
                    <div class="slot" id="slot-1"></div>
                    <div class="slot" id="slot-2"></div>
                    <div class="slot" id="slot-3"></div>
                    <div class="slot" id="slot-4"></div>
                    <div class="slot" id="slot-5"></div>
                </div>
                <button class="btn-ready" id="btn-ready" onclick="iniciarJogo()">Confirmar Time & Iniciar Batalha</button>
            </div>

            <!-- Grid com os Pokémon -->
            <div class="pokemon-grid" id="pokemon-grid"></div>

        </div>
    </div>

    <script>
        // O catálogo dos 15 Pokémon vem 100% pronto do PokeApiService (PHP)
        // Sem necessidade de chamar fetch() na PokéAPI pelo navegador
        const listaPokemon = <?php echo json_encode($catalogoPokemon); ?>;

        // Se o usuário veio da opção "Modificar Time", recupera o time atual
        <?php
        $timeInicial = [];
        if (!empty($_SESSION['meu_time']) && !empty($_GET['modificar'])) {
            $timeInicial = array_values($_SESSION['meu_time']);
        }
        ?>
        let meuTime = <?php echo json_encode($timeInicial); ?>;

        // Guarda os dados carregados para reutilizar sem recarregar a tela
        const pokemonStatsCache = {};

        // Mapeamento dos tipos para português, ícone e classe CSS
        const MAPA_TIPOS = {
            'grass':    { nome: 'Planta',   icone: '🌿', classe: 'grass' },
            'fire':     { nome: 'Fogo',     icone: '🔥', classe: 'fire' },
            'water':    { nome: 'Água',     icone: '💧', classe: 'water' },
            'electric': { nome: 'Elétrico', icone: '⚡', classe: 'electric' },
            'normal':   { nome: 'Normal',   icone: '⚪', classe: 'normal' },
            'psychic':  { nome: 'Psíquico', icone: '🔮', classe: 'psychic' },
            'ghost':    { nome: 'Fantasma', icone: '👻', classe: 'ghost' },
            'poison':   { nome: 'Venenoso', icone: '☠️', classe: 'poison' },
            'ground':   { nome: 'Terra',    icone: '🏜️', classe: 'ground' },
            'rock':     { nome: 'Pedra',    icone: '🪨', classe: 'rock' },
            'bug':      { nome: 'Inseto',   icone: '🐛', classe: 'bug' },
            'dragon':   { nome: 'Dragão',   icone: '🐉', classe: 'dragon' },
            'ice':      { nome: 'Gelo',     icone: '❄️', classe: 'ice' },
            'fighting': { nome: 'Lutador',  icone: '🥊', classe: 'fighting' }
        };

        // 1. Carrega todos os Pokémon na tela diretamente dos dados do PokeApiService
        function carregarOpcoes() {
            const grid = document.getElementById('pokemon-grid');

            for (let i = 0; i < listaPokemon.length; i++) {
                const poke = listaPokemon[i];
                const id = poke.id_pokedex;

                pokemonStatsCache[id] = {
                    id: id,
                    name: poke.nome,
                    img: poke.gif,
                    tipo: poke.tipo,
                    hp: poke.hpMaximo,
                    attack: poke.ataque,
                    defense: poke.defesa,
                    speed: poke.velocidade
                };

                grid.innerHTML += criarCardHTML(poke);
            }

            // Se já tínhamos um time salvo na sessão, atualiza as imagens
            for (let i = 0; i < meuTime.length; i++) {
                const id = Number(meuTime[i].id);
                if (pokemonStatsCache[id]) {
                    meuTime[i].img = pokemonStatsCache[id].img;
                }
            }

            atualizarPainelTime();
        }

        // 2. Monta o card HTML de cada Pokémon com suas informações e atributos
        function criarCardHTML(poke) {
            const id = poke.id_pokedex;
            const nome = poke.nome;
            const imagem = poke.gif;
            const tipoPrincipal = poke.tipo;

            let dadosTipo = MAPA_TIPOS[tipoPrincipal];
            if (!dadosTipo) {
                dadosTipo = { nome: tipoPrincipal, icone: '✨', classe: tipoPrincipal };
            }

            const statsLista = [
                { nome: 'HP', valor: poke.hpMaximo, cor: '#10b981' },
                { nome: 'Ataque', valor: poke.ataque, cor: '#f97316' },
                { nome: 'Defesa', valor: poke.defesa, cor: '#0ea5e9' },
                { nome: 'Velocidade', valor: poke.velocidade, cor: '#f59e0b' }
            ];

            let statsHTML = '';
            for (let i = 0; i < statsLista.length; i++) {
                const s = statsLista[i];
                let porc = (s.valor / 150) * 100;
                if (porc > 100) {
                    porc = 100;
                }

                statsHTML += `
                    <div style="margin-bottom: 8px;">
                        <div class="stat-row">
                            <strong>${s.nome}</strong>
                            <span>${s.valor}</span>
                        </div>
                        <div class="stat-bar-bg">
                            <div class="stat-bar-fill" style="width: ${porc}%; background: ${s.cor}"></div>
                        </div>
                    </div>
                `;
            }

            // Verifica se este Pokémon já está no time
            let noTime = false;
            for (let i = 0; i < meuTime.length; i++) {
                if (Number(meuTime[i].id) === Number(id)) {
                    noTime = true;
                    break;
                }
            }

            const classeCard = noTime ? 'selected' : '';
            const textoBtnAdd = noTime ? '✓ No Time' : '+ Adicionar';
            const desabilitarAdd = noTime ? 'disabled' : '';
            const desabilitarDel = noTime ? '' : 'disabled';

            return `
                <div class="pokemon-card card-tipo-${dadosTipo.classe} ${classeCard}" id="card-${id}">
                    ${imagem ? `<img src="${imagem}" alt="${nome}">` : ''}
                    <h3 class="card-name">${nome}</h3>
                    <div class="badge-tipo-pill badge-pill-${dadosTipo.classe}">${dadosTipo.icone} ${dadosTipo.nome}</div>
                    <div class="stats-container">
                        ${statsHTML}
                    </div>
                    <div class="card-botoes">
                        <button type="button" class="btn-card btn-adicionar" id="btn-add-${id}" onclick="adicionarAoTime(${id}, '${nome}', '${imagem}', '${tipoPrincipal}', event)" ${desabilitarAdd}>${textoBtnAdd}</button>
                        <button type="button" class="btn-card btn-excluir" id="btn-del-${id}" onclick="removerDoTime(${id}, event)" ${desabilitarDel}>✕ Excluir</button>
                    </div>
                </div>
            `;
        }

        // 3. Adiciona o Pokémon selecionado ao time
        function adicionarAoTime(id, name, img, tipo, event) {
            if (event) {
                event.stopPropagation();
            }

            // Verifica se o Pokémon já está no time
            for (let i = 0; i < meuTime.length; i++) {
                if (Number(meuTime[i].id) === Number(id)) {
                    return;
                }
            }

            // Limite de 6 Pokémon por time
            if (meuTime.length >= 6) {
                alert("Seu time já está cheio (máximo 6 Pokémon)! Remova um antes de adicionar outro.");
                return;
            }

            const stats = pokemonStatsCache[id];
            meuTime.push({
                id: id,
                name: name,
                img: img,
                tipo: tipo,
                hp: stats.hp,
                attack: stats.attack,
                defense: stats.defense,
                speed: stats.speed
            });

            // Atualiza os botões e visual do card
            const cardEl = document.getElementById('card-' + id);
            const btnAdd = document.getElementById('btn-add-' + id);
            const btnDel = document.getElementById('btn-del-' + id);

            if (cardEl) {
                cardEl.classList.add('selected');
            }
            if (btnAdd) {
                btnAdd.disabled = true;
                btnAdd.innerText = "✓ No Time";
            }
            if (btnDel) {
                btnDel.disabled = false;
            }

            atualizarPainelTime();
        }

        // 4. Remove um Pokémon do time
        function removerDoTime(id, event) {
            if (event) {
                event.stopPropagation();
            }

            let index = -1;
            for (let i = 0; i < meuTime.length; i++) {
                if (Number(meuTime[i].id) === Number(id)) {
                    index = i;
                    break;
                }
            }

            if (index === -1) {
                return;
            }

            meuTime.splice(index, 1);

            // Atualiza os botões e visual do card
            const cardEl = document.getElementById('card-' + id);
            const btnAdd = document.getElementById('btn-add-' + id);
            const btnDel = document.getElementById('btn-del-' + id);

            if (cardEl) {
                cardEl.classList.remove('selected');
            }
            if (btnAdd) {
                btnAdd.disabled = false;
                btnAdd.innerText = "+ Adicionar";
            }
            if (btnDel) {
                btnDel.disabled = true;
            }

            atualizarPainelTime();
        }

        // 5. Atualiza a barra de slots do time no topo da página
        function atualizarPainelTime() {
            document.getElementById('team-count').innerText = meuTime.length;
            const slots = document.getElementById('team-slots').children;

            for (let i = 0; i < 6; i++) {
                if (i < meuTime.length) {
                    const pokemon = meuTime[i];
                    slots[i].innerHTML = `
                        <img src="${pokemon.img}" alt="${pokemon.name}">
                        <span class="slot-nome">${pokemon.name}</span>
                    `;
                } else {
                    slots[i].innerHTML = '';
                }
            }

            // Exibe o botão de confirmação apenas quando tiver exatamente 6
            const btnReady = document.getElementById('btn-ready');
            if (meuTime.length === 6) {
                btnReady.style.display = 'inline-block';
            } else {
                btnReady.style.display = 'none';
            }
        }

        // 6. Envia o time escolhido e os adversários para o servidor salvar
        async function iniciarJogo() {
            const botaoConfirmar = document.getElementById('btn-ready');
            botaoConfirmar.disabled = true;

            // Pega os IDs escolhidos para o time
            let idsEscolhidos = [];
            for (let i = 0; i < meuTime.length; i++) {
                idsEscolhidos.push(meuTime[i].id);
            }

            // Identifica os outros Pokémon que serão os inimigos
            let idsRestantes = [];
            for (let i = 0; i < listaPokemon.length; i++) {
                const id = listaPokemon[i].id_pokedex;
                if (!idsEscolhidos.includes(id)) {
                    idsRestantes.push(id);
                }
            }

            // Monta a lista completa de adversários
            let inimigosCompletos = [];
            for (let i = 0; i < idsRestantes.length; i++) {
                const id = idsRestantes[i];
                const p = pokemonStatsCache[id];
                inimigosCompletos.push({
                    id: id,
                    nome: p.name,
                    img: p.img,
                    tipo: p.tipo,
                    nivel: 5,
                    hpMaximo: p.hp,
                    hpAtual: p.hp,
                    ataque: p.attack,
                    defesa: p.defense,
                    velocidade: p.speed,
                    prioridade: 0,
                    statusCondicao: 'Normal',
                    evoluido: 0,
                    modAtaque: 1.0,
                    modDefesa: 1.0
                });
            }

            // Monta a lista do nosso time com os valores corretos
            let timeParaSalvar = [];
            for (let i = 0; i < meuTime.length; i++) {
                const p = meuTime[i];
                const stats = pokemonStatsCache[p.id];
                timeParaSalvar.push({
                    id: p.id,
                    nome: p.name,
                    img: p.img,
                    tipo: p.tipo,
                    nivel: 5,
                    hpMaximo: stats.hp,
                    hpAtual: stats.hp,
                    ataque: stats.attack,
                    defesa: stats.defense,
                    velocidade: stats.speed,
                    prioridade: 0,
                    statusCondicao: 'Normal',
                    evoluido: 0,
                    modAtaque: 1.0,
                    modDefesa: 1.0
                });
            }

            try {
                const resposta = await fetch('index.php?rota=salvar-time', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        time: timeParaSalvar,
                        meuTime: timeParaSalvar,
                        restantes: idsRestantes,
                        inimigos: inimigosCompletos
                    })
                });

                const resultado = await resposta.json();
                if (resultado.sucesso) {
                    window.location.href = resultado.redirect;
                } else {
                    alert(resultado.mensagem);
                    botaoConfirmar.disabled = false;
                }
            } catch (erro) {
                alert('Erro ao salvar o time. Tente novamente.');
                botaoConfirmar.disabled = false;
            }
        }

        // Inicia o carregamento dos Pokémon direto dos dados já entregues pelo PHP
        carregarOpcoes();
    </script>
</body>
</html>
