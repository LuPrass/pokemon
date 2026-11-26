<?php

/**
 * PokeApiService
 * Centraliza a comunicação com a PokéAPI (lado do servidor)
 * e o catálogo dos Pokémons disponíveis para o jogo, incluindo cache em sessão e memória.
 */
class PokeApiService {

    // IDs da Pokédex dos Pokémons iniciais e evoluídos (3 de cada um dos 5 tipos)
    const IDS = [
        1, 252, 152, 4, 255, 155, 7, 258, 158, 27, 50, 104, 25, 179, 100,
        2, 253, 153, 5, 256, 156, 8, 259, 159, 28, 51, 105, 26, 180, 101
    ];

    // Mapeamento: ID do Pokémon base => ID da sua evolução correspondente
    const EVOLUCOES = [
        1   => 2,   // Bulbasaur  -> Ivysaur   (Planta)
        252 => 253, // Treecko    -> Grovyle   (Planta)
        152 => 153, // Chikorita  -> Bayleef   (Planta)
        4   => 5,   // Charmander -> Charmeleon (Fogo)
        255 => 256, // Torchic    -> Combusken  (Fogo)
        155 => 156, // Cyndaquil  -> Quilava    (Fogo)
        7   => 8,   // Squirtle   -> Wartortle  (Água)
        258 => 259, // Mudkip     -> Marshtomp  (Água)
        158 => 159, // Totodile   -> Croconaw   (Água)
        27  => 28,  // Sandshrew  -> Sandslash  (Terra)
        50  => 51,  // Diglett    -> Dugtrio    (Terra)
        104 => 105, // Cubone     -> Marowak    (Terra)
        25  => 26,  // Pikachu    -> Raichu     (Elétrico)
        179 => 180, // Mareep     -> Flaaffy    (Elétrico)
        100 => 101  // Voltorb    -> Electrode  (Elétrico)
    ];

    /**
     * Retorna o ID da evolução de um Pokémon a partir do seu ID base
     */
    public static function obterIdEvolucao($idPokedex): ?int {
        $idInt = (int)$idPokedex;
        if (isset(self::EVOLUCOES[$idInt])) {
            return self::EVOLUCOES[$idInt];
        }
        // Se buscar pela posição no array IDS (a evolução está 15 posições à frente)
        $pos = array_search($idInt, self::IDS);
        if ($pos !== false && $pos < 15 && isset(self::IDS[$pos + 15])) {
            return self::IDS[$pos + 15];
        }
        return null;
    }

    private $urlBase = "https://pokeapi.co/api/v2/pokemon/";

    // Cache em memória (durante a execução da requisição atual)
    private static $cache = [];
    private static $cachePokemon = [];

    /**
     * Retorna os 3 movimentos padronizados do jogo (1 Físico, 1 Especial, 1 Status)
     * determinados pelo tipo elemental do Pokémon, sem requisições lentas de rede.
     */
    public static function obterMovimentosPorTipo(string $tipoPokemon): array {
        $tipoNorm = strtolower(trim($tipoPokemon));

        $sinonimos = [
            'fogo'      => 'fire',
            'fire'      => 'fire',
            'agua'      => 'water',
            'água'      => 'water',
            'water'     => 'water',
            'planta'    => 'grass',
            'grass'     => 'grass',
            'terra'     => 'ground',
            'ground'    => 'ground',
            'eletrico'  => 'electric',
            'elétrico'  => 'electric',
            'electric'  => 'electric'
        ];
        if (isset($sinonimos[$tipoNorm])) {
            $tipoNorm = $sinonimos[$tipoNorm];
        }

        $catalogo = [
            'fire' => [
                'fisico' => [
                    'nome'         => 'Investida Feroz',
                    'damage_class' => 'physical',
                    'categoria'    => 'physical',
                    'tipo'         => 'fire',
                    'poder'        => 40,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Pancada corporal direta contra o oponente.'
                ],
                'especial' => [
                    'nome'         => 'Lança-Chamas',
                    'damage_class' => 'special',
                    'categoria'    => 'special',
                    'tipo'         => 'fire',
                    'poder'        => 60,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Jato intenso de chamas que explora fraquezas elementais.'
                ],
                'status' => [
                    'nome'         => 'Fogo-Fátuo',
                    'damage_class' => 'status',
                    'categoria'    => 'status',
                    'tipo'         => 'fire',
                    'poder'        => 0,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'efeito'       => 'queimar',
                    'desc'         => 'Chamas sinistras que causam Queimadura no oponente.'
                ]
            ],
            'water' => [
                'fisico' => [
                    'nome'         => 'Pancada Aquática',
                    'damage_class' => 'physical',
                    'categoria'    => 'physical',
                    'tipo'         => 'water',
                    'poder'        => 40,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Golpe físico vigoroso contra o oponente.'
                ],
                'especial' => [
                    'nome'         => 'Hidro Bomba',
                    'damage_class' => 'special',
                    'categoria'    => 'special',
                    'tipo'         => 'water',
                    'poder'        => 60,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Jato torrencial de água sob alta pressão.'
                ],
                'status' => [
                    'nome'         => 'Gota Congelante',
                    'damage_class' => 'status',
                    'categoria'    => 'status',
                    'tipo'         => 'water',
                    'poder'        => 0,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'efeito'       => 'congelar',
                    'desc'         => 'Resfriamento repentino que congela e paralisa o adversário.'
                ]
            ],
            'grass' => [
                'fisico' => [
                    'nome'         => 'Chicote de Vinha',
                    'damage_class' => 'physical',
                    'categoria'    => 'physical',
                    'tipo'         => 'grass',
                    'poder'        => 40,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Chicoteia o oponente com vinhas rígidas e resistentes.'
                ],
                'especial' => [
                    'nome'         => 'Folha Navalha',
                    'damage_class' => 'special',
                    'categoria'    => 'special',
                    'tipo'         => 'grass',
                    'poder'        => 60,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Lança folhas afiadas com o poder elemental da natureza.'
                ],
                'status' => [
                    'nome'         => 'Pó Venenoso',
                    'damage_class' => 'status',
                    'categoria'    => 'status',
                    'tipo'         => 'grass',
                    'poder'        => 0,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'efeito'       => 'envenenar',
                    'desc'         => 'Espalha um pó tóxico que envenena o adversário.'
                ]
            ],
            'electric' => [
                'fisico' => [
                    'nome'         => 'Ataque Rápido',
                    'damage_class' => 'physical',
                    'categoria'    => 'physical',
                    'tipo'         => 'electric',
                    'poder'        => 40,
                    'precisao'     => 100,
                    'prioridade'   => 1,
                    'desc'         => 'Investida relâmpago veloz com prioridade no turno.'
                ],
                'especial' => [
                    'nome'         => 'Choque do Trovão',
                    'damage_class' => 'special',
                    'categoria'    => 'special',
                    'tipo'         => 'electric',
                    'poder'        => 60,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Descarga elétrica poderosa que atinge o adversário.'
                ],
                'status' => [
                    'nome'         => 'Onda de Trovão',
                    'damage_class' => 'status',
                    'categoria'    => 'status',
                    'tipo'         => 'electric',
                    'poder'        => 0,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'efeito'       => 'paralisar',
                    'desc'         => 'Onda eletrizante que paralisa e corta a velocidade do alvo.'
                ]
            ],
            'ground' => [
                'fisico' => [
                    'nome'         => 'Mordida Pesada',
                    'damage_class' => 'physical',
                    'categoria'    => 'physical',
                    'tipo'         => 'ground',
                    'poder'        => 40,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Mordida vigorosa com todo o peso do solo.'
                ],
                'especial' => [
                    'nome'         => 'Tiro de Lama',
                    'damage_class' => 'special',
                    'categoria'    => 'special',
                    'tipo'         => 'ground',
                    'poder'        => 60,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'desc'         => 'Dispara terra e lama elemental em alta velocidade.'
                ],
                'status' => [
                    'nome'         => 'Ataque de Areia',
                    'damage_class' => 'status',
                    'categoria'    => 'status',
                    'tipo'         => 'ground',
                    'poder'        => 0,
                    'precisao'     => 100,
                    'prioridade'   => 0,
                    'efeito'       => 'reduzir_atk',
                    'desc'         => 'Joga areia no adversário para enfraquecer o seu poder de ataque.'
                ]
            ]
        ];

        return $catalogo[$tipoNorm] ?? [];
    }

    /**
     * Busca dados brutos de um pokémon na PokéAPI pelo nome ou ID
     */
    public function buscarPokemon($nome) {
        $chave = strtolower(trim((string)$nome));
        if ($chave === '') {
            return false;
        }

        if (isset(self::$cachePokemon[$chave])) {
            return self::$cachePokemon[$chave];
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['pokemon_api'][$chave])) {
            return self::$cachePokemon[$chave] = $_SESSION['pokemon_api'][$chave];
        }

        $dados = $this->requisitarDadosPokemon($chave);
        if ($dados === null) {
            return false;
        }

        $baseStats = [];
        foreach (($dados['stats'] ?? []) as $stat) {
            $nomeStat = $stat['stat']['name'] ?? null;
            $valor = $stat['base_stat'] ?? 0;
            if ($nomeStat !== null) {
                $baseStats[$nomeStat] = (int) $valor;
            }
        }

        $tipoPrincipal = $dados['types'][0]['type']['name'] ?? '';
        $gif = $dados['sprites']['other']['showdown']['front_default'] ?? '';

        // Pega as informações formatadas para o jogo
        $pokemon = [
            'id'            => $dados['id'],
            'nome'          => $dados['name'],
            'tipo'          => $tipoPrincipal,
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
            'gif'           => $gif,
            'movimentos'    => self::obterMovimentosPorTipo($tipoPrincipal)
        ];

        $chavesCache = array_unique([$chave, (string)$dados['id'], strtolower($dados['name'])]);
        foreach ($chavesCache as $chaveCache) {
            self::$cachePokemon[$chaveCache] = $pokemon;
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['pokemon_api'][$chaveCache] = $pokemon;
            }
        }

        return $pokemon;
    }

    private function requisitarDadosPokemon(string $chave): ?array {
        $opcoes = [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false
            ],
            'http' => [
                'timeout' => 3
            ]
        ];
        $contexto = stream_context_create($opcoes);
        $url = $this->urlBase . rawurlencode($chave);
        $json = @file_get_contents($url, false, $contexto);

        if ($json === false) {
            return null;
        }

        $dados = json_decode($json, true);
        return is_array($dados) ? $dados : null;
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
            'gif'            => $info['gif'],
            'movimentos'     => $info['movimentos'] ?? []
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['catalogo_pokeapi'][$id] = $pokemon;
        }

        return self::$cache[$id] = $pokemon;
    }

    /**
     * Retorna os movimentos de um Pokémon por ID ou pelo seu tipo elemental
     */
    public static function obterMovimentosPokemon($idOuNome): array {
        $api = new self();
        $poke = $api->buscarPokemon((string)$idOuNome);
        if ($poke && !empty($poke['movimentos'])) {
            return $poke['movimentos'];
        }
        if ($poke && !empty($poke['tipo'])) {
            return self::obterMovimentosPorTipo($poke['tipo']);
        }
        return [];
    }

    public static function obterMovimentosPadrao($idOuNome, string $nome = '', string $tipoPokemon = ''): array {
        return self::obterMovimentosPorTipo($tipoPokemon);
    }

    /**
     * Lista os 15 Pokémons iniciais disponíveis no catálogo do jogo
     */
    public static function listar(): array {
        $lista = [];
        // Pega os 15 primeiros Pokémon da lista para a escolha no Team Builder
        $iniciais = array_slice(self::IDS, 0, 15);
        foreach ($iniciais as $id) {
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
