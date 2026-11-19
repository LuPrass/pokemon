<?php

// Regras da batalha entre dois pokémons (não mexe no banco de dados)
class Batalha {

    // Frase mostrada quando o pokémon ganha um status
    const FRASES_STATUS = [
        'Queimado'   => 'foi queimado!',
        'Congelado'  => 'foi congelado!',
        'Envenenado' => 'foi envenenado!',
        'Paralisado' => 'foi paralisado!',
        'Dormindo'   => 'adormeceu!'
    ];

    // Golpes de status (da PokéAPI) e o status que cada um causa
    const EFEITOS_STATUS = [
        'queimar'   => 'Queimado',
        'congelar'  => 'Congelado',
        'envenenar' => 'Envenenado',
        'paralisar' => 'Paralisado',
        'dormir'    => 'Dormindo'
    ];

    private $jogador;
    private $inimigo;
    private $turnos = []; // tudo o que aconteceu, em texto
    private $resultado = '';

    public function __construct(Pokemon $jogador, Pokemon $inimigo) {
        $this->jogador = $jogador;
        $this->inimigo = $inimigo;
    }

    public function getTurnos() {
        return $this->turnos;
    }

    public function getResultado() {
        return $this->resultado;
    }

    private function mensagem($texto) {
        $this->turnos[] = $texto;
    }

    // Golpe básico padrão caso lute de forma automática
    private function golpePadrao(Pokemon $pokemon) {
        return [
            'nome'         => 'Investida',
            'tipo'         => strtolower($pokemon->getTipo()),
            'poder'        => 40,
            'categoria'    => 'physical',
            'damage_class' => 'physical',
            'prioridade'   => 0
        ];
    }

    // Faz a luta inteira de uma vez, usando um golpe simples para cada lado
    public function lutar() {
        if ($this->jogador->estaDesmaiado()) {
            throw new PokemonDesmaiadoException();
        }

        $limite = 100; // segurança para nunca ficar em loop infinito
        while (!$this->jogador->estaDesmaiado() && !$this->inimigo->estaDesmaiado() && $limite > 0) {
            $this->turno($this->golpePadrao($this->jogador), $this->golpePadrao($this->inimigo));
            $limite--;
        }

        if ($this->inimigo->estaDesmaiado()) {
            $this->resultado = 'vitoria';
        } else {
            $this->resultado = 'derrota';
        }
        return $this->resultado;
    }

    // Um turno: os dois atacam (quem tem maior prioridade e velocidade ataca primeiro)
    public function turno(array $golpeJogador, array $golpeInimigo) {
        if ($this->jogadorAtacaPrimeiro($golpeJogador, $golpeInimigo)) {
            $this->agir($this->jogador, $this->inimigo, $golpeJogador);
            if (!$this->inimigo->estaDesmaiado()) {
                $this->agir($this->inimigo, $this->jogador, $golpeInimigo);
            }
        } else {
            $this->agir($this->inimigo, $this->jogador, $golpeInimigo);
            if (!$this->jogador->estaDesmaiado()) {
                $this->agir($this->jogador, $this->inimigo, $golpeJogador);
            }
        }

        // Queimadura e veneno tiram 10% de HP no fim do turno
        $this->danoDoStatus($this->jogador);
        if (!$this->jogador->estaDesmaiado()) {
            $this->danoDoStatus($this->inimigo);
        }
    }

    // Regra oficial do trabalho:
    // 1º Quem tiver maior prioridade (do Pokémon ou do Golpe) ataca primeiro.
    // 2º Se a prioridade for igual, quem tiver maior velocidade ataca primeiro.
    private function jogadorAtacaPrimeiro(array $golpeJogador = [], array $golpeInimigo = []) {
        $prioGolpeJog = 0;
        if (isset($golpeJogador['prioridade'])) {
            $prioGolpeJog = (int)$golpeJogador['prioridade'];
        }

        $prioGolpeIni = 0;
        if (isset($golpeInimigo['prioridade'])) {
            $prioGolpeIni = (int)$golpeInimigo['prioridade'];
        }

        $prioJogador = $this->jogador->getPrioridade() + $prioGolpeJog;
        $prioInimigo = $this->inimigo->getPrioridade() + $prioGolpeIni;

        // 1. Desempate por Prioridade
        if ($prioJogador != $prioInimigo) {
            return $prioJogador > $prioInimigo;
        }

        // 2. Desempate por Velocidade
        return $this->velocidade($this->jogador) >= $this->velocidade($this->inimigo);
    }

    // Paralisia reduz a velocidade pela metade
    private function velocidade(Pokemon $pokemon) {
        if ($pokemon->getStatusCondicao() == 'Paralisado') {
            return $pokemon->getVelocidade() / 2;
        }
        return $pokemon->getVelocidade();
    }

    // O pokémon tenta usar o golpe (o status pode impedir)
    public function agir(Pokemon $atacante, Pokemon $defensor, array $golpe) {
        if ($this->podeAgir($atacante)) {
            $this->atacar($atacante, $defensor, $golpe);
        }
    }

    // Dormindo, Congelado e Paralisado bloqueiam o ataque
    private function podeAgir(Pokemon $pokemon) {
        $nome = $pokemon->getNome();
        $status = $pokemon->getStatusCondicao();
        $ehJogador = ($pokemon === $this->jogador);

        if ($status == 'Dormindo') {
            if ($ehJogador) {
                $this->mensagem("💤 " . $nome . " está dormindo e não conseguiu atacar! Use Awakening ou Full Heal.");
            } else {
                $this->mensagem("💤 " . $nome . " adversário está dormindo e não pôde atacar!");
            }
            return false;
        }

        if ($status == 'Congelado') {
            if ($ehJogador) {
                $this->mensagem("❄️ " . $nome . " está congelado e não pode se mover! Use Ice Heal ou Full Heal.");
            } else {
                $this->mensagem("❄️ " . $nome . " adversário está congelado e não pôde atacar!");
            }
            return false;
        }

        if ($status == 'Paralisado') {
            if ($ehJogador) {
                $this->mensagem("⚡ " . $nome . " está paralisado e travou! Use Paralyze Heal ou Full Heal.");
            } else {
                $this->mensagem("⚡ " . $nome . " adversário está paralisado e travou!");
            }
            return false;
        }

        return true;
    }

    private function atacar(Pokemon $atacante, Pokemon $defensor, array $golpe) {
        $nomeGolpe = 'Ataque';
        if (!empty($golpe['nome'])) {
            $nomeGolpe = $golpe['nome'];
        }
        $this->mensagem($atacante->getNome() . " usou " . $nomeGolpe . "!");

        // Golpe de status não causa dano
        $categoria = 'physical';
        if (!empty($golpe['damage_class'])) {
            $categoria = $golpe['damage_class'];
        } elseif (!empty($golpe['categoria'])) {
            $categoria = $golpe['categoria'];
        }

        if ($categoria == 'status') {
            $this->usarGolpeDeStatus($atacante, $defensor, $golpe);
            return;
        }

        // Vantagem de tipo vem dos traits
        $tipoGolpe = $atacante->getTipo();
        if (!empty($golpe['tipo'])) {
            $tipoGolpe = $golpe['tipo'];
        }

        $multiplicador = self::multiplicador($tipoGolpe, $defensor->getTipo());
        if ($multiplicador == 0) {
            $this->mensagem("Não afetou " . $defensor->getNome() . "...");
            return;
        }

        $dano = $this->calcularDano($atacante, $defensor, $golpe, $multiplicador);
        $defensor->receberDano($dano);
        $this->mensagem("Causou " . $dano . " de dano em " . $defensor->getNome() .
            " (HP: " . $defensor->getHpAtual() . "/" . $defensor->getHpMaximo() . ")");

        if ($multiplicador > 1) {
            $this->mensagem("É super eficaz!");
        } elseif ($multiplicador < 1) {
            $this->mensagem("Não é muito eficaz.");
        }

        if ($defensor->estaDesmaiado()) {
            $this->mensagem($defensor->getNome() . " desmaiou!");
            return;
        }

        // Se o golpe for elemental, ele sempre aplica o status correspondente do seu tipo
        $status = self::statusDoTipo($tipoGolpe);
        if ($status != 'Normal') {
            $this->aplicarStatus($defensor, $status);
        }
    }

    // Dano = (poder x ataque) / (defesa x 5), vezes a vantagem de tipo
    private function calcularDano(Pokemon $atacante, Pokemon $defensor, array $golpe, $multiplicador) {
        $poder = 40;
        if (isset($golpe['poder']) && (int)$golpe['poder'] > 0) {
            $poder = (int)$golpe['poder'];
        }

        // O ataque e a defesa já vêm com os efeitos dos status calculados
        $ataque = $atacante->getAtaqueAtual();
        $defesa = max(1, $defensor->getDefesaAtual());

        $dano = round(($poder * $ataque) / ($defesa * 5) * $multiplicador);
        return max(1, (int)$dano);
    }

    private function usarGolpeDeStatus(Pokemon $atacante, Pokemon $defensor, array $golpe) {
        $efeito = 'reduzir_atk';
        if (!empty($golpe['efeito'])) {
            $efeito = $golpe['efeito'];
        }
        $nomeDefensor = $defensor->getNome();

        if ($efeito == 'reduzir_atk') {
            $defensor->reduzirAtaque();
            $this->mensagem("O Ataque de " . $nomeDefensor . " caiu!");
        } elseif ($efeito == 'reduzir_def') {
            $defensor->reduzirDefesa();
            $this->mensagem("A Defesa de " . $nomeDefensor . " caiu!");
        } elseif ($efeito == 'curar_proprio') {
            $hpAntes = $atacante->getHpAtual();
            $atacante->curar(max(10, (int)round($atacante->getHpMaximo() * 0.35)));
            $this->mensagem($atacante->getNome() . " recuperou " . ($atacante->getHpAtual() - $hpAntes) . " HP.");
        } elseif (isset(self::EFEITOS_STATUS[$efeito])) {
            if (!$this->aplicarStatus($defensor, self::EFEITOS_STATUS[$efeito])) {
                $this->mensagem("Mas não teve efeito.");
            }
        } else {
            $this->mensagem("Mas não teve efeito.");
        }
    }

    // Só dá o status se o pokémon estiver normal (um status por vez)
    private function aplicarStatus(Pokemon $pokemon, $status) {
        if ($pokemon->getStatusCondicao() != 'Normal') {
            return false;
        }
        $pokemon->setStatusCondicao($status);
        $this->mensagem($pokemon->getNome() . " " . self::FRASES_STATUS[$status]);
        return true;
    }

    // Dano de status: Queimado e Envenenado perdem sempre 10% do HP máximo por turno
    private function danoDoStatus(Pokemon $pokemon) {
        if ($pokemon->estaDesmaiado()) {
            return;
        }

        $status = $pokemon->getStatusCondicao();
        if ($status == 'Queimado' || $status == 'Envenenado') {
            // Perde 10% da vida máxima por turno
            $dano = max(1, (int)round($pokemon->getHpMaximo() * 0.10));
            $pokemon->receberDano($dano);
            $this->mensagem($pokemon->getNome() . " perdeu " . $dano . " de HP por estar " . strtolower($status) . ".");
            if ($pokemon->estaDesmaiado()) {
                $this->mensagem($pokemon->getNome() . " desmaiou!");
            }
        }
    }

    // Vantagens de tipo delegadas diretamente para o Pokémon (que herda os traits dos tipos)
    public static function multiplicador($tipoAtacante, $tipoAlvo) {
        return Pokemon::calcularMultiplicadorTipos($tipoAtacante, $tipoAlvo);
    }

    // Status elemental delegado diretamente para o Pokémon (que herda os traits dos tipos)
    public static function statusDoTipo($tipoAtacante) {
        return Pokemon::obterStatusPorTipo($tipoAtacante);
    }

    // Aceita o tipo em português (banco) ou inglês (PokéAPI)
    private static function normalizarTipo($tipo) {
        $tipos = [
            'fire' => 'fogo',
            'water' => 'agua',
            'grass' => 'planta',
            'electric' => 'eletrico',
            'raio' => 'eletrico',
            'ghost' => 'fantasma',
            'psychic' => 'psiquico',
            'ground' => 'terra',
            'fighting' => 'luta',
            'poison' => 'veneno'
        ];
        $tipo = strtolower(trim((string)$tipo));
        $tipo = strtr($tipo, [
            'á' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ç' => 'c'
        ]);

        if (isset($tipos[$tipo])) {
            return $tipos[$tipo];
        }
        return $tipo;
    }
}
