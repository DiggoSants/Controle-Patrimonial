<?php
require_once '../ControleDePatrimonio/config/conexao.php'; ?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Relatórios</title>
</head>

<body>
    <h1>Relatórios</h1>
    <p><a href="index.php">Voltar</a></p>

    <?php
    $total = $conexao->query("SELECT SUM(valor_inicial) as total FROM bens WHERE situacao='ativo'")->fetch_assoc()['total'];
    echo "<h3>Valor total dos bens: R$ " . number_format($total, 2, ',', '.') . "</h3>";

    $sql = "SELECT categoria, SUM(valor_inicial) as soma FROM bens WHERE situacao='ativo' GROUP BY categoria";
    $res = $conexao->query($sql);

    echo "<h3>Por categoria:</h3><ul>";
    while ($r = $res->fetch_assoc()) {
        echo "<li>{$r['categoria']} — R$ " . number_format($r['soma'], 2, ',', '.') . "</li>";
    }
    echo "</ul>";

    $sql = "SELECT descricao, data_aquisicao, vida_util FROM bens WHERE situacao='ativo'";
    $res = $conexao->query($sql);

    echo "<h3>Encerrando vida útil (último ano):</h3>";
    while ($b = $res->fetch_assoc()) {
        $anos = date('Y') - date('Y', strtotime($b['data_aquisicao']));
        if ($anos >= $b['vida_util'] - 1) {
            echo "<p>{$b['descricao']} — adquirido em {$b['data_aquisicao']}</p>";
        }
    }
    ?>
</body>

</html>