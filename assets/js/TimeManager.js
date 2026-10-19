/**
 * ==============================================================================
 * TimeManager.js
 * Gerenciador de Seleção do Time Pokémon
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
 * Chamado ao clicar na imagem do Pokémon ou no botão "+ Adicionar ao Time".
 */
function adicionarAoTime(pokemon) {
    // 2.1: Valida se o time já está com 3 Pokémons
    if (time.length >= LIMITE_MAXIMO) {
        alert("Seu time já está cheio (máximo de 3 Pokémons). Remova um integrante para adicionar outro.");
        return;
    }

    // 2.2: Valida se o Pokémon já foi adicionado
    var jaExiste = time.some(function(item) {
        return item.nome.toLowerCase() === pokemon.nome.toLowerCase();
    });

    if (jaExiste) {
        alert(pokemon.nome + " já está no seu time! Para removê-lo, clique no botão 'Remover'.");
        return;
    }

    // 2.3: Insere o Pokémon no array do time
    time.push(pokemon);

    // 2.4: Atualiza a interface
    atualizarInterface();
}

/**
 * PASSO 3: Remover Pokémon pelo Índice (Slot 0, 1 ou 2)
 * Acionado ao clicar no botão '✕ Remover' dentro do slot no topo da tela.
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
 * Sincroniza o contador, as 3 vagas superiores e os botões das 20 caixas.
 */
function atualizarInterface() {
    desenharContador();
    desenharSlotsDoTime();
    atualizarCardsDoCatalogo();
}

/**
 * 5.1: Atualiza o contador de vagas preenchidas
 */
function desenharContador() {
    var elementoContador = document.getElementById("contador");
    if (elementoContador) {
        elementoContador.innerText = time.length;
    }
}

/**
 * 5.2: Renderiza os 3 slots no topo (mostrando botão Remover apenas nos ocupados)
 */
function desenharSlotsDoTime() {
    var containerSlots = document.getElementById("slotsTime");
    if (!containerSlots) return;

    var htmlFinal = "";

    for (var i = 0; i < LIMITE_MAXIMO; i++) {
        var pokemon = time[i];

        if (pokemon) {
            // Slot preenchido: exibe dados e botão de remoção
            var imgUrl = pokemon.imagemArtwork || pokemon.imagemFrente || "";
            htmlFinal += '' +
                '<div class="slot-card ocupado">' +
                    '<img src="' + imgUrl + '" alt="' + pokemon.nome + '">' +
                    '<div class="slot-info">' +
                        '<div class="slot-info-topo">' +
                            '<b>' + pokemon.nome + '</b>' +
                            '<span class="tipo-' + pokemon.tipo + '">' + pokemon.tipo + '</span>' +
                        '</div>' +
                        '<div class="slot-stats-resumo">' +
                            'HP: ' + pokemon.hpMaximo + ' | ATK: ' + pokemon.ataque + ' | DEF: ' + pokemon.defesa + ' | SPD: ' + pokemon.velocidade +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="btn-remover-slot" title="Remover do time" onclick="removerPorIndice(' + i + ')">' +
                        '✕ Remover' +
                    '</button>' +
                '</div>';
        } else {
            // Slot livre
            htmlFinal += '' +
                '<div class="slot-card vazio">' +
                    '<span class="slot-icone-add">+</span>' +
                    '<b>Vaga Disponível</b>' +
                    '<small style="font-size: 11px; margin-top: 2px;">Clique na imagem de um Pokémon</small>' +
                '</div>';
        }
    }

    containerSlots.innerHTML = htmlFinal;
}

/**
 * 5.3: Atualiza o estado visual das 20 caixas do catálogo
 */
function atualizarCardsDoCatalogo() {
    // Reseta todos os cards para o estado padrão
    var todosCards = document.querySelectorAll(".poke-card");
    todosCards.forEach(function(card) {
        card.classList.remove("no-time");
    });

    var todosBotoes = document.querySelectorAll(".btn-acao-card");
    todosBotoes.forEach(function(btn) {
        btn.className = "btn-acao-card adicionar";
        btn.innerHTML = "+ Adicionar ao Time";
    });

    // Configura os Pokémons que estão atualmente no time
    time.forEach(function(pokemon) {
        var card = document.getElementById("card-" + pokemon.nome);
        var btn = document.getElementById("btn-" + pokemon.nome);

        if (card) {
            card.classList.add("no-time");
        }

        if (btn) {
            btn.className = "btn-acao-card remover-card";
            btn.innerHTML = "✕ Remover do Time";
            // A remoção é acionada exclusivamente ao clicar no botão
            btn.setAttribute("onclick", "removerPorNome('" + pokemon.nome + "')");
        }
    });

    // Garante que os botões dos que não estão no time chamem adicionarAoTime
    document.querySelectorAll(".poke-card:not(.no-time) .btn-acao-card").forEach(function(btn) {
        var nome = btn.getAttribute("data-nome");
        if (nome && window.catalogoPokemons && window.catalogoPokemons[nome]) {
            btn.setAttribute("onclick", "adicionarAoTime(window.catalogoPokemons['" + nome + "'])");
        }
    });
}

/**
 * PASSO 6: Fechar Time
 * Salva a formação escolhida no banco de dados e conclui a montagem.
 */
async function fecharTime() {
    if (time.length === 0) {
        alert("Por favor, selecione pelo menos 1 Pokémon para o seu time antes de fechar!");
        return;
    }

    var btnFechar = document.getElementById("btnFecharTime");
    var textoOriginal = btnFechar ? btnFechar.innerHTML : "✓ Fechar Time";

    try {
        if (btnFechar) {
            btnFechar.disabled = true;
            btnFechar.innerHTML = "Salvando Time...";
        }

        var resultado = await salvarTime(usuarioId, time);

        alert(resultado.mensagem || "Time fechado com sucesso!");
        window.location.href = "index.php?rota=menu";

    } catch (erro) {
        alert("Não foi possível fechar o time: " + erro.message);
        if (btnFechar) {
            btnFechar.disabled = false;
            btnFechar.innerHTML = textoOriginal;
        }
    }
}
