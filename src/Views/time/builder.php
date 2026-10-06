<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Montar Time</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <h1>Montar Time Pokémon</h1>

    <!-- Busca -->
    <input type="text" id="campoBusca" placeholder="Nome ou número (ex: pikachu)">
    <button onclick="buscar()">Buscar</button>

    <!-- Pokémon encontrado -->
    <div id="resultado"></div>

    <!-- Time -->
    <h2>Seu time (<span id="contador">0</span>/6)</h2>
    <div id="time"></div>

    <button onclick="salvar()">Salvar Time</button>

    <script src="assets/js/ApiClient.js"></script>
    <script>
        var pokemonAtual = null;
        var time = [];
        var usuarioId = 1; // Ash

        function buscar() {
            var nome = document.getElementById("campoBusca").value;

            buscarPokemon(nome)
                .then(function (pokemon) {
                    pokemonAtual = pokemon;

                    document.getElementById("resultado").innerHTML =
                        "<img src='" + pokemon.imagemArtwork + "' width='150'><br>" +
                        "<b>" + pokemon.nome + "</b> (" + pokemon.tipo + ")<br>" +
                        "HP: " + pokemon.hpMaximo + "<br>" +
                        "Ataque: " + pokemon.ataque + "<br>" +
                        "Defesa: " + pokemon.defesa + "<br>" +
                        "Velocidade: " + pokemon.velocidade + "<br>" +
                        "<button onclick='adicionar()'>Adicionar ao time</button>";
                })
                .catch(function (erro) {
                    alert(erro.message);
                });
        }

        function adicionar() {
            if (time.length >= 6) {
                alert("O time já tem 6 pokémons!");
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

        function salvar() {
            salvarTime(usuarioId, time)
                .then(function (resultado) {
                    alert(resultado.mensagem);
                })
                .catch(function (erro) {
                    alert(erro.message);
                });
        }
    </script>

</body>
</html>
