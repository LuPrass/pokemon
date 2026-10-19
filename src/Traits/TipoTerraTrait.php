<?php

// Vantagens e desvantagens de um pokémon do tipo TERRA
trait TipoTerraTrait {
    public function multiplicadorTerra($tipoAlvo) {
        if ($tipoAlvo == 'fogo') return 2;
        if ($tipoAlvo == 'eletrico') return 2;
        if ($tipoAlvo == 'planta') return 0.5;
        return 1;
    }
}
