<?php

// Regras da batalha entre dois pokémons (não mexe no banco de dados)
class Batalha {
    use TipoFogoTrait, TipoAguaTrait, TipoPlantaTrait, TipoEletricoTrait;

    private $jogador;
    private $inimigo;
    private $turnos = [];
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

    // Faz a luta inteira, turno a turno, até alguém desmaiar
    public function lutar() {
        if ($this->jogador->estaDesmaiado()) {
            throw new PokemonDesmaiadoException();
        }

        $limite = 100; // segurança para nunca ficar em loop infinito
        while (!$this->jogador->estaDesmaiado() && !$this->inimigo->estaDesmaiado() && $limite > 0) {
            // Quem tem maior prioridade (e depois velocidade) ataca primeiro
            if ($this->jogadorAtacaPrimeiro()) {
                $this->atacar($this->jogador, $this->inimigo);
                if (!$this->inimigo->estaDesmaiado()) {
                    $this->atacar($this->inimigo, $this->jogador);
                }
            } else {
                $this->atacar($this->inimigo, $this->jogador);
                if (!$this->jogador->estaDesmaiado()) {
                    $this->atacar($this->jogador, $this->inimigo);
                }
            }
            $limite--;
        }

        if ($this->inimigo->estaDesmaiado()) {
            $this->resultado = 'vitoria';
        } else {
            $this->resultado = 'derrota';
        }
        return $this->resultado;
    }

    private function jogadorAtacaPrimeiro() {
        if ($this->jogador->getPrioridade() != $this->inimigo->getPrioridade()) {
            return $this->jogador->getPrioridade() > $this->inimigo->getPrioridade();
        }
        return $this->jogador->getVelocidade() >= $this->inimigo->getVelocidade();
    }

    private function atacar(Pokemon $atacante, Pokemon $defensor) {
        $dano = $this->calcularDano($atacante, $defensor);
        $defensor->receberDano($dano);
        $this->turnos[] = $atacante->getNome() . " atacou " . $defensor->getNome() .
            " e causou " . $dano . " de dano (HP restante: " . $defensor->getHpAtual() . ")";
    }

    private function calcularDano(Pokemon $atacante, Pokemon $defensor) {
        $base = ((($atacante->getNivel() * 2 / 5) + 2) * 40 * $atacante->getAtaque() / $defensor->getDefesa()) / 50 + 2;
        $multiplicador = $this->multiplicador($atacante->getTipo(), $defensor->getTipo());
        $dano = (int) round($base * $multiplicador);
        if ($dano < 1) {
            $dano = 1;
        }
        return $dano;
    }

    // Usa o trait do tipo do atacante para descobrir o multiplicador
    public function multiplicador($tipoAtacante, $tipoAlvo) {
        $atacante = $this->normalizarTipo($tipoAtacante);
        $alvo = $this->normalizarTipo($tipoAlvo);

        if ($atacante == 'fogo') return $this->multiplicadorFogo($alvo);
        if ($atacante == 'agua') return $this->multiplicadorAgua($alvo);
        if ($atacante == 'planta') return $this->multiplicadorPlanta($alvo);
        if ($atacante == 'eletrico') return $this->multiplicadorEletrico($alvo);
        return 1;
    }

    // Aceita o tipo em português (banco) ou inglês (PokéAPI)
    private function normalizarTipo($tipo) {
        $tipo = strtolower(trim($tipo));

        if ($tipo == 'fogo' || $tipo == 'fire') return 'fogo';
        if ($tipo == 'agua' || $tipo == 'água' || $tipo == 'water') return 'agua';
        if ($tipo == 'planta' || $tipo == 'grass') return 'planta';
        if ($tipo == 'raio' || $tipo == 'eletrico' || $tipo == 'elétrico' || $tipo == 'electric') return 'eletrico';
        return $tipo;
    }
}
