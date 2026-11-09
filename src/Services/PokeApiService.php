<?php

/**
 * PokeApiService
 * Centraliza a comunicação com a PokéAPI (lado do servidor)
 * e o catálogo dos Pokémons disponíveis para o jogo, incluindo cache em sessão e memória.
 */
class PokeApiService {

    // IDs da Pokédex dos 15 Pokémons disponíveis no catálogo do jogo
    const IDS = [1, 252, 4, 255, 7, 258, 25, 179, 133, 143, 63, 92, 152, 155, 158];

    private $urlBase = "https://pokeapi.co/api/v2/pokemon/";

    // Cache em memória (durante a execução da requisição atual)
    private static $cache = [];
    private static $cacheMoves = [];

    /**
     * Busca os detalhes de um movimento específico diretamente na PokéAPI (/api/v2/move/{nome})
     * com cache em memória e sessão para alto desempenho.
     */
    public function buscarMovimentoNaApi(string $nomeMove): ?array {
        $slug = strtolower(trim(str_replace([' ', '_'], '-', $nomeMove)));
        if ($slug === '') return null;

        if (isset(self::$cacheMoves[$slug])) {
            return self::$cacheMoves[$slug];
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['cache_moves'][$slug])) {
            return self::$cacheMoves[$slug] = $_SESSION['cache_moves'][$slug];
        }

        $url = "https://pokeapi.co/api/v2/move/" . $slug;
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
        $json = @file_get_contents($url, false, $contexto);

        if ($json === false) {
            return null;
        }

        $dados = json_decode($json, true);
        if (!is_array($dados)) return null;

        $classeDano = $dados['damage_class']['name'] ?? 'physical';
        $moveFormatado = [
            'nome'         => ucwords(str_replace('-', ' ', $dados['name'] ?? $slug)),
            'damage_class' => $classeDano,
            'categoria'    => $classeDano,
            'tipo'         => $dados['type']['name'] ?? 'normal',
            'poder'        => (int)($dados['power'] ?? ($classeDano === 'status' ? 0 : 40)),
            'precisao'     => (int)($dados['accuracy'] ?? 100),
            'efeito'       => self::extrairEfeitoMove($dados),
            'desc'         => self::extrairDescricaoMove($dados)
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['cache_moves'][$slug] = $moveFormatado;
        }

        return self::$cacheMoves[$slug] = $moveFormatado;
    }

    /**
     * Determina o efeito de batalha para golpes de status a partir dos dados da PokéAPI
     */
    public static function extrairEfeitoMove(array $dadosMove): string {
        $nome = strtolower($dadosMove['name'] ?? '');

        $movesCura = ['recover', 'rest', 'soft-boiled', 'roost', 'synthesis', 'morning-sun', 'moonlight', 'slack-off', 'milk-drink', 'heal-order', 'swallow', 'wish'];
        if (in_array($nome, $movesCura) || (!empty($dadosMove['meta']['healing']) && $dadosMove['meta']['healing'] > 0)) {
            return 'curar_proprio';
        }

        foreach (($dadosMove['stat_changes'] ?? []) as $sc) {
            $statName = $sc['stat']['name'] ?? '';
            $change = (int)($sc['change'] ?? 0);
            if ($statName === 'attack' && $change < 0) return 'reduzir_atk';
            if ($statName === 'defense' && $change < 0) return 'reduzir_def';
            if ($statName === 'accuracy' && $change < 0) return 'reduzir_precisao';
        }

        $ailment = strtolower($dadosMove['meta']['ailment']['name'] ?? '');
        if ($ailment === 'paralysis') return 'paralisar';
        if ($ailment === 'sleep') return 'dormir';
        if ($ailment === 'poison') return 'envenenar';

        if (in_array($nome, ['thunder-wave', 'glare', 'stun-spore', 'nuzzle', 'zap-cannon'])) {
            return 'paralisar';
        }
        if (in_array($nome, ['leer', 'tail-whip', 'screech', 'tickle', 'acid-spray'])) {
            return 'reduzir_def';
        }
        if (in_array($nome, ['sand-attack', 'smokescreen', 'flash', 'kinesis', 'mud-slap'])) {
            return 'reduzir_precisao';
        }

        return 'reduzir_atk';
    }

    /**
     * Extrai descrição curta do movimento a partir das entradas de texto
     */
    public static function extrairDescricaoMove(array $dadosMove): string {
        foreach (($dadosMove['flavor_text_entries'] ?? []) as $entry) {
            if (($entry['language']['name'] ?? '') === 'en') {
                return preg_replace('/\s+/', ' ', trim($entry['flavor_text']));
            }
        }
        return 'Movimento carregado da PokéAPI.';
    }

    /**
     * Carrega dinamicamente 3 categorias de movimentos para o Pokémon (1 Físico, 1 Especial, 1 Status)
     */
    public function carregarMovimentosDaApi(array $movesDoPokemon, string $tipoPokemon = 'normal'): array {
        $movimentos = [];

        foreach ($movesDoPokemon as $m) {
            if (isset($movimentos['fisico'], $movimentos['especial'], $movimentos['status'])) {
                break;
            }

            $slug = $m['move']['name'] ?? null;
            if (!$slug) continue;

            $detalhes = $this->buscarMovimentoNaApi($slug);
            if (!$detalhes) continue;

            $cat = strtolower($detalhes['damage_class'] ?? $detalhes['categoria'] ?? 'physical');
            if ($cat === 'physical' && !isset($movimentos['fisico'])) {
                $movimentos['fisico'] = $detalhes;
            } elseif ($cat === 'special' && !isset($movimentos['especial'])) {
                $movimentos['especial'] = $detalhes;
            } elseif ($cat === 'status' && !isset($movimentos['status'])) {
                $movimentos['status'] = $detalhes;
            }
        }

        // Fallbacks caso a requisição falhe ou o Pokémon não possua a categoria em níveis baixos
        if (!isset($movimentos['fisico'])) {
            $movimentos['fisico'] = [
                'nome'         => 'Tackle',
                'damage_class' => 'physical',
                'categoria'    => 'physical',
                'tipo'         => 'normal',
                'poder'        => 40,
                'precisao'     => 100,
                'efeito'       => null,
                'desc'         => 'Investida física corporal direta.'
            ];
        }

        if (!isset($movimentos['especial'])) {
            $tipoNorm = strtolower(trim($tipoPokemon));
            $nomeEspec = 'Swift';
            $tipoEspec = 'normal';
            if (in_array($tipoNorm, ['fogo', 'fire'])) { $nomeEspec = 'Ember'; $tipoEspec = 'fire'; }
            elseif (in_array($tipoNorm, ['agua', 'water'])) { $nomeEspec = 'Water Gun'; $tipoEspec = 'water'; }
            elseif (in_array($tipoNorm, ['planta', 'grass'])) { $nomeEspec = 'Vine Whip'; $tipoEspec = 'grass'; }
            elseif (in_array($tipoNorm, ['eletrico', 'electric'])) { $nomeEspec = 'Thundershock'; $tipoEspec = 'electric'; }
            elseif (in_array($tipoNorm, ['psiquico', 'psychic'])) { $nomeEspec = 'Confusion'; $tipoEspec = 'psychic'; }
            elseif (in_array($tipoNorm, ['fantasma', 'ghost'])) { $nomeEspec = 'Shadow Ball'; $tipoEspec = 'ghost'; }

            $movimentos['especial'] = [
                'nome'         => $nomeEspec,
                'damage_class' => 'special',
                'categoria'    => 'special',
                'tipo'         => $tipoEspec,
                'poder'        => 40,
                'precisao'     => 100,
                'efeito'       => null,
                'desc'         => 'Ataque de energia elemental à distância.'
            ];
        }

        if (!isset($movimentos['status'])) {
            $movimentos['status'] = [
                'nome'         => 'Growl',
                'damage_class' => 'status',
                'categoria'    => 'status',
                'tipo'         => 'normal',
                'poder'        => 0,
                'precisao'     => 100,
                'efeito'       => 'reduzir_atk',
                'desc'         => 'Rosnado intimidador que diminui o Ataque do oponente.'
            ];
        }

        return $movimentos;
    }

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
            ],
            'http' => [
                'timeout' => 3
            ]
        ];
        $contexto = stream_context_create($opcoes);

        $json = @file_get_contents($url, false, $contexto);

        if ($json === false) {
            return false;
        }

        $dados = json_decode($json, true);
        if (!is_array($dados)) {
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

        $tipoPrincipal = $dados['types'][0]['type']['name'] ?? 'normal';

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
            'imagem'        => $dados['sprites']['front_default'] ?? '',
            'imagemCostas'  => $dados['sprites']['back_default'] ?? '',
            'imagemArtwork' => $dados['sprites']['other']['official-artwork']['front_default'] ?? ($dados['sprites']['front_default'] ?? ''),
            'movimentos'    => $this->carregarMovimentosDaApi($dados['moves'] ?? [], $tipoPrincipal)
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
            'gif'            => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/showdown/' . $info['id'] . '.gif',
            'movimentos'     => $info['movimentos'] ?? []
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['catalogo_pokeapi'][$id] = $pokemon;
        }

        return self::$cache[$id] = $pokemon;
    }

    /**
     * Carrega os movimentos de um Pokémon buscando diretamente na PokéAPI por ID ou nome
     */
    public static function obterMovimentosPokemon($idOuNome): array {
        $api = new self();
        $poke = $api->buscarPokemon((string)$idOuNome);
        return $poke['movimentos'] ?? [];
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
