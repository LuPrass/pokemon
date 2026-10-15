<?php
require_once __DIR__ . '/../../../verifica_login.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Montar Time</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
        <h2>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Treinador'); ?>!</h2>
        <a href="logout.php" style="color: #e3350d; font-weight: bold; text-decoration: none;">Sair (Logout)</a>
    </div>

    <h1>Montar Time Pokémon</h1>

    <!-- Busca -->
    <input type="text" id="campoBusca" placeholder="Nome ou número (ex: pikachu)">
    <button onclick="buscar()">Buscar</button>

    <!-- Pokémon encontrado -->
    <div id="resultado"></div>

    <!-- Time -->
    <h2>Seu time (<span id="contador">0</span>/3)</h2>
    <div id="time"></div>

    <button onclick="salvar()">Salvar Time</button>

    <script src="assets/js/ApiClient.js"></script>
    <script>
        var pokemonAtual = null;
        var time = [];
        var usuarioId = <?php echo (int)($_SESSION['usuario_id'] ?? 1); ?>;

        async function buscar() {
            var nome = document.getElementById("campoBusca").value;

            try {
                var pokemon = await buscarPokemon(nome);
                pokemonAtual = pokemon;
                mostrarPokemon(pokemon);
            } catch (erro) {
                alert(erro.message);
            }
        }

        function mostrarPokemon(pokemon) {
            document.getElementById("resultado").innerHTML =
                "<img src='" + pokemon.imagemArtwork + "' width='150'><br>" +
                "<b>" + pokemon.nome + "</b> (" + pokemon.tipo + ")<br>" +
                "HP: " + pokemon.hpMaximo + "<br>" +
                "Ataque: " + pokemon.ataque + "<br>" +
                "Defesa: " + pokemon.defesa + "<br>" +
                "Velocidade: " + pokemon.velocidade + "<br>" +
                "<button onclick='adicionar()'>Adicionar ao time</button>";
        }

        function adicionar() {
            if (time.length >= 3) {
                alert("O time já tem 3 pokémons!");
                return;
            }
            time.push(pokemonAtual);
            mostrarTime();
        }

        function remover(posicao) {
            time.splice(posicao, 1);
            mostrarTime();
        }

        function mostrarTime() {
            var html = "";

            for (var i = 0; i < time.length; i++) {
                html += "<div>" +
                    "<img src='" + time[i].imagemFrente + "'>" +
                    time[i].nome + " " +
                    "<button onclick='remover(" + i + ")'>Remover</button>" +
                    "</div>";
            }

            document.getElementById("time").innerHTML = html;
            document.getElementById("contador").innerHTML = time.length;
        }

        async function salvar() {
            try {
                var resultado = await salvarTime(usuarioId, time);
                alert(resultado.mensagem);
            } catch (erro) {
                alert(erro.message);
            }
        }
    </script>

</body>
</html>
