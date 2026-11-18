<?php

// Vantagens e desvantagens de um pokémon do tipo NORMAL
trait TipoNormalTrait {
    public static function multiplicadorNormal($tipoAlvo) {
        if ($tipoAlvo === 'fantasma') return 0;
        return 1.0;
    }

    // Ataque normal não causa nenhum status
    public static function statusNormal() {
        return 'Normal';
    }
}
