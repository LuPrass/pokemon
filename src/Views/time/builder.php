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
        // Lista dos IDs dos 15 Pokémon disponíveis
        const pokemonDisponiveis = <?php echo json_encode(PokeApiService::IDS); ?>;

        // Se o usuário veio da opção "Modificar Time", recupera o time atual
        <?php
        $timeInicial = [];
        if (!empty($_SESSION['meu_time']) && !empty($_GET['modificar'])) {
            $timeInicial = array_values($_SESSION['meu_time']);
        }
        ?>
        let meuTime = <?php echo json_encode($timeInicial); ?>;

        // Guarda os dados carregados da PokeAPI para reutilizar sem fazer novas requisições
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

        // Tradução simples dos atributos
        function traduzirStat(nome) {
            if (nome === 'hp') return 'HP';
            if (nome === 'attack') return 'Ataque';
            if (nome === 'defense') return 'Defesa';
            if (nome === 'speed') return 'Velocidade';
            return nome;
        }

        // 1. Busca os dados de um Pokémon na PokeAPI
        async function buscarPokemon(id) {
            const resposta = await fetch('https://pokeapi.co/api/v2/pokemon/' + id);
            const dados = await resposta.json();

            // Pega a imagem animada (showdown) ou a imagem padrão
            let imagem = '';
            if (dados.sprites.other && dados.sprites.other.showdown && dados.sprites.other.showdown.front_default) {
                imagem = dados.sprites.other.showdown.front_default;
            } else if (dados.sprites.front_default) {
                imagem = dados.sprites.front_default;
            }

            // Pega o tipo principal do Pokémon
            let tipo = 'normal';
            if (dados.types && dados.types.length > 0) {
                tipo = dados.types[0].type.name;
            }

            // Pega a lista de todos os tipos
            let tipos = [];
            for (let i = 0; i < dados.types.length; i++) {
                tipos.push(dados.types[i].type.name);
            }

            // Organiza os atributos em um objeto simples
            let stats = {};
            for (let i = 0; i < dados.stats.length; i++) {
                const item = dados.stats[i];
                stats[item.stat.name] = item.base_stat;
            }

            return {
                id: dados.id,
                nome: dados.name,
                imagem: imagem,
                tipo: tipo,
                tipos: tipos,
                stats: stats
            };
        }

        // 2. Carrega todos os Pokémon da lista e monta a tela
        async function carregarOpcoes() {
            const grid = document.getElementById('pokemon-grid');

            // Dispara a busca de todos os Pokémon da lista
            const promessas = pokemonDisponiveis.map(id => buscarPokemon(id));
            const pokemons = await Promise.all(promessas);

            // Adiciona cada Pokémon no cache e cria seu card na tela
            for (let i = 0; i < pokemons.length; i++) {
                const poke = pokemons[i];
                pokemonStatsCache[poke.id] = {
                    id: poke.id,
                    name: poke.nome,
                    img: poke.imagem,
                    tipo: poke.tipo,
                    tipos: poke.tipos,
                    hp: poke.stats.hp,
                    attack: poke.stats.attack,
                    defense: poke.stats.defense,
                    speed: poke.stats.speed
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

        // 3. Monta o card HTML de um Pokémon
        function criarCardHTML(poke) {
            const imagem = poke.imagem;
            const tipoPrincipal = poke.tipo;

            let dadosTipo = MAPA_TIPOS[tipoPrincipal];
            if (!dadosTipo) {
                dadosTipo = { nome: tipoPrincipal, icone: '✨', classe: tipoPrincipal };
            }

            const statsDesejados = ['hp', 'attack', 'defense', 'speed'];
            let statsHTML = '';

            for (let i = 0; i < statsDesejados.length; i++) {
                const nome = statsDesejados[i];
                const valor = poke.stats[nome];

                if (valor !== undefined) {
                    const nomePt = traduzirStat(nome);

                    let porc = (valor / 150) * 100;
                    if (porc > 100) {
                        porc = 100;
                    }

                    let corBarra = '#f59e0b'; // amarelo para velocidade
                    if (nome === 'hp') {
                        corBarra = '#10b981'; // verde
                    } else if (nome === 'attack') {
                        corBarra = '#f97316'; // laranja
                    } else if (nome === 'defense') {
                        corBarra = '#0ea5e9'; // azul
                    }

                    statsHTML += `
                        <div style="margin-bottom: 8px;">
                            <div class="stat-row">
                                <strong>${nomePt}</strong>
                                <span>${valor}</span>
                            </div>
                            <div class="stat-bar-bg">
                                <div class="stat-bar-fill" style="width: ${porc}%; background: ${corBarra}"></div>
                            </div>
                        </div>
                    `;
                }
            }

            // Verifica se este Pokémon já está no time
            let noTime = false;
            for (let i = 0; i < meuTime.length; i++) {
                if (Number(meuTime[i].id) === Number(poke.id)) {
                    noTime = true;
                    break;
                }
            }

            const classeCard = noTime ? 'selected' : '';
            const textoBtnAdd = noTime ? '✓ No Time' : '+ Adicionar';
            const desabilitarAdd = noTime ? 'disabled' : '';
            const desabilitarDel = noTime ? '' : 'disabled';

            return `
                <div class="pokemon-card card-tipo-${dadosTipo.classe} ${classeCard}" id="card-${poke.id}">
                    ${imagem ? `<img src="${imagem}" alt="${poke.nome}">` : ''}
                    <h3 class="card-name">${poke.nome}</h3>
                    <div class="badge-tipo-pill badge-pill-${dadosTipo.classe}">${dadosTipo.icone} ${dadosTipo.nome}</div>
                    <div class="stats-container">
                        ${statsHTML}
                    </div>
                    <div class="card-botoes">
                        <button type="button" class="btn-card btn-adicionar" id="btn-add-${poke.id}" onclick="adicionarAoTime(${poke.id}, '${poke.nome}', '${imagem}', '${tipoPrincipal}', event)" ${desabilitarAdd}>${textoBtnAdd}</button>
                        <button type="button" class="btn-card btn-excluir" id="btn-del-${poke.id}" onclick="removerDoTime(${poke.id}, event)" ${desabilitarDel}>✕ Excluir</button>
                    </div>
                </div>
            `;
        }

        // 4. Adiciona o Pokémon selecionado ao time
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

        // 5. Remove um Pokémon do time
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

        // 6. Atualiza a barra de slots do time no topo da página
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

        // 7. Envia o time escolhido e os adversários para o servidor salvar
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
            for (let i = 0; i < pokemonDisponiveis.length; i++) {
                const id = pokemonDisponiveis[i];
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

        // Inicia o carregamento dos Pokémon ao abrir a página
        carregarOpcoes();
    </script>
</body>
</html>
