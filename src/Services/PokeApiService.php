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

        $baseStats = [];
        foreach (($dados['stats'] ?? []) as $stat) {
            $nomeStat = $stat['stat']['name'] ?? null;
            $valor = $stat['base_stat'] ?? 0;
            if ($nomeStat !== null) {
                $baseStats[$nomeStat] = (int) $valor;
            }
        }

        // Pega só o que o nosso jogo precisa usando o nome do atributo da API,
        // evitando dependência da ordem de `stats[]`.
        $pokemon = [
            'id'         => $dados['id'],
            'nome'       => $dados['name'],
            'tipo'       => $dados['types'][0]['type']['name'],
            'nivel'      => 5,
            'hp'         => $baseStats['hp'] ?? 0,
            'ataque'     => $baseStats['attack'] ?? 0,
            'defesa'     => $baseStats['defense'] ?? 0,
            'velocidade' => $baseStats['speed'] ?? 0,
            'baseStats'  => [
                'hp' => $baseStats['hp'] ?? 0,
                'attack' => $baseStats['attack'] ?? 0,
                'defense' => $baseStats['defense'] ?? 0,
                'speed' => $baseStats['speed'] ?? 0,
            ],
            'imagem'     => $dados['sprites']['front_default'],
            'imagemCostas' => $dados['sprites']['back_default'],
            'imagemArtwork' => $dados['sprites']['other']['official-artwork']['front_default'] ?? $dados['sprites']['front_default']
        ];

        return $pokemon;
    }
}
