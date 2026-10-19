/**
 * ==============================================================================
 * TimeManager.js
 * Gerenciador de Seleção do Time Pokémon
 * Regras:
 * - Adição APENAS ao clicar no botão "+ Adicionar ao Time"
 * - Remoção EXCLUSIVAMENTE ao clicar no botão "Remover"
 * - Pokémons selecionados aparecem com a mesma caixinha na área "Seu Time"
 * - Sem destaque roxo para os selecionados
 * ==============================================================================
 */

// 1. Variáveis de Estado
var time = [];
var LIMITE_MAXIMO = 3;

/**
 * PASSO 1: Inicialização do Time
 * Carrega a lista com os Pokémons já salvos no banco de dados.
 */
function inicializarTime(timeExistente) {
    if (Array.isArray(timeExistente)) {
        time = timeExistente;
    } else {
        time = [];
    }
    atualizarInterface();
}

/**
 * PASSO 2: Adicionar um Pokémon ao Time
 * Acionado EXCLUSIVAMENTE pelo clique no botão "+ Adicionar ao Time".
 */
function adicionarAoTime(pokemon) {
    // 2.1: Valida se o time já atingiu o limite de 3 Pokémons
    if (time.length >= LIMITE_MAXIMO) {
        alert("Seu time já está completo (máximo de 3 Pokémons). Remova um integrante antes de adicionar outro.");
        return;
    }

    // 2.2: Valida se o Pokémon já foi adicionado
    var jaExiste = time.some(function(item) {
        return item.nome.toLowerCase() === pokemon.nome.toLowerCase();
    });

    if (jaExiste) {
        alert(pokemon.nome + " já está no seu time!");
        return;
    }

    // 2.3: Adiciona o Pokémon ao array do time
    time.push(pokemon);

    // 2.4: Atualiza a interface
    atualizarInterface();
}

/**
 * PASSO 3: Remover Pokémon pelo Índice
 * Acionado ao clicar no botão '✕ Remover do Time' na caixinha da área "Seu Time".
 */
function removerPorIndice(indice) {
    if (indice >= 0 && indice < time.length) {
        time.splice(indice, 1);
        atualizarInterface();
    }
}

/**
 * PASSO 4: Remover Pokémon pelo Nome
 * Acionado ao clicar no botão '✕ Remover do Time' no card do catálogo.
 */
function removerPorNome(nome) {
    var indiceParaRemover = -1;

    for (var i = 0; i < time.length; i++) {
        if (time[i].nome.toLowerCase() === nome.toLowerCase()) {
            indiceParaRemover = i;
            break;
        }
    }

    if (indiceParaRemover !== -1) {
        time.splice(indiceParaRemover, 1);
        atualizarInterface();
    }
}

/**
 * PASSO 5: Atualizar toda a Interface Visual
 * Atualiza o contador de Pokémons, renderiza as caixinhas na área "Seu Time"
 * e sincroniza os botões das 20 caixas do catálogo.
 */
function atualizarInterface() {
    desenharContador();
    desenharCaixinhasSeuTime();
    atualizarCardsDoCatalogo();
}

/**
 * 5.1: Atualiza o contador de Pokémons (ex: 0/3, 1/3, 2/3, 3/3)
 */
function desenharContador() {
    var elementoContador = document.getElementById("contador");
    if (elementoContador) {
        elementoContador.innerText = time.length;
    }
}

/**
 * 5.2: Renderiza a mesma caixinha para cada Pokémon na área "Seu Time"
 */
function desenharCaixinhasSeuTime() {
    var containerSlots = document.getElementById("slotsTime");
    if (!containerSlots) return;

    if (time.length === 0) {
        containerSlots.innerHTML = '<div class="time-vazio-aviso">' +
            '<b>Nenhum Pokémon selecionado ainda.</b><br>' +
            '<small>Clique no botão "+ Adicionar ao Time" de qualquer Pokémon do catálogo abaixo para montar sua equipe.</small>' +
        '</div>';
        return;
    }

    var htmlFinal = "";

    // Para cada Pokémon selecionado, gera a mesma estrutura de caixinha
    time.forEach(function(pokemon, index) {
        var numFormatado = String(pokemon.id_pokedex || "").padStart(3, "0");
        var imgUrl = pokemon.imagemArtwork || pokemon.imagemFrente || "";

        // Monta a lista de ataques/golpes
        var golpesHtml = "";
        if (Array.isArray(pokemon.golpes)) {
            pokemon.golpes.forEach(function(g) {
                golpesHtml += '<span class="golpe-chip" title="Tipo: ' + g.tipo + ' | Poder: ' + g.poder + '">' + g.nome + '</span> ';
            });
        }

        htmlFinal += '' +
            '<div class="poke-card">' +
                '<div class="titulo-cartao">' +
                    '<div>' +
                        '<span class="numero-poke">#' + numFormatado + '</span>' +
                        '<div class="nome-poke">' + pokemon.nome + '</div>' +
                    '</div>' +
                    '<span class="tipo-' + pokemon.tipo + '">' + pokemon.tipo + '</span>' +
                '</div>' +

                '<div class="imagem-poke">' +
                    '<img src="' + imgUrl + '" alt="' + pokemon.nome + '">' +
                '</div>' +

                '<div class="poke-stats-grid">' +
                    '<div class="stat-item">' +
                        '<span class="stat-rotulo">HP:</span>' +
                        '<span class="stat-valor" style="color: #28a745;">' + pokemon.hpMaximo + '</span>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<span class="stat-rotulo">ATK:</span>' +
                        '<span class="stat-valor" style="color: #e53e3e;">' + pokemon.ataque + '</span>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<span class="stat-rotulo">DEF:</span>' +
                        '<span class="stat-valor" style="color: #dd6b20;">' + pokemon.defesa + '</span>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<span class="stat-rotulo">SPD:</span>' +
                        '<span class="stat-valor" style="color: #3182ce;">' + pokemon.velocidade + '</span>' +
                    '</div>' +
                '</div>' +

                '<div class="poke-golpes-box">' +
                    '<b>Ataques:</b>' +
                    '<div class="golpes-chips">' + golpesHtml + '</div>' +
                '</div>' +

                '<button type="button" class="btn-acao-card remover-card" onclick="removerPorIndice(' + index + ')">' +
                    '✕ Remover do Time' +
                '</button>' +
            '</div>';
    });

    containerSlots.innerHTML = htmlFinal;
}

/**
 * 5.3: Atualiza o estado dos botões das 20 caixas do catálogo
 */
function atualizarCardsDoCatalogo() {
    // 1. Reseta todos os botões do catálogo para "+ Adicionar ao Time"
    var todosBotoes = document.querySelectorAll("#catalogoPokemon .btn-acao-card");
    todosBotoes.forEach(function(btn) {
        var nome = btn.getAttribute("data-nome");
        btn.className = "btn-acao-card adicionar";
        btn.innerHTML = "+ Adicionar ao Time";
        btn.setAttribute("onclick", "adicionarAoTime(window.catalogoPokemons['" + nome + "'])");
    });

    // 2. Para os que estão no time, altera o botão para "✕ Remover do Time"
    time.forEach(function(pokemon) {
        var btn = document.getElementById("btn-" + pokemon.nome);
        if (btn) {
            btn.className = "btn-acao-card remover-card";
            btn.innerHTML = "✕ Remover do Time";
            btn.setAttribute("onclick", "removerPorNome('" + pokemon.nome + "')");
        }
    });
}

/**
 * PASSO 6: Fechar Time
 * Envia o time para o PHP salvar no banco de dados seguindo o padrão de requisição JSON.
 */
async function fecharTime() {
    if (time.length === 0) {
        alert("Por favor, selecione pelo menos 1 Pokémon para o seu time antes de fechar!");
        return;
    }

    const btnFechar = document.getElementById("btnFecharTime");
    const textoOriginal = btnFechar ? btnFechar.innerHTML : "✓ Fechar Time";

    try {
        if (btnFechar) {
            btnFechar.disabled = true;
            btnFechar.innerHTML = "Salvando Time...";
        }

        const dados = await salvarTime(usuarioId, time);
        console.log("Time salvo com sucesso:", dados);

        alert(dados.mensagem || "Time fechado com sucesso!");
        window.location.href = "index.php?rota=menu";

    } catch (erro) {
        console.error("Falha:", erro);
        alert("Não foi possível fechar o time: " + erro.message);
        if (btnFechar) {
            btnFechar.disabled = false;
            btnFechar.innerHTML = textoOriginal;
        }
    }
}
