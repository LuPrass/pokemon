<?php

// Vantagens e desvantagens de um pokémon do tipo TERRA
trait TipoTerraTrait {
    public static function multiplicadorTerra($tipoAlvo) {
        if ($tipoAlvo === 'fogo' || $tipoAlvo === 'eletrico') return 2;
        if ($tipoAlvo === 'planta') return 0.5;
        return 1;
    }

    // Ataque de terra não causa nenhum status
    public static function statusTerra() {
        return 'Normal';
    }
}
