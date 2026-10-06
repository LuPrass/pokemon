<?php

class SaldoInsuficienteException extends Exception {
    public function __construct(string $mensagem = "Saldo de Pokédollars insuficiente para realizar esta compra!") {
        parent::__construct($mensagem);
    }
}
