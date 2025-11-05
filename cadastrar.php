<?php 
require_once '../ControleDePatrimonio/config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descricao = $_POST['descricao'];
    $categoria = $_POST['categoria'];
    $valor = $_POST['valor_inicial'];
    $data = $_POST['data_aquisicao'];
    $vida = $_POST['vida_util'];

    $sql = "INSERT INTO bens (descricao, categoria, valor_inicial, data_aquisicao, vida_util)
            VALUES ('$descricao', '$categoria', '$valor', '$data', '$vida')";
    $conexao->query($sql);

    header("Location: bens.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="UTF-8"><title>Cadastrar Bem</title></head>
<body>
<h1>Cadastrar Bem</h1>
<form method="post">
  <label>Descrição:</label><br>
  <input type="text" name="descricao" required><br><br>

  <label>Categoria:</label><br>
  <input type="text" name="categoria"><br><br>

  <label>Valor inicial:</label><br>
  <input type="number" step="0.01" name="valor_inicial" required><br><br>

  <label>Data aquisição:</label><br>
  <input type="date" name="data_aquisicao" required><br><br>

  <label>Vida útil (anos):</label><br>
  <input type="number" name="vida_util" required><br><br>

  <button type="submit">Salvar</button>
</form>

<p><a href="bens.php">Voltar</a></p>
</body>
</html>
