<?php

// Vantagens e desvantagens de um pokémon do tipo ÁGUA
trait TipoAguaTrait {
    public function multiplicadorAgua($tipoAlvo) {
        if ($tipoAlvo == 'fogo') return 2;
        if ($tipoAlvo == 'planta') return 0.5;
        if ($tipoAlvo == 'agua') return 0.5;
        return 1;
    }
}
