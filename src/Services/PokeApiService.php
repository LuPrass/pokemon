<?php

// Serviço que busca dados de um pokémon na PokéAPI (lado do servidor)
class PokeApiService {

    private $urlBase = "https://pokeapi.co/api/v2/pokemon/";

    // Busca o pokémon e devolve um array com os dados (ou false se não achar)
    public function buscarPokemon($nome) {
        $url = $this->urlBase . strtolower(trim($nome));

        // No XAMPP o certificado HTTPS muitas vezes não está configurado,
        // então desligamos a verificação (ok para trabalho escolar)
        $opcoes = [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false
            ]
        ];
        $contexto = stream_context_create($opcoes);

        $json = @file_get_contents($url, false, $contexto);

        if ($json === false) {
            return false;
        }

        $dados = json_decode($json, true);

        // Pega só o que o nosso jogo precisa
        $pokemon = [
            'nome'       => $dados['name'],
            'tipo'       => $dados['types'][0]['type']['name'],
            'nivel'      => 5,
            'hp'         => $dados['stats'][0]['base_stat'],
            'ataque'     => $dados['stats'][1]['base_stat'],
            'defesa'     => $dados['stats'][2]['base_stat'],
            'velocidade' => $dados['stats'][5]['base_stat'],
            'imagem'     => $dados['sprites']['front_default'],
            'imagemCostas' => $dados['sprites']['back_default']
        ];

        return $pokemon;
    }
}
