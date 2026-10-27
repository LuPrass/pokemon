<?php

// Vantagens e desvantagens de um pokémon do tipo FANTASMA
trait TipoFantasmaTrait {
    public function multiplicadorFantasma($tipoAlvo) {
        if ($tipoAlvo == 'psiquico' || $tipoAlvo == 'psychic') return 2;
        if ($tipoAlvo == 'fantasma' || $tipoAlvo == 'ghost') return 0.5;
        if ($tipoAlvo == 'normal') return 0;
        return 1;
    }
}
