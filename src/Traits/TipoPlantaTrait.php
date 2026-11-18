<?php

// Vantagens e desvantagens de um pokémon do tipo PLANTA
trait TipoPlantaTrait {
    public static function multiplicadorPlanta($tipoAlvo) {
        if ($tipoAlvo === 'agua' || $tipoAlvo === 'terra') return 2;
        if ($tipoAlvo === 'fogo' || $tipoAlvo === 'planta') return 0.5;
        return 1;
    }

    // Status que um ataque de planta pode causar em quem recebe
    public static function statusPlanta() {
        return 'Envenenado';
    }
}
