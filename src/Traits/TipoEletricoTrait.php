<?php

// Vantagens e desvantagens de um pokémon do tipo ELÉTRICO
trait TipoEletricoTrait {
    public static function multiplicadorEletrico($tipoAlvo) {
        if ($tipoAlvo === 'agua') return 2;
        if ($tipoAlvo === 'terra') return 0;
        if ($tipoAlvo === 'planta' || $tipoAlvo === 'eletrico') return 0.5;
        return 1;
    }

    // Status que um ataque elétrico pode causar em quem recebe
    public static function statusEletrico() {
        return 'Paralisado';
    }
}
