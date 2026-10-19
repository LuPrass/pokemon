/**
 * ApiClient.js
 * Funções para comunicação com a PokéAPI e com o backend PHP
 * Seguindo o padrão de requisições assíncronas com try/catch, fetch e JSON
 */

const URL_API = "https://pokeapi.co/api/v2/pokemon/";

// Busca um pokémon na PokéAPI pelo nome ou número
async function buscarPokemon(nome) {
    try {
        const resposta = await fetch(`${URL_API}${nome.toLowerCase().trim()}`);
        if (!resposta.ok) {
            throw new Error("Erro na requisição: Pokémon não encontrado");
        }
        const dados = await resposta.json();
        console.log("Pokémon:", dados);

        // Pega apenas as informações necessárias para o nosso jogo
        const pokemon = {
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
    } catch (erro) {
        console.error("Falha:", erro);
        throw erro;
    }
}

// Envia o time para o PHP salvar no banco de dados
async function salvarTime(usuarioId, time) {
    try {
        const resposta = await fetch("index.php?rota=salvar-time", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ usuario_id: usuarioId, time: time })
        });

        if (!resposta.ok) {
            throw new Error("Erro na requisição ao salvar time");
        }

        const dados = await resposta.json();
        console.log("Resposta PHP:", dados);

        if (dados.sucesso === false) {
            throw new Error(dados.mensagem || "Falha ao salvar time no servidor");
        }

        return dados;
    } catch (erro) {
        console.error("Falha:", erro);
        throw erro;
    }
}
