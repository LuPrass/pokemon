<?php

// Vantagens e desvantagens de um pokémon do tipo PLANTA
trait TipoPlantaTrait {
    public function multiplicadorPlanta($tipoAlvo) {
        if ($tipoAlvo == 'agua') return 2;
        if ($tipoAlvo == 'fogo') return 0.5;
        if ($tipoAlvo == 'planta') return 0.5;
        return 1;
    }
}
