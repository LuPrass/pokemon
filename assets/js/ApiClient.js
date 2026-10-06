// ApiClient.js
// Funções simples para falar com a PokéAPI e com o nosso PHP

var URL_API = "https://pokeapi.co/api/v2/pokemon/";

// Busca um pokémon na PokéAPI pelo nome ou número
async function buscarPokemon(nome) {
    var resposta = await fetch(URL_API + nome.toLowerCase().trim());

    if (resposta.ok == false) {
        throw new Error("Pokémon não encontrado!");
    }

    var dados = await resposta.json();

    // Pega só o que o nosso jogo precisa
    var pokemon = {
        nome: dados.name,
        tipo: dados.types[0].type.name,
        nivel: 5,
        hpMaximo: dados.stats[0].base_stat,
        hpAtual: dados.stats[0].base_stat,
        ataque: dados.stats[1].base_stat,
        defesa: dados.stats[2].base_stat,
        velocidade: dados.stats[5].base_stat,
        prioridade: 0,
        statusCondicao: "Normal",
        imagemArtwork: dados.sprites.other["official-artwork"].front_default,
        imagemFrente: dados.sprites.front_default,
        imagemCostas: dados.sprites.back_default
    };

    return pokemon;
}

// Envia o time para o PHP salvar no banco
async function salvarTime(usuarioId, time) {
    var resposta = await fetch("index.php?rota=salvar-time", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ usuario_id: usuarioId, time: time })
    });

    var resultado = await resposta.json();

    if (resultado.sucesso == false) {
        throw new Error(resultado.mensagem);
    }

    return resultado;
}
