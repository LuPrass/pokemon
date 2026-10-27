<?php

/**
 * PokeApiService
 * Centraliza a comunicação com a PokéAPI (lado do servidor)
 * e o catálogo dos Pokémons disponíveis para o jogo, incluindo cache em sessão e memória.
 */
class PokeApiService {

    // IDs da Pokédex dos 12 Pokémons disponíveis no catálogo do jogo
    const IDS = [1, 252, 4, 255, 7, 258, 25, 179, 133, 143, 63, 92];

    private $urlBase = "https://pokeapi.co/api/v2/pokemon/";

    // Cache em memória (durante a execução da requisição atual)
    private static $cache = [];

    /**
     * Busca dados brutos de um pokémon na PokéAPI pelo nome ou ID
     */
    public function buscarPokemon($nome) {
        $url = $this->urlBase . strtolower(trim((string)$nome));

        // No XAMPP o certificado HTTPS muitas vezes não está configurado,
        // então desligamos a verificação para o ambiente local
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

        // Pega as informações formatadas para o jogo
        $pokemon = [
            'id'            => $dados['id'],
            'nome'          => $dados['name'],
            'tipo'          => $dados['types'][0]['type']['name'] ?? 'normal',
            'nivel'         => 5,
            'hp'            => $baseStats['hp'] ?? 0,
            'ataque'        => $baseStats['attack'] ?? 0,
            'defesa'        => $baseStats['defense'] ?? 0,
            'velocidade'    => $baseStats['speed'] ?? 0,
            'baseStats'     => [
                'hp'        => $baseStats['hp'] ?? 0,
                'attack'    => $baseStats['attack'] ?? 0,
                'defense'   => $baseStats['defense'] ?? 0,
                'speed'     => $baseStats['speed'] ?? 0,
            ],
            'imagem'        => $dados['sprites']['front_default'] ?? '',
            'imagemCostas'  => $dados['sprites']['back_default'] ?? '',
            'imagemArtwork' => $dados['sprites']['other']['official-artwork']['front_default'] ?? ($dados['sprites']['front_default'] ?? '')
        ];

        return $pokemon;
    }

    /**
     * Busca um Pokémon do catálogo pelo ID com cache de memória e de sessão
     */
    public static function buscarPorId(int $id): ?array {
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['catalogo_pokeapi'][$id])) {
            return self::$cache[$id] = $_SESSION['catalogo_pokeapi'][$id];
        }

        $api  = new self();
        $info = $api->buscarPokemon((string)$id);

        if ($info === false) {
            return null; // API indisponível ou Pokémon não encontrado
        }

        $baseStats = [
            'hp'      => (int)($info['baseStats']['hp'] ?? $info['hp'] ?? 0),
            'attack'  => (int)($info['baseStats']['attack'] ?? $info['ataque'] ?? 0),
            'defense' => (int)($info['baseStats']['defense'] ?? $info['defesa'] ?? 0),
            'speed'   => (int)($info['baseStats']['speed'] ?? $info['velocidade'] ?? 0),
        ];

        $pokemon = [
            'id_pokedex'     => $info['id'],
            'nome'           => ucfirst($info['nome']),
            'tipo'           => $info['tipo'],
            'nivel'          => $info['nivel'],
            'hpMaximo'       => $baseStats['hp'],
            'hpAtual'        => $baseStats['hp'],
            'ataque'         => $baseStats['attack'],
            'defesa'         => $baseStats['defense'],
            'velocidade'     => $baseStats['speed'],
            'prioridade'     => 0,
            'statusCondicao' => 'Normal',
            'baseStats'      => $baseStats,
            'imagemArtwork'  => $info['imagemArtwork'],
            'imagemFrente'   => $info['imagem'],
            'imagemCostas'   => $info['imagemCostas'],
            'gif'            => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/showdown/' . $info['id'] . '.gif'
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['catalogo_pokeapi'][$id] = $pokemon;
        }

        return self::$cache[$id] = $pokemon;
    }

    /**
     * Lista todos os Pokémons disponíveis no catálogo
     */
    public static function listar(): array {
        $lista = [];
        foreach (self::IDS as $id) {
            $pokemon = self::buscarPorId($id);
            if ($pokemon !== null) {
                $lista[] = $pokemon;
            }
        }
        return $lista;
    }

    /**
     * Busca um Pokémon do catálogo pelo nome
     */
    public static function buscarPorNome(string $nome): ?array {
        $todos = self::listar();
        foreach ($todos as $p) {
            if (strcasecmp($p['nome'], $nome) === 0) {
                return $p;
            }
        }
        return null;
    }
}
