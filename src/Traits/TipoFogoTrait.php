<?php

// Vantagens e desvantagens de um pokémon do tipo FOGO
trait TipoFogoTrait {
    public function multiplicadorFogo($tipoAlvo) {
        if ($tipoAlvo == 'planta') return 2;
        if ($tipoAlvo == 'agua') return 0.5;
        if ($tipoAlvo == 'fogo') return 0.5;
        return 1;
    }
}
