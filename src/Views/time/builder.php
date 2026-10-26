<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../verifica_login.php';

// Processa o envio do time via AJAX/POST para guardar na Sessão do PHP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
        $_SESSION['meu_time'] = $data['meuTime'];
        $_SESSION['inimigos_restantes'] = $data['restantes'];
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'redirect' => 'batalha.php']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monte seu Time Pokémon</title>
    <link rel="stylesheet" href="assets/css/builder.css">
</head>
<body>

    <div class="builder-wrapper">

        <!-- Barra superior / Cabeçalho em tela cheia -->
        <header class="builder-header">
            <div class="titulo-box">
                <h1>Construção de Time</h1>
                <p>Escolha exatamente 3 Pokémon para a sua equipe de batalha</p>
            </div>
            <div class="builder-header-acoes">
                <?php if (!empty($_SESSION['usuario'])): ?>
                    <div class="treinador-badge">
                        Treinador: <b><?php echo htmlspecialchars($_SESSION['usuario']); ?></b>
                    </div>
                <?php endif; ?>
                <a href="index.php?rota=menu" class="btn-voltar">← Voltar ao Menu</a>
            </div>
        </header>

        <!-- Conteúdo Principal em Tela Cheia -->
        <div class="builder-conteudo">

            <!-- Painel do Time Selecionado -->
            <div class="team-panel">
                <h3>Seu Time (<span id="team-count">0</span>/3)</h3>
                <div class="team-slots" id="team-slots">
                    <div class="slot" title="Vaga 1"></div>
                    <div class="slot" title="Vaga 2"></div>
                    <div class="slot" title="Vaga 3"></div>
                </div>
                <button class="btn-ready" id="btn-ready" onclick="iniciarJogo()">Confirmar Time & Iniciar</button>
            </div>

            <!-- Grid com os Pokémon -->
            <div class="pokemon-grid" id="pokemon-grid"></div>

        </div>

        <!-- Rodapé em Barra de Largura Total -->
        <footer class="builder-footer">
            <span><b>PokeArena</b> - Team Builder Oficial</span>
            <a class="btn-voltar" href="index.php?rota=menu">← Menu Principal</a>
        </footer>

    </div>

    <script>
        // IDs dos 12 Pokémon selecionados
        const pokemonDisponiveis = [1, 252, 4, 255, 7, 258, 25, 179, 133, 143, 63, 92];
        let meuTime = [];
        const pokemonStatsCache = {};

        // Mapeamento e tradução visual dos tipos
        const MAPA_TIPOS = {
            'grass': { nome: 'Planta', icone: '🌿', classe: 'grass' },
            'fire': { nome: 'Fogo', icone: '🔥', classe: 'fire' },
            'water': { nome: 'Água', icone: '💧', classe: 'water' },
            'electric': { nome: 'Elétrico', icone: '⚡', classe: 'electric' },
            'normal': { nome: 'Normal', icone: '⚪', classe: 'normal' },
            'psychic': { nome: 'Psíquico', icone: '🔮', classe: 'psychic' },
            'ghost': { nome: 'Fantasma', icone: '👻', classe: 'ghost' },
            'poison': { nome: 'Venenoso', icone: '☠️', classe: 'poison' },
            'ground': { nome: 'Terra', icone: '🏜️', classe: 'ground' },
            'rock': { nome: 'Pedra', icone: '🪨', classe: 'rock' },
            'bug': { nome: 'Inseto', icone: '🐛', classe: 'bug' },
            'dragon': { nome: 'Dragão', icone: '🐉', classe: 'dragon' },
            'ice': { nome: 'Gelo', icone: '❄️', classe: 'ice' },
            'fighting': { nome: 'Lutador', icone: '🥊', classe: 'fighting' }
        };

        // Tradução dos atributos
        const traduzirStat = (name) => {
            const traducoes = { 
                'hp': 'HP', 
                'attack': 'Ataque', 
                'defense': 'Defesa', 
                'speed': 'Velocidade' 
            };
            return traducoes[name] || name;
        };

        // 1. Carrega os dados da PokéAPI
        async function carregarOpcoes() {
            const grid = document.getElementById('pokemon-grid');

            for (let id of pokemonDisponiveis) {
                try {
                    const response = await fetch(`https://pokeapi.co/api/v2/pokemon/${id}`);
                    if (!response.ok) {
                        throw new Error("Erro na requisição");
                    }
                    const poke = await response.json();
                    
                    const tipoPrincipal = poke.types[0]?.type?.name || 'normal';

                    // Armazena os atributos principais e tipo para facilitar o uso na batalha
                    pokemonStatsCache[poke.id] = {
                        tipo: tipoPrincipal,
                        tipos: poke.types.map(t => t.type.name),
                        hp: poke.stats.find(s => s.stat.name === 'hp')?.base_stat || 50,
                        attack: poke.stats.find(s => s.stat.name === 'attack')?.base_stat || 50,
                        defense: poke.stats.find(s => s.stat.name === 'defense')?.base_stat || 50,
                        speed: poke.stats.find(s => s.stat.name === 'speed')?.base_stat || 50
                    };

                    grid.innerHTML += criarCardHTML(poke);
                } catch (error) {
                    console.error("Erro ao buscar Pokémon ID " + id, error);
                }
            }
        }

        // 2. Constrói o HTML do Card personalizado por tipo
        function criarCardHTML(poke) {
            const imagem = poke.sprites.other['official-artwork'].front_default || poke.sprites.front_default;
            const tipoPrincipal = poke.types[0]?.type?.name || 'normal';
            const dadosTipo = MAPA_TIPOS[tipoPrincipal] || { nome: tipoPrincipal, icone: '✨', classe: tipoPrincipal };

            const statsDesejados = ['hp', 'attack', 'defense', 'speed'];
            let statsHTML = '';

            poke.stats.forEach(s => {
                if (statsDesejados.includes(s.stat.name)) {
                    const nomePt = traduzirStat(s.stat.name);
                    const porc = Math.min((s.base_stat / 150) * 100, 100);
                    const corBarra = s.stat.name === 'hp' ? '#2e7d32' : '#8259af';

                    statsHTML += `
                        <div style="margin-bottom: 8px;">
                            <div class="stat-row">
                                <strong>${nomePt}</strong>
                                <span>${s.base_stat}</span>
                            </div>
                            <div class="stat-bar-bg">
                                <div class="stat-bar-fill" style="width: ${porc}%; background: ${corBarra}"></div>
                            </div>
                        </div>
                    `;
                }
            });

            return `
                <div class="pokemon-card card-tipo-${dadosTipo.classe}" id="card-${poke.id}" onclick="alternarSelecao(${poke.id}, '${poke.name}', '${imagem}', '${tipoPrincipal}')">
                    <img src="${imagem}" alt="${poke.name}">
                    <h3 class="card-name">${poke.name}</h3>
                    <div class="badge-tipo-pill badge-pill-${dadosTipo.classe}">${dadosTipo.icone} ${dadosTipo.nome}</div>
                    <div class="stats-container">
                        ${statsHTML}
                    </div>
                </div>
            `;
        }

        // 3. Adiciona ou remove Pokémon do time
        function alternarSelecao(id, name, img, tipo) {
            const index = meuTime.findIndex(p => p.id === id);
            const cardEl = document.getElementById(`card-${id}`);

            if (index > -1) {
                meuTime.splice(index, 1);
                cardEl.classList.remove('selected');
            } else {
                if (meuTime.length >= 3) {
                    alert("Seu time já está cheio! Remova um Pokémon antes de adicionar outro.");
                    return;
                }
                const stats = pokemonStatsCache[id] || {};
                meuTime.push({ id, name, img, tipo: tipo || stats.tipo || 'normal', ...stats });
                cardEl.classList.add('selected');
            }

            atualizarPainelTime();
        }

        // 4. Atualiza os slots superiores
        function atualizarPainelTime() {
            document.getElementById('team-count').innerText = meuTime.length;
            const slots = document.getElementById('team-slots').children;

            for (let i = 0; i < 3; i++) {
                slots[i].innerHTML = '';
                if (meuTime[i]) {
                    slots[i].innerHTML = `<img src="${meuTime[i].img}" alt="${meuTime[i].name}">`;
                }
            }

            document.getElementById('btn-ready').style.display = meuTime.length === 3 ? 'inline-block' : 'none';
        }

        // 5. Separa os 9 Pokémon restantes e envia para a sessão / próxima página
        async function iniciarJogo() {
            const idsEscolhidos = meuTime.map(p => p.id);
            const idsRestantes = pokemonDisponiveis.filter(id => !idsEscolhidos.includes(id));

            // Guarda em sessionStorage no frontend como backup
            sessionStorage.setItem('meuTime', JSON.stringify(meuTime));
            sessionStorage.setItem('restantes', JSON.stringify(idsRestantes));

            // Salva na Sessão PHP via POST
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        meuTime: meuTime,
                        restantes: idsRestantes
                    })
                });

                const result = await response.json();
                if (result.status === 'success') {
                    window.location.href = result.redirect; // Redireciona para a tela de batalha
                }
            } catch (err) {
                // Fallback caso PHP não trate o redirecionamento direto
                window.location.href = 'batalha.php';
            }
        }

        // Inicializa o carregamento dos Pokémon ao abrir a página
        carregarOpcoes();
    </script>
</body>
</html>