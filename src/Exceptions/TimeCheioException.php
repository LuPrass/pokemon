<?php

class TimeCheioException extends Exception {
    public function __construct(string $mensagem = "O time já atingiu o limite máximo de 6 Pokémon!") {
        parent::__construct($mensagem);
    }
}
