<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../verifica_login.php';

$meuTime = $_SESSION['meu_time'] ?? [];
$inimigosRestantes = $_SESSION['inimigos_restantes'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arena de Batalha Pokémon</title>
    <style>
        :root {
            --primary: #ef5350;
            --secondary: #3b4cca;
            --bg: #f4f4f9;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .topo-nav {
            max-width: 1100px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-link {
            text-decoration: none;
            color: #fff;
            background: var(--secondary);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-right: 10px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        h1 { color: var(--secondary); text-align: center; margin-bottom: 8px; }
        .subtitulo { text-align: center; color: #666; margin-bottom: 30px; }
        
        .secao-titulo {
            border-bottom: 2px solid #eee;
            padding-bottom: 8px;
            margin: 25px 0 15px 0;
            color: #333;
        }
        .grid-time {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card-poke {
            background: #fdfdfd;
            border: 2px solid #e0e6ed;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            transition: transform 0.2s;
        }
        .card-poke:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }
        .card-poke img {
            width: 110px;
            height: 110px;
            object-fit: contain;
        }
        .card-poke h3 {
            margin: 10px 0 5px 0;
            text-transform: capitalize;
            color: var(--secondary);
        }
        .stats-mini {
            font-size: 0.85rem;
            color: #555;
            text-align: left;
            margin-top: 10px;
            background: #f4f4f9;
            padding: 8px 12px;
            border-radius: 6px;
        }
        .inimigos-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .inimigo-chip {
            background: #f0f0f5;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #444;
            border: 1px solid #ddd;
        }
        .aviso-vazio {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ffeeba;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="topo-nav">
        <div>
            <a href="index.php?rota=menu" class="btn-link">← Menu Principal</a>
            <a href="index.php?rota=builder" class="btn-link" style="background: #555;">⚙ Modificar Time</a>
        </div>
        <?php if (!empty($_SESSION['usuario'])): ?>
            <span>Treinador: <b><?php echo htmlspecialchars($_SESSION['usuario']); ?></b></span>
        <?php endif; ?>
    </div>

    <div class="container">
        <h1>Arena de Batalha</h1>
        <p class="subtitulo">Seu time foi montado com sucesso e está registrado na Sessão do PHP!</p>

        <h2 class="secao-titulo">Seu Time Selecionado (<?php echo count($meuTime); ?>)</h2>
        <?php if (empty($meuTime)): ?>
            <div class="aviso-vazio">
                Nenhum Pokémon foi selecionado ainda. <a href="index.php?rota=builder">Clique aqui para montar sua equipe no Builder</a>.
            </div>
        <?php else: ?>
            <div class="grid-time">
                <?php foreach ($meuTime as $p): ?>
                    <div class="card-poke">
                        <img src="<?php echo htmlspecialchars($p['img'] ?? ''); ?>" alt="<?php echo htmlspecialchars($p['name'] ?? ''); ?>">
                        <h3><?php echo htmlspecialchars($p['name'] ?? ''); ?></h3>
                        <div class="stats-mini">
                            <?php if (isset($p['hp'])): ?><div><b>HP:</b> <?php echo (int)$p['hp']; ?></div><?php endif; ?>
                            <?php if (isset($p['attack'])): ?><div><b>Ataque:</b> <?php echo (int)$p['attack']; ?></div><?php endif; ?>
                            <?php if (isset($p['defense'])): ?><div><b>Defesa:</b> <?php echo (int)$p['defense']; ?></div><?php endif; ?>
                            <?php if (isset($p['speed'])): ?><div><b>Velocidade:</b> <?php echo (int)$p['speed']; ?></div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="secao-titulo">Inimigos Restantes Disponíveis (<?php echo count($inimigosRestantes); ?>)</h2>
        <div class="inimigos-chips">
            <?php foreach ($inimigosRestantes as $inimigoId): ?>
                <span class="inimigo-chip">Pokémon #<?php echo (int)$inimigoId; ?></span>
            <?php endforeach; ?>
        </div>
    </div>

</body>
</html>
