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
    private static $cacheMoves = [];

    /**
     * Busca os detalhes de um movimento específico diretamente na PokéAPI (/api/v2/move/{nome})
     * com cache em memória e sessão para alto desempenho.
     */
    public function buscarMovimentoNaApi(string $nomeMove): ?array {
        $slug = strtolower(trim(str_replace(' ', '-', $nomeMove)));
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

        $moveFormatado = [
            'nome'         => ucfirst(str_replace('-', ' ', $dados['name'])),
            'categoria'    => $dados['damage_class']['name'] ?? 'physical',
            'tipo'         => $dados['type']['name'] ?? 'normal',
            'poder'        => (int)($dados['power'] ?? 40),
            'precisao'     => (int)($dados['accuracy'] ?? 100),
            'efeito'       => $dados['stat_changes'][0] ?? null
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['cache_moves'][$slug] = $moveFormatado;
        }

        return self::$cacheMoves[$slug] = $moveFormatado;
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
            'imagemArtwork' => $dados['sprites']['other']['official-artwork']['front_default'] ?? ($dados['sprites']['front_default'] ?? ''),
            'movimentos'    => self::obterMovimentosPadrao((int)$dados['id'], $dados['name'], $dados['types'][0]['type']['name'] ?? 'normal')
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
            'movimentos'     => self::obterMovimentosPadrao((int)$info['id'], $info['nome'], $info['tipo'])
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['catalogo_pokeapi'][$id] = $pokemon;
        }

        return self::$cache[$id] = $pokemon;
    }

    /**
     * Mapeia os 3 tipos canônicos de ataque da PokéAPI para cada Pokémon:
     * - Físico (Physical): dano direto baseado em Atk vs Def
     * - Especial (Special): dano de energia/feixes baseado em poder elemental
     * - Status (Efeito): altera atributos (Atk/Def), causa condições ou cura vida
     */
    public static function obterMovimentosPadrao(int $idPokedex, string $nome = '', string $tipo = 'normal'): array {
        $nomeLimpo = strtolower(trim($nome));

        $catalogoPorId = [
            1 => [ // Bulbasaur
                'fisico'   => ['nome' => 'Tackle', 'nomePt' => 'Investida', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Dano físico direto corporal.'],
                'especial' => ['nome' => 'Vine Whip', 'nomePt' => 'Chicote de Vinha', 'damage_class' => 'special', 'tipo' => 'grass', 'poder' => 45, 'desc' => 'Golpe com vinhas carregadas de energia vegetal à distância.'],
                'status'   => ['nome' => 'Growl', 'nomePt' => 'Rosnar', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'reduzir_atk', 'desc' => 'Diminui o Ataque do oponente em 20%.']
            ],
            252 => [ // Treecko
                'fisico'   => ['nome' => 'Pound', 'nomePt' => 'Pancada', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Golpe físico com a cauda.'],
                'especial' => ['nome' => 'Mega Drain', 'nomePt' => 'Mega Dreno', 'damage_class' => 'special', 'tipo' => 'grass', 'poder' => 40, 'desc' => 'Drena energia vegetal do oponente à distância.'],
                'status'   => ['nome' => 'Leer', 'nomePt' => 'Olhar Encarador', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'reduzir_def', 'desc' => 'Encarada intimidadora que diminui a Defesa do oponente em 20%.']
            ],
            4 => [ // Charmander
                'fisico'   => ['nome' => 'Scratch', 'nomePt' => 'Arranhão', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Dano físico direto usando garras afiadas.'],
                'especial' => ['nome' => 'Ember', 'nomePt' => 'Brasas', 'damage_class' => 'special', 'tipo' => 'fire', 'poder' => 40, 'desc' => 'Projéteis de fogo ardente disparados à distância.'],
                'status'   => ['nome' => 'Growl', 'nomePt' => 'Rosnar', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'reduzir_atk', 'desc' => 'Diminui o Ataque do oponente em 20%.']
            ],
            255 => [ // Torchic
                'fisico'   => ['nome' => 'Scratch', 'nomePt' => 'Arranhão', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Ataque físico direto usando garras.'],
                'especial' => ['nome' => 'Ember', 'nomePt' => 'Brasas', 'damage_class' => 'special', 'tipo' => 'fire', 'poder' => 40, 'desc' => 'Lança brasas de fogo concentradas à distância.'],
                'status'   => ['nome' => 'Growl', 'nomePt' => 'Rosnar', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'reduzir_atk', 'desc' => 'Diminui o Ataque do oponente em 20%.']
            ],
            7 => [ // Squirtle
                'fisico'   => ['nome' => 'Tackle', 'nomePt' => 'Investida', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Impacto físico corporal direto.'],
                'especial' => ['nome' => 'Water Gun', 'nomePt' => 'Jato d\'Água', 'damage_class' => 'special', 'tipo' => 'water', 'poder' => 40, 'desc' => 'Jato de água pressurizada lançado à distância.'],
                'status'   => ['nome' => 'Tail Whip', 'nomePt' => 'Chicote de Cauda', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'reduzir_def', 'desc' => 'Diminui a Defesa do oponente em 20%.']
            ],
            258 => [ // Mudkip
                'fisico'   => ['nome' => 'Tackle', 'nomePt' => 'Investida', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Investida corporal direta.'],
                'especial' => ['nome' => 'Water Gun', 'nomePt' => 'Jato d\'Água', 'damage_class' => 'special', 'tipo' => 'water', 'poder' => 40, 'desc' => 'Disparo de energia aquática à distância.'],
                'status'   => ['nome' => 'Growl', 'nomePt' => 'Rosnar', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'reduzir_atk', 'desc' => 'Diminui o Ataque do oponente em 20%.']
            ],
            25 => [ // Pikachu
                'fisico'   => ['nome' => 'Quick Attack', 'nomePt' => 'Ataque Rápido', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Avanço físico em velocidade extrema.'],
                'especial' => ['nome' => 'Thundershock', 'nomePt' => 'Choque do Trovão', 'damage_class' => 'special', 'tipo' => 'electric', 'poder' => 40, 'desc' => 'Descarga de raios elétricos arremessada à distância.'],
                'status'   => ['nome' => 'Thunder Wave', 'nomePt' => 'Onda de Trovão', 'damage_class' => 'status', 'tipo' => 'electric', 'efeito' => 'paralisar', 'desc' => 'Paralisa o adversário, diminuindo sua velocidade e chance de ação.']
            ],
            179 => [ // Mareep
                'fisico'   => ['nome' => 'Tackle', 'nomePt' => 'Investida', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Investida física corporal.'],
                'especial' => ['nome' => 'Thundershock', 'nomePt' => 'Choque do Trovão', 'damage_class' => 'special', 'tipo' => 'electric', 'poder' => 40, 'desc' => 'Disparo de energia estática à distância.'],
                'status'   => ['nome' => 'Thunder Wave', 'nomePt' => 'Onda de Trovão', 'damage_class' => 'status', 'tipo' => 'electric', 'efeito' => 'paralisar', 'desc' => 'Paralisa o adversário, diminuindo sua velocidade e chance de ação.']
            ],
            63 => [ // Abra
                'fisico'   => ['nome' => 'Headbutt', 'nomePt' => 'Cabeçada', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 35, 'desc' => 'Golpe físico direto.'],
                'especial' => ['nome' => 'Confusion', 'nomePt' => 'Confusão', 'damage_class' => 'special', 'tipo' => 'psychic', 'poder' => 50, 'desc' => 'Feixe de energia mental psíquica pura à distância.'],
                'status'   => ['nome' => 'Recover', 'nomePt' => 'Recuperar', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'curar_proprio', 'desc' => 'Recupera até 30% do próprio HP!']
            ],
            92 => [ // Gastly
                'fisico'   => ['nome' => 'Lick', 'nomePt' => 'Lambida', 'damage_class' => 'physical', 'tipo' => 'ghost', 'poder' => 30, 'desc' => 'Contato físico espectral frio.'],
                'especial' => ['nome' => 'Shadow Ball', 'nomePt' => 'Bola Sombria', 'damage_class' => 'special', 'tipo' => 'ghost', 'poder' => 50, 'desc' => 'Esfera de energia sombria disparada à distância.'],
                'status'   => ['nome' => 'Spite', 'nomePt' => 'Rancor', 'damage_class' => 'status', 'tipo' => 'ghost', 'efeito' => 'reduzir_atk', 'desc' => 'Energia negativa que reduz o Ataque do oponente em 20%.']
            ],
            133 => [ // Eevee
                'fisico'   => ['nome' => 'Bite', 'nomePt' => 'Mordida', 'damage_class' => 'physical', 'tipo' => 'dark', 'poder' => 40, 'desc' => 'Mordida física direta com mandíbulas afiadas.'],
                'especial' => ['nome' => 'Swift', 'nomePt' => 'Ataque Veloz', 'damage_class' => 'special', 'tipo' => 'normal', 'poder' => 45, 'desc' => 'Dispara estrelas de energia luminosa à distância.'],
                'status'   => ['nome' => 'Tail Whip', 'nomePt' => 'Chicote de Cauda', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'reduzir_def', 'desc' => 'Diminui a Defesa do oponente em 20%.']
            ],
            143 => [ // Snorlax
                'fisico'   => ['nome' => 'Body Slam', 'nomePt' => 'Pancada Corporal', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 50, 'desc' => 'Impacto físico maciço com todo o peso do corpo.'],
                'especial' => ['nome' => 'Snore', 'nomePt' => 'Ronco Sônico', 'damage_class' => 'special', 'tipo' => 'normal', 'poder' => 45, 'desc' => 'Onda acústica de energia disparada à distância.'],
                'status'   => ['nome' => 'Rest', 'nomePt' => 'Descansar', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'curar_proprio', 'desc' => 'Cochila e recupera até 35% do próprio HP!']
            ]
        ];

        if (isset($catalogoPorId[$idPokedex])) {
            return $catalogoPorId[$idPokedex];
        }

        // Busca por nome se ID não for o principal
        foreach ($catalogoPorId as $id => $movs) {
            $pkNome = strtolower(self::listarNomesPadrao()[$id] ?? '');
            if ($pkNome !== '' && (strpos($nomeLimpo, $pkNome) !== false || strpos($pkNome, $nomeLimpo) !== false)) {
                return $movs;
            }
        }

        // Fallback genérico por tipo
        $tipoNorm = strtolower(trim($tipo));
        $golpeEspecial = 'Swift';
        $tipoEspecial = 'normal';
        if ($tipoNorm === 'fogo' || $tipoNorm === 'fire') { $golpeEspecial = 'Ember'; $tipoEspecial = 'fire'; }
        elseif ($tipoNorm === 'agua' || $tipoNorm === 'water') { $golpeEspecial = 'Water Gun'; $tipoEspecial = 'water'; }
        elseif ($tipoNorm === 'planta' || $tipoNorm === 'grass') { $golpeEspecial = 'Vine Whip'; $tipoEspecial = 'grass'; }
        elseif ($tipoNorm === 'eletrico' || $tipoNorm === 'electric') { $golpeEspecial = 'Thundershock'; $tipoEspecial = 'electric'; }
        elseif ($tipoNorm === 'psiquico' || $tipoNorm === 'psychic') { $golpeEspecial = 'Confusion'; $tipoEspecial = 'psychic'; }
        elseif ($tipoNorm === 'fantasma' || $tipoNorm === 'ghost') { $golpeEspecial = 'Shadow Ball'; $tipoEspecial = 'ghost'; }

        return [
            'fisico'   => ['nome' => 'Tackle', 'nomePt' => 'Investida', 'damage_class' => 'physical', 'tipo' => 'normal', 'poder' => 40, 'desc' => 'Dano físico corporal direto.'],
            'especial' => ['nome' => $golpeEspecial, 'nomePt' => $golpeEspecial, 'damage_class' => 'special', 'tipo' => $tipoEspecial, 'poder' => 40, 'desc' => 'Ataque de energia elemental à distância.'],
            'status'   => ['nome' => 'Growl', 'nomePt' => 'Rosnar', 'damage_class' => 'status', 'tipo' => 'normal', 'efeito' => 'reduzir_atk', 'desc' => 'Diminui o Ataque do oponente em 20%.']
        ];
    }

    private static function listarNomesPadrao(): array {
        return [
            1 => 'bulbasaur', 252 => 'treecko', 4 => 'charmander', 255 => 'torchic',
            7 => 'squirtle', 258 => 'mudkip', 25 => 'pikachu', 179 => 'mareep',
            133 => 'eevee', 143 => 'snorlax', 63 => 'abra', 92 => 'gastly'
        ];
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
