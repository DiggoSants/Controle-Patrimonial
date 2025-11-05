<?php 
require_once '../ControleDePatrimonio/config/conexao.php'; 
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Bens Cadastrados</title>
</head>
<body>
<h1>Bens</h1>
<p><a href="cadastrar.php">Cadastrar novo bem</a> | <a href="index.php">Voltar</a></p>

<table border="1" cellpadding="6">
<tr>
  <th>ID</th>
  <th>Descrição</th>
  <th>Categoria</th>
  <th>Valor Inicial</th>
  <th>Data Aquisição</th>
  <th>Vida útil</th>
  <th>Valor Atual</th>
  <th>Ações</th>
</tr>

<?php
$sql = "SELECT * FROM bens WHERE situacao='ativo'";
$res = $conexao->query($sql);

while ($linha = $res->fetch_assoc()) {
    $anos = date('Y') - date('Y', strtotime($linha['data_aquisicao']));
    $dep = $linha['valor_inicial'] / $linha['vida_util'];
    $valor_atual = max($linha['valor_inicial'] - ($dep * $anos), 0);

    echo "<tr>
            <td>{$linha['id']}</td>
            <td>{$linha['descricao']}</td>
            <td>{$linha['categoria']}</td>
            <td>R$ ".number_format($linha['valor_inicial'],2,',','.')."</td>
            <td>{$linha['data_aquisicao']}</td>
            <td>{$linha['vida_util']}</td>
            <td>R$ ".number_format($valor_atual,2,',','.')."</td>
            <td>
              <a href='editar.php?id={$linha['id']}'>Editar</a> |
              <a href='baixar.php?id={$linha['id']}' onclick='return confirm(\"Baixar este bem?\")'>Baixar</a>
            </td>
          </tr>";
}
?>
</table>
</body>
</html>
