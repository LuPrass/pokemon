<?php

/**
 * Trait TipoFogoTrait
 * Regras do Tipo FOGO:
 * - Bate em: Planta (x2)
 * - Apanha para: Água e Terra (toma x2 de Água e Terra)
 * - Resistência: Planta e Fogo (toma x0.5 de Planta e Fogo)
 * - Status elemental: Queimado
 */
trait TipoFogoTrait {
    public static function multiplicadorFogo($tipoAlvo) {
        $tipoAlvo = strtolower(trim((string)$tipoAlvo));

        // Vantagem (dá o dobro de dano x2 em Planta)
        if ($tipoAlvo === 'planta' || $tipoAlvo === 'grass') {
            return 2.0;
        }

        // Resistência do alvo (Fogo e Água resistem a Fogo -> tomam x0.5)
        if ($tipoAlvo === 'fogo' || $tipoAlvo === 'fire' || $tipoAlvo === 'agua' || $tipoAlvo === 'água' || $tipoAlvo === 'water') {
            return 0.5;
        }

        // Dano neutro contra Terra e Elétrico
        return 1.0;
    }

    public static function statusFogo() {
        return 'Queimado';
    }
}
