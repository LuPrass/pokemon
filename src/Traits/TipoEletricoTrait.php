<?php

// Vantagens e desvantagens de um pokémon do tipo ELÉTRICO
trait TipoEletricoTrait {
    public function multiplicadorEletrico($tipoAlvo) {
        if ($tipoAlvo == 'agua') return 2;
        if ($tipoAlvo == 'planta') return 0.5;
        if ($tipoAlvo == 'eletrico') return 0.5;
        return 1;
    }
}
