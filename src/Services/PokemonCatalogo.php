<?php

/**
 * Catálogo dos 20 Pokémons disponíveis para o jogador montar o time.
 * Baseado na estrutura de arrays associativos ensinada nas aulas.
 */
class PokemonCatalogo {

    public static function listar(): array {
        return [
            [
                'id_pokedex' => 1,
                'nome' => 'Bulbasaur',
                'tipo' => 'planta',
                'nivel' => 5,
                'hpMaximo' => 45,
                'hpAtual' => 45,
                'ataque' => 49,
                'defesa' => 49,
                'velocidade' => 45,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/1.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/1.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/1.png',
                'golpes' => [
                    ['nome' => 'Tackle', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Vine Whip', 'tipo' => 'planta', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Poison Powder', 'tipo' => 'planta', 'poder' => 35, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 4,
                'nome' => 'Charmander',
                'tipo' => 'fogo',
                'nivel' => 5,
                'hpMaximo' => 39,
                'hpAtual' => 39,
                'ataque' => 52,
                'defesa' => 43,
                'velocidade' => 65,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/4.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/4.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/4.png',
                'golpes' => [
                    ['nome' => 'Scratch', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Ember', 'tipo' => 'fogo', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Flamethrower', 'tipo' => 'fogo', 'poder' => 70, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 7,
                'nome' => 'Squirtle',
                'tipo' => 'agua',
                'nivel' => 5,
                'hpMaximo' => 44,
                'hpAtual' => 44,
                'ataque' => 48,
                'defesa' => 65,
                'velocidade' => 43,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/7.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/7.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/7.png',
                'golpes' => [
                    ['nome' => 'Tackle', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Water Gun', 'tipo' => 'agua', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Hydro Pump', 'tipo' => 'agua', 'poder' => 75, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 25,
                'nome' => 'Pikachu',
                'tipo' => 'eletrico',
                'nivel' => 5,
                'hpMaximo' => 35,
                'hpAtual' => 35,
                'ataque' => 55,
                'defesa' => 40,
                'velocidade' => 90,
                'prioridade' => 1,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/25.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/25.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/25.png',
                'golpes' => [
                    ['nome' => 'Quick Attack', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 1],
                    ['nome' => 'Thunder Shock', 'tipo' => 'eletrico', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Thunderbolt', 'tipo' => 'eletrico', 'poder' => 75, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 74,
                'nome' => 'Geodude',
                'tipo' => 'terra',
                'nivel' => 5,
                'hpMaximo' => 40,
                'hpAtual' => 40,
                'ataque' => 80,
                'defesa' => 100,
                'velocidade' => 20,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/74.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/74.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/74.png',
                'golpes' => [
                    ['nome' => 'Tackle', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Rock Throw', 'tipo' => 'terra', 'poder' => 50, 'prioridade' => 0],
                    ['nome' => 'Earthquake', 'tipo' => 'terra', 'poder' => 80, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 27,
                'nome' => 'Sandshrew',
                'tipo' => 'terra',
                'nivel' => 5,
                'hpMaximo' => 50,
                'hpAtual' => 50,
                'ataque' => 75,
                'defesa' => 85,
                'velocidade' => 40,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/27.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/27.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/27.png',
                'golpes' => [
                    ['nome' => 'Scratch', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Mud-Slap', 'tipo' => 'terra', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Earthquake', 'tipo' => 'terra', 'poder' => 75, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 58,
                'nome' => 'Growlithe',
                'tipo' => 'fogo',
                'nivel' => 5,
                'hpMaximo' => 55,
                'hpAtual' => 55,
                'ataque' => 70,
                'defesa' => 45,
                'velocidade' => 60,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/58.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/58.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/58.png',
                'golpes' => [
                    ['nome' => 'Bite', 'tipo' => 'normal', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Ember', 'tipo' => 'fogo', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Flamethrower', 'tipo' => 'fogo', 'poder' => 75, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 60,
                'nome' => 'Poliwag',
                'tipo' => 'agua',
                'nivel' => 5,
                'hpMaximo' => 40,
                'hpAtual' => 40,
                'ataque' => 50,
                'defesa' => 40,
                'velocidade' => 90,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/60.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/60.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/60.png',
                'golpes' => [
                    ['nome' => 'Water Gun', 'tipo' => 'agua', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Bubble', 'tipo' => 'agua', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Hydro Pump', 'tipo' => 'agua', 'poder' => 75, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 43,
                'nome' => 'Oddish',
                'tipo' => 'planta',
                'nivel' => 5,
                'hpMaximo' => 45,
                'hpAtual' => 45,
                'ataque' => 50,
                'defesa' => 55,
                'velocidade' => 30,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/43.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/43.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/43.png',
                'golpes' => [
                    ['nome' => 'Tackle', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Absorb', 'tipo' => 'planta', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Mega Drain', 'tipo' => 'planta', 'poder' => 65, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 81,
                'nome' => 'Magnemite',
                'tipo' => 'eletrico',
                'nivel' => 5,
                'hpMaximo' => 30,
                'hpAtual' => 30,
                'ataque' => 45,
                'defesa' => 70,
                'velocidade' => 45,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/81.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/81.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/81.png',
                'golpes' => [
                    ['nome' => 'Tackle', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Thunder Shock', 'tipo' => 'eletrico', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Spark', 'tipo' => 'eletrico', 'poder' => 65, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 50,
                'nome' => 'Diglett',
                'tipo' => 'terra',
                'nivel' => 5,
                'hpMaximo' => 25,
                'hpAtual' => 25,
                'ataque' => 55,
                'defesa' => 30,
                'velocidade' => 95,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/50.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/50.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/50.png',
                'golpes' => [
                    ['nome' => 'Scratch', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Mud-Slap', 'tipo' => 'terra', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Dig', 'tipo' => 'terra', 'poder' => 70, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 54,
                'nome' => 'Psyduck',
                'tipo' => 'agua',
                'nivel' => 5,
                'hpMaximo' => 50,
                'hpAtual' => 50,
                'ataque' => 52,
                'defesa' => 48,
                'velocidade' => 55,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/54.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/54.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/54.png',
                'golpes' => [
                    ['nome' => 'Scratch', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Water Gun', 'tipo' => 'agua', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Water Pulse', 'tipo' => 'agua', 'poder' => 65, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 37,
                'nome' => 'Vulpix',
                'tipo' => 'fogo',
                'nivel' => 5,
                'hpMaximo' => 38,
                'hpAtual' => 38,
                'ataque' => 45,
                'defesa' => 40,
                'velocidade' => 65,
                'prioridade' => 1,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/37.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/37.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/37.png',
                'golpes' => [
                    ['nome' => 'Quick Attack', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 1],
                    ['nome' => 'Ember', 'tipo' => 'fogo', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Fire Spin', 'tipo' => 'fogo', 'poder' => 65, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 69,
                'nome' => 'Bellsprout',
                'tipo' => 'planta',
                'nivel' => 5,
                'hpMaximo' => 50,
                'hpAtual' => 50,
                'ataque' => 75,
                'defesa' => 35,
                'velocidade' => 40,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/69.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/69.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/69.png',
                'golpes' => [
                    ['nome' => 'Vine Whip', 'tipo' => 'planta', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Wrap', 'tipo' => 'normal', 'poder' => 35, 'prioridade' => 0],
                    ['nome' => 'Razor Leaf', 'tipo' => 'planta', 'poder' => 70, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 125,
                'nome' => 'Electabuzz',
                'tipo' => 'eletrico',
                'nivel' => 5,
                'hpMaximo' => 65,
                'hpAtual' => 65,
                'ataque' => 75,
                'defesa' => 55,
                'velocidade' => 85,
                'prioridade' => 1,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/125.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/125.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/125.png',
                'golpes' => [
                    ['nome' => 'Quick Attack', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 1],
                    ['nome' => 'Thunder Punch', 'tipo' => 'eletrico', 'poder' => 60, 'prioridade' => 0],
                    ['nome' => 'Thunderbolt', 'tipo' => 'eletrico', 'poder' => 75, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 95,
                'nome' => 'Onix',
                'tipo' => 'terra',
                'nivel' => 5,
                'hpMaximo' => 45,
                'hpAtual' => 45,
                'ataque' => 50,
                'defesa' => 120,
                'velocidade' => 60,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/95.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/95.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/95.png',
                'golpes' => [
                    ['nome' => 'Tackle', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Rock Throw', 'tipo' => 'terra', 'poder' => 50, 'prioridade' => 0],
                    ['nome' => 'Slam', 'tipo' => 'normal', 'poder' => 65, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 129,
                'nome' => 'Magikarp',
                'tipo' => 'agua',
                'nivel' => 5,
                'hpMaximo' => 30,
                'hpAtual' => 30,
                'ataque' => 35,
                'defesa' => 55,
                'velocidade' => 80,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/129.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/129.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/129.png',
                'golpes' => [
                    ['nome' => 'Splash', 'tipo' => 'normal', 'poder' => 20, 'prioridade' => 0],
                    ['nome' => 'Tackle', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Flail', 'tipo' => 'agua', 'poder' => 55, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 77,
                'nome' => 'Ponyta',
                'tipo' => 'fogo',
                'nivel' => 5,
                'hpMaximo' => 50,
                'hpAtual' => 50,
                'ataque' => 70,
                'defesa' => 50,
                'velocidade' => 90,
                'prioridade' => 1,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/77.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/77.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/77.png',
                'golpes' => [
                    ['nome' => 'Quick Attack', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 1],
                    ['nome' => 'Ember', 'tipo' => 'fogo', 'poder' => 45, 'prioridade' => 0],
                    ['nome' => 'Flame Wheel', 'tipo' => 'fogo', 'poder' => 65, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 102,
                'nome' => 'Exeggcute',
                'tipo' => 'planta',
                'nivel' => 5,
                'hpMaximo' => 60,
                'hpAtual' => 60,
                'ataque' => 45,
                'defesa' => 80,
                'velocidade' => 40,
                'prioridade' => 0,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/102.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/102.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/102.png',
                'golpes' => [
                    ['nome' => 'Barrage', 'tipo' => 'normal', 'poder' => 35, 'prioridade' => 0],
                    ['nome' => 'Hypnosis', 'tipo' => 'normal', 'poder' => 20, 'prioridade' => 0],
                    ['nome' => 'Solar Beam', 'tipo' => 'planta', 'poder' => 75, 'prioridade' => 0]
                ]
            ],
            [
                'id_pokedex' => 133,
                'nome' => 'Eevee',
                'tipo' => 'normal',
                'nivel' => 5,
                'hpMaximo' => 55,
                'hpAtual' => 55,
                'ataque' => 55,
                'defesa' => 50,
                'velocidade' => 55,
                'prioridade' => 1,
                'statusCondicao' => 'Normal',
                'imagemArtwork' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/133.png',
                'imagemFrente' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/133.png',
                'imagemCostas' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/133.png',
                'golpes' => [
                    ['nome' => 'Tackle', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 0],
                    ['nome' => 'Quick Attack', 'tipo' => 'normal', 'poder' => 40, 'prioridade' => 1],
                    ['nome' => 'Bite', 'tipo' => 'normal', 'poder' => 60, 'prioridade' => 0]
                ]
            ]
        ];
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
