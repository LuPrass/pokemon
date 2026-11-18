<?php

// Vantagens e desvantagens de um pokémon do tipo FANTASMA
trait TipoFantasmaTrait {
    public static function multiplicadorFantasma($tipoAlvo) {
        if ($tipoAlvo === 'psiquico') return 2;
        if ($tipoAlvo === 'fantasma') return 0.5;
        if ($tipoAlvo === 'normal') return 0;
        return 1;
    }

    // Status que um ataque fantasma pode causar em quem recebe (a lambida deixa paralisado)
    public static function statusFantasma() {
        return 'Paralisado';
    }
}
