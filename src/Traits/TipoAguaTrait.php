<?php

/**
 * Trait TipoAguaTrait
 * Regras do Tipo ÁGUA:
 * - Bate em: Fogo e Terra (x2)
 * - Apanha para: Planta e Elétrico (toma x2 de Planta e Elétrico)
 * - Resistência: Água e Fogo (toma x0.5 de Água e Fogo)
 * - Status elemental: Congelado
 */
trait TipoAguaTrait {
    public static function multiplicadorAgua($tipoAlvo) {
        $tipoAlvo = strtolower(trim((string)$tipoAlvo));

        // Vantagem (dá o dobro de dano x2 em Fogo e Terra)
        if ($tipoAlvo === 'fogo' || $tipoAlvo === 'fire' || $tipoAlvo === 'terra' || $tipoAlvo === 'ground') {
            return 2.0;
        }

        // Resistência do alvo (Água e Planta resistem a Água -> tomam x0.5)
        if ($tipoAlvo === 'agua' || $tipoAlvo === 'água' || $tipoAlvo === 'water' || $tipoAlvo === 'planta' || $tipoAlvo === 'grass') {
            return 0.5;
        }

        // Dano neutro contra Elétrico
        return 1.0;
    }

    public static function statusAgua() {
        return 'Congelado';
    }
}
