<?php

require_once __DIR__ . '/PokeApiService.php';

/**
 * Catálogo dos 12 Pokémons disponíveis para o jogador montar o time.
 * Aqui ficam apenas os IDs escolhidos: todos os dados (nome, tipo, stats e
 * imagens) são buscados na PokéAPI através do PokeApiService.
 */
class PokemonCatalogo {

    // IDs da Pokédex dos Pokémons do jogo
    const IDS = [1, 252, 4, 255, 7, 258, 25, 179, 133, 143, 63, 92];

    // Cache em memória (durante a requisição atual)
    private static $cache = [];

    // Busca um Pokémon do catálogo pelo ID (usa cache da sessão para não repetir requisições)
    public static function buscarPorId(int $id): ?array {
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }
        if (isset($_SESSION['catalogo_pokeapi'][$id])) {
            return self::$cache[$id] = $_SESSION['catalogo_pokeapi'][$id];
        }

        $api  = new PokeApiService();
        $info = $api->buscarPokemon((string)$id);

        if ($info === false) {
            return null; // API indisponível ou Pokémon não encontrado
        }

        $pokemon = [
            'id_pokedex'     => $info['id'],
            'nome'           => ucfirst($info['nome']),
            'tipo'           => $info['tipo'],
            'nivel'          => $info['nivel'],
            'hpMaximo'       => $info['hp'],
            'hpAtual'        => $info['hp'],
            'ataque'         => $info['ataque'],
            'defesa'         => $info['defesa'],
            'velocidade'     => $info['velocidade'],
            'prioridade'     => 0,
            'statusCondicao' => 'Normal',
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

    // Lista todos os Pokémons do catálogo (ignora os que falharem na API)
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

    public static function buscarPorNome(string $nome): ?array {
        $todos = self::listar();
        foreach ($todos as $p) {
            if (strcasecmp($p['nome'], $nome) === 0) {
                return $p;
            }
        }
        return null;
    }

    public static function sortearOponentes(array $nomesIgnorados, int $quantidade = 3): array {
        $todos = self::listar();
        $disponiveis = [];

        // Filtra os que o jogador não escolheu
        foreach ($todos as $p) {
            $escolhido = false;
            foreach ($nomesIgnorados as $nome) {
                if (strcasecmp($p['nome'], $nome) === 0) {
                    $escolhido = true;
                    break;
                }
            }
            if (!$escolhido) {
                $disponiveis[] = $p;
            }
        }

        // Embaralha usando a função nativa do PHP conforme slides
        shuffle($disponiveis);
        return array_slice($disponiveis, 0, $quantidade);
    }
}
