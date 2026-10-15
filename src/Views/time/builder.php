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

    <!-- Aqui entrarão os pokémons pré-definidos para escolher -->

    <!-- Time -->
    <h2>Seu time (<span id="contador">0</span>/3)</h2>
    <div id="time"></div>

    <button onclick="salvar()">Salvar Time</button>

    <script src="assets/js/ApiClient.js"></script>
    <script>
        var time = [];
        var usuarioId = <?php echo (int)($_SESSION['usuario_id'] ?? 1); ?>;

        // Recebe um pokémon (objeto) e coloca no time.
        // Os pokémons pré-definidos serão ligados a esta função depois.
        function adicionar(pokemon) {
            if (time.length >= 3) {
                alert("O time já tem 3 pokémons!");
                return;
            }
            time.push(pokemon);
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
