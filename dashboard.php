<?php
require_once '../Controle-Patrimonial/config/conexao.php';
require_once 'vendor/autoload.php';
session_start();
use Dompdf\Dompdf;

/* =============================
   📄 EXPORTAR PDF
   ============================= */
if (isset($_GET['exportar']) && $_GET['exportar'] === 'pdf') {
  ob_start();
  echo "<h1 style='text-align:center;'>Relatório Completo de Bens Patrimoniais</h1>";
  echo "<p style='text-align:center;'>Gerado em " . date('d/m/Y H:i') . "</p><hr>";

  $dados = $conexao->query("SELECT COUNT(*) AS total_bens, SUM(valor_inicial) AS valor_total FROM bens")->fetch_assoc();
  $ativos = $conexao->query("SELECT COUNT(*) AS ativos FROM bens WHERE status='ativo'")->fetch_assoc();
  $baixados = $conexao->query("SELECT COUNT(*) AS baixados FROM bens WHERE status='baixado'")->fetch_assoc();

  echo "<h2>Resumo Geral</h2>";
  echo "<p><strong>Total de bens:</strong> {$dados['total_bens']}<br>";
  echo "<strong>Valor total:</strong> R$ " . number_format($dados['valor_total'], 2, ',', '.') . "<br>";
  echo "<strong>Ativos:</strong> {$ativos['ativos']}<br>";
  echo "<strong>Baixados:</strong> {$baixados['baixados']}</p>";

  echo "<h2>Bens Ativos</h2>";
  $bens = $conexao->query("SELECT b.descricao, c.nome AS categoria, b.data_aquisicao, b.valor_inicial, b.valor_atual, b.status
    FROM bens b JOIN categorias c ON b.id_categoria=c.id_categoria ORDER BY b.id_bem ASC");
  echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>
    <tr><th>Descrição</th><th>Categoria</th><th>Data de Aquisição</th><th>Valor Inicial</th><th>Valor Atual</th><th>Status</th></tr>";
  while ($b = $bens->fetch_assoc()) {
    echo "<tr>
      <td>{$b['descricao']}</td>
      <td>{$b['categoria']}</td>
      <td>" . date('d/m/Y', strtotime($b['data_aquisicao'])) . "</td>
      <td>R$ " . number_format($b['valor_inicial'], 2, ',', '.') . "</td>
      <td>R$ " . number_format($b['valor_atual'], 2, ',', '.') . "</td>
      <td>{$b['status']}</td>
    </tr>";
  }
  echo "</table>";

  echo "<h2>Bens Baixados</h2>";
  $baixas = $conexao->query("SELECT b.descricao, c.nome AS categoria, bb.data_baixa, bb.motivo 
    FROM baixa_bens bb JOIN bens b ON bb.id_bem=b.id_bem 
    JOIN categorias c ON b.id_categoria=c.id_categoria ORDER BY bb.data_baixa DESC");
  echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>
    <tr><th>Bem</th><th>Categoria</th><th>Data da Baixa</th><th>Motivo</th></tr>";
  while ($bb = $baixas->fetch_assoc()) {
    echo "<tr>
      <td>{$bb['descricao']}</td>
      <td>{$bb['categoria']}</td>
      <td>" . date('d/m/Y', strtotime($bb['data_baixa'])) . "</td>
      <td>{$bb['motivo']}</td>
    </tr>";
  }
  echo "</table>";

  $html = ob_get_clean();
  $dompdf = new Dompdf();
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  $dompdf->stream("Relatorio_Completo_" . date('Y-m-d_His') . ".pdf", ["Attachment" => true]);
  exit;
}

/* =============================
   🆕 CADASTRO / ✏️ EDIÇÃO DE BEM
   ============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $acao = $_POST['acao'] ?? '';
  $descricao = $_POST['descricao'] ?? '';
  $id_categoria = $_POST['id_categoria'] ?? null;
  $valor_inicial = $_POST['valor_inicial'] ?? 0;
  $data_aquisicao = $_POST['data_aquisicao'] ?? '';
  $vida_util = $_POST['vida_util'] ?? 0;

  // NOVO BEM
  if ($acao === 'novo' && empty($_POST['id_bem'])) {
    $valor_atual = $valor_inicial;
    $status = 'ativo';
    $sql_insert = "INSERT INTO bens (descricao, id_categoria, valor_inicial, valor_atual, data_aquisicao, vida_util, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conexao->prepare($sql_insert);
    if ($stmt->execute([$descricao, $id_categoria, $valor_inicial, $valor_atual, $data_aquisicao, $vida_util, $status])) {
      echo "<script>alert('✅ Bem cadastrado com sucesso!'); window.location='?';</script>";
      exit;
    } else {
      echo "<script>alert('❌ Erro ao cadastrar o bem!');</script>";
    }
  }

  // EDITAR BEM
  if ($acao === 'editar' && !empty($_POST['id_bem'])) {
    $id_bem = $_POST['id_bem'];
    $sql_update = "UPDATE bens 
                   SET descricao=?, id_categoria=?, valor_inicial=?, data_aquisicao=?, vida_util=?, valor_atual=? 
                   WHERE id_bem=?";
    $stmt = $conexao->prepare($sql_update);
    if ($stmt->execute([$descricao, $id_categoria, $valor_inicial, $data_aquisicao, $vida_util, $valor_inicial, $id_bem])) {
      echo "<script>alert('✏️ Bem atualizado com sucesso!'); window.location='?';</script>";
      exit;
    } else {
      echo "<script>alert('❌ Erro ao atualizar o bem!');</script>";
    }
  }

  // DAR BAIXA EM UM BEM
  if (isset($_POST['confirmar_baixa'])) {
    $id_bem = (int)$_POST['id_bem_baixa'];
    $data_baixa = $_POST['data_baixa'];
    $motivo = $_POST['motivo_baixa'];
    $conexao->query("UPDATE bens SET status='baixado' WHERE id_bem=$id_bem");
    $stmt = $conexao->prepare("INSERT INTO baixa_bens (id_bem, data_baixa, motivo) VALUES (?, ?, ?)");
    $stmt->execute([$id_bem, $data_baixa, $motivo]);
    echo "<script>alert('📦 Baixa registrada com sucesso!'); window.location='?';</script>";
    exit;
  }
}



/* =============================
   ✏️ EDITAR UM BEM
   ============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_bem'])) {
  $id_bem = $_POST['id_bem'];
  $descricao = $_POST['descricao'];
  $id_categoria = $_POST['id_categoria'];
  $valor_inicial = $_POST['valor_inicial'];
  $data_aquisicao = $_POST['data_aquisicao'];
  $vida_util = $_POST['vida_util'];

  $sql = "UPDATE bens 
          SET descricao=?, id_categoria=?, valor_inicial=?, data_aquisicao=?, vida_util=?, valor_atual=? 
          WHERE id_bem=?";
  $stmt = $conexao->prepare($sql);
  $stmt->execute([$descricao, $id_categoria, $valor_inicial, $data_aquisicao, $vida_util, $valor_inicial, $id_bem]);
}

/* =============================
   📊 CONSULTAS PRINCIPAIS DO DASHBOARD
   ============================= */
$sql_total = "SELECT SUM(valor_inicial) AS total_bens, COUNT(*) AS qtd_bens FROM bens";
$res_total = $conexao->query($sql_total);
$total = $res_total->fetch_assoc();

$sql_contabil = "
  SELECT 
    SUM(valor_atual) AS valor_contabil,
    COUNT(*) AS qtd_ativos
  FROM bens 
  WHERE status = 'ativo'
";
$res_contabil = $conexao->query($sql_contabil);
$contabil = $res_contabil->fetch_assoc();

$sql_dep = "SELECT 
    SUM(b.valor_inicial - COALESCE(b.valor_atual, 0)) AS dep_total
  FROM bens b
  WHERE b.status = 'ativo'
";
$res_dep = $conexao->query($sql_dep);
$dep = $res_dep->fetch_assoc();

$sql_depreciados = "
  SELECT COUNT(*) AS qtd_depreciados 
  FROM bens 
  WHERE valor_atual = 0 AND status = 'ativo'
";
$res_depreciados = $conexao->query($sql_depreciados);
$depreciados = $res_depreciados->fetch_assoc();

$sql_baixados = "SELECT COUNT(*) AS qtd_baixados FROM bens WHERE status = 'baixado'";
$res_baixados = $conexao->query($sql_baixados);
$baixados = $res_baixados->fetch_assoc();

$sql_relatorio = "SELECT 
    c.nome AS categoria,
    COUNT(b.id_bem) AS quantidade,
    SUM(b.valor_inicial) AS valor_total,
    SUM(b.valor_atual) AS valor_contabil
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.status = 'ativo'
  GROUP BY c.nome
  ORDER BY quantidade DESC
";
$res_relatorio = $conexao->query($sql_relatorio);

$sql_recentes = "SELECT 
    b.descricao, 
    c.nome AS categoria, 
    b.data_aquisicao, 
    b.valor_inicial, 
    b.vida_util
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.data_aquisicao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  ORDER BY b.data_aquisicao DESC
";
$res_recentes = $conexao->query($sql_recentes);

/* =============================
   ⚙️ CONSULTAS SECUNDÁRIAS
   ============================= */
$sql_bens = "SELECT 
  b.id_bem,
  b.descricao AS nome,
  c.id_categoria,
  c.nome AS categoria,
  b.valor_inicial,
  b.data_aquisicao,
  b.vida_util,
  COALESCE(b.valor_atual, 0) AS valor_atual,
  COALESCE(SUM(d.valor_depreciado), (b.valor_inicial - COALESCE(b.valor_atual,0))) AS valor_depreciado,
  b.status
FROM bens b
LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
LEFT JOIN depreciacoes d ON b.id_bem = d.id_bem
GROUP BY b.id_bem, b.descricao, c.id_categoria, c.nome, b.valor_inicial, b.data_aquisicao, b.valor_atual, b.status, b.vida_util
ORDER BY status ASC, b.id_bem ASC";

$res_bens = $conexao->query($sql_bens);

/* =============================
   📈 DEPRECIAÇÃO ANUAL
   ============================= */
$dep_anual = "SELECT 
    b.id_bem,
    b.descricao AS nome,
    c.nome AS categoria,
    b.data_aquisicao,
    b.valor_inicial,
    ROUND((1 / b.vida_util) * 100, 2) AS taxa_anual,
    ROUND(b.valor_inicial / b.vida_util, 2) AS depreciacao_anual,
    TIMESTAMPDIFF(YEAR, b.data_aquisicao, CURDATE()) AS anos_decorridos,
    GREATEST(b.vida_util - TIMESTAMPDIFF(YEAR, b.data_aquisicao, CURDATE()), 0) AS anos_restantes
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.status = 'ativo'
";
$res_dep_anual = $conexao->query($dep_anual);
$result_dep_anual = $res_dep_anual->fetch_all(MYSQLI_ASSOC);
$total_dep_anual = array_sum(array_column($result_dep_anual, 'depreciacao_anual'));

// quantidade de bens totalmente depreciados
$totalmente_dep = "SELECT COUNT(*) AS qtd_depreciados FROM bens WHERE valor_atual <= 0 AND status = 'ativo';";
$res_totalmente_dep = $conexao->query($totalmente_dep);
$totalmente_dep = $res_totalmente_dep->fetch_assoc();


$contabil_total = "SELECT SUM(valor_atual) AS valor_contabil_total FROM bens WHERE status = 'ativo'";
$res_contabil_total = $conexao->query($contabil_total);
$cont_total = $res_contabil_total->fetch_assoc();

// Consulta completa para o Relatório de Novos Bens (últimos 12 meses)
// Consulta principal: bens adicionados nos últimos 12 meses
$novos_bens = "SELECT 
    b.id_bem,
    b.descricao AS nome,
    c.nome AS categoria,
    b.data_aquisicao,
    b.valor_inicial,
    b.vida_util
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.data_aquisicao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND b.status = 'ativo'
  ORDER BY b.data_aquisicao DESC
";
$res_novos_bens = $conexao->query($novos_bens);
$n_bens = $res_novos_bens->fetch_all(MYSQLI_ASSOC); // fetch_all para pegar todos

// Valor total dos novos bens (últimos 12 meses)
$total_novos_bens = "SELECT SUM(b.valor_inicial) AS valor_total_novos
  FROM bens b
  WHERE b.data_aquisicao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND b.status = 'ativo'
";
$res_total_novos_bens = $conexao->query($total_novos_bens);
$valorT_novos_bens = $res_total_novos_bens->fetch_assoc();

//Quantidade total de novos bens (últimos 12 meses)
$qtd_novos_bens = "SELECT COUNT(*) AS qtd_novos
  FROM bens b
  WHERE b.data_aquisicao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND b.status = 'ativo'
";
$res_qtd_novos_bens = $conexao->query($qtd_novos_bens);
$qtd_novos = $res_qtd_novos_bens->fetch_assoc();

// Consulta de bens baixados com data da tabela baixa_bens
$baixas_bens = "
  SELECT 
    b.id_bem,
    b.descricao AS nome,
    c.nome AS categoria,
    b.data_aquisicao,
    b.valor_inicial,
    b.valor_atual,
    b.vida_util,
    bb.data_baixa
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  JOIN baixa_bens bb ON b.id_bem = bb.id_bem
  WHERE b.status = 'baixado'
  ORDER BY bb.data_baixa DESC
";
$res_baixas_bens = $conexao->query($baixas_bens);
$bens_baixados = $res_baixas_bens ? $res_baixas_bens->fetch_all(MYSQLI_ASSOC) : [];

// Total e quantidade de bens baixados
$total_baixas = "
  SELECT 
    COUNT(*) AS qtd_baixados,
    SUM(b.valor_inicial) AS valor_total_baixados
  FROM bens b
  JOIN baixa_bens bb ON b.id_bem = bb.id_bem
  WHERE b.status = 'baixado'
";
$res_total_baixas = $conexao->query($total_baixas);
$info_baixas = $res_total_baixas ? $res_total_baixas->fetch_assoc() : ['qtd_baixados' => 0, 'valor_total_baixados' => 0];


$sql_bens_finais = "
  SELECT 
    b.id_bem,
    b.descricao AS nome,
    c.nome AS categoria,
    b.data_aquisicao,
    b.valor_atual,
    b.status,
    GREATEST(0, b.vida_util * 12 - TIMESTAMPDIFF(MONTH, b.data_aquisicao, CURDATE())) AS meses_restantes
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  WHERE b.status = 'ativo'
    AND (b.vida_util * 12 - TIMESTAMPDIFF(MONTH, b.data_aquisicao, CURDATE())) < 12
  ORDER BY meses_restantes ASC
";

$res_bens_finais = $conexao->query($sql_bens_finais);
$result_bens_finais = $res_bens_finais->fetch_all(MYSQLI_ASSOC);

$sql_qtd_bens_finais = "
  SELECT COUNT(*) AS qtd_bens_finais
  FROM bens
  WHERE status = 'ativo'
    AND (vida_util * 12 - TIMESTAMPDIFF(MONTH, data_aquisicao, CURDATE())) < 12
";
$res_qtd_bens_finais = $conexao->query($sql_qtd_bens_finais);
$bens_finais = $res_qtd_bens_finais->fetch_assoc();

// Consulta de bens baixados com motivo da tabela baixa_bens
$baixas_bens = "
  SELECT 
    b.id_bem,
    b.descricao AS nome,
    c.nome AS categoria,
    b.data_aquisicao,
    b.valor_inicial,
    b.valor_atual,
    b.vida_util,
    bb.data_baixa,
    bb.motivo
  FROM bens b
  JOIN categorias c ON b.id_categoria = c.id_categoria
  JOIN baixa_bens bb ON b.id_bem = bb.id_bem
  WHERE b.status = 'baixado'
  ORDER BY bb.data_baixa DESC
";
$res_baixas_bens = $conexao->query($baixas_bens);
$bens_baixados = $res_baixas_bens ? $res_baixas_bens->fetch_all(MYSQLI_ASSOC) : [];

// Total e quantidade de bens baixados
$total_baixas = "
  SELECT 
    COUNT(*) AS qtd_baixados,
    SUM(b.valor_inicial) AS valor_total_baixados
  FROM bens b
  JOIN baixa_bens bb ON b.id_bem = bb.id_bem
  WHERE b.status = 'baixado'
";
$res_total_baixas = $conexao->query($total_baixas);
$info_baixas = $res_total_baixas ? $res_total_baixas->fetch_assoc() : ['qtd_baixados' => 0, 'valor_total_baixados' => 0];

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Controle Patrimonial</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <header>
    <div>
      <h1>Controle Patrimonial</h1>
      <p>Registro de bens e controle de depreciação</p>
    </div>
    <div>
      <button class="btn btn-outline" onclick="window.location='?exportar=pdf'">📄 Exportar Relatório Completo</button>
      <button class="btn btn-primary" id="abrir-modal">+ Novo bem</button>
      <button class="btn btn-danger"
        onclick="if(confirm('Deseja realmente sair da conta?')) window.location='logout.php';">
        SAIR
      </button>
    </div>

    </div>

    <?php
    // Verifica se o usuário está logado
    if (!isset($_SESSION['usuario_nome'])) {
      header('Location: ../Controle-Patrimonial/form/FORMULARIO.html');
      exit;
    }

    // Extrai primeiro nome do email
    $nomeCompleto = $_SESSION['usuario_nome'];
    $partes = explode('@', trim($nomeCompleto));
    $primeiro = $partes[0];
    $usuarioExibido = $primeiro;
    ?>

    </div>
  </header>

  <main>
    <nav>
      <ul>
        <li data-target="dashboard" class="active">Dashboard</li>
        <li data-target="bens">Bens Patrimoniais</li>
        <li data-target="relatorios">
          <!-- <img src="public/icon_relatorio.png" alt=""> -->
          Relatórios
        </li>
      </ul>
    </nav>

    <!-- DASHBOARD -->
    <section id="dashboard" class="active">
      <div class="cards">
        <div class="card">
          <h4>Valor Total dos Bens</h4>
          <h2>R$ <?= number_format($total['total_bens'] ?? 0, 2, ',', '.') ?></h2>
          <span><?= $total['qtd_bens'] ?? 0 ?> bens cadastrados</span>
        </div>
        <div class="card">
          <h4>Valor Contábil</h4>
          <h2>R$ <?= number_format($contabil['valor_contabil'] ?? 0, 2, ',', '.') ?></h2>
          <span><?= $contabil['qtd_ativos'] ?? 0 ?> bens ativos</span>
        </div>
        <div class="card">
          <h4>Depreciação Acumulada</h4>
          <h2>R$ <?= number_format($dep['dep_total'] ?? 0, 2, ',', '.') ?></h2>
          <span><?= $depreciados['qtd_depreciados'] ?? 0 ?> bens totalmente depreciados</span>
        </div>
        <div class="card">
          <h4>Bens Baixados</h4>
          <h2><?= $baixados['qtd_baixados'] ?? 0 ?></h2>
          <span>Removidos do patrimônio</span>
        </div>
      </div>

      <div class="card">
        <h3>Relatório de novos bens (Últimos 6 meses)</h3>
        <div class="scroll-box">
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Valor de Aquisição</th>
                <th>Vida Útil</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($res_recentes && $res_recentes->num_rows > 0): ?>
                <?php while ($bem = $res_recentes->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($bem['descricao']) ?></td>
                    <td><?= htmlspecialchars($bem['categoria']) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($bem['data_aquisicao']))) ?></td>
                    <td>R$ <?= number_format($bem['valor_inicial'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($bem['vida_util']) ?> anos</td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5">Nenhum bem cadastrado nos últimos 6 meses.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
    </section>
    </div>

    <!-- BENS -->
    <section id="bens">
      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Categoria</th>
              <th>Data Aquisição</th>
              <th>Valor Aquisição</th>
              <th>Valor Contábil</th>
              <th>Depreciação</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res_bens && $res_bens->num_rows > 0): ?>
              <?php while ($b = $res_bens->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($b['nome']) ?></td>
                  <td><?= htmlspecialchars($b['categoria']) ?></td>
                  <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                  <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($b['valor_atual'], 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($b['valor_depreciado'], 2, ',', '.') ?></td>
                  <td><?= htmlspecialchars($b['status']) ?></td>
                  <td>
                    <a class="btn-editar" data-id="<?= $b['id_bem'] ?>" data-descricao="<?= htmlspecialchars($b['nome']) ?>"
                      data-categoria="<?= $b['categoria'] ?>" data-valor="<?= $b['valor_inicial'] ?>"
                      data-data="<?= $b['data_aquisicao'] ?>" data-vida="<?= $b['vida_util'] ?>">
                      <img src="public/icon_editar.png" alt="Editar">
                    </a>

                    <?php if ($b['status'] === 'ativo'): ?>
                      <a href="?baixar=<?= $b['id_bem'] ?>" onclick="return confirm('Dar baixa neste bem?')">
                        <img src="public/icon_deletar.png" alt="Dar baixa">
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align:left;">Nenhum bem cadastrado.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>


    <!-- RELATÓRIOS -->
    <!-- RELATÓRIOS -->
    <section id="relatorios">
      <nav class="sub-menu">
        <ul>
          <li class="active" data-subtarget="relatorio-depreciacao">Depreciação Anual</li>
          <li data-subtarget="relatorio-categoria">Por Categoria</li>
          <li data-subtarget="relatorio-contabil">Valor Contábil</li>
          <li data-subtarget="relatorio-depreciados">Depreciados</li>
          <li data-subtarget="relatorio-novos">Novos Bens</li>
          <li data-subtarget="relatorio-baixas">Baixas</li>
          <li data-subtarget="relatorio-vida_util">Vida Útil</li>
        </ul>
      </nav>

      <!-- DEPRECIAÇÃO ANUAL -->
      <div class="subsection active" id="relatorio-depreciacao">
        <div class="card">
          <h4>Relatório de Depreciação Anual</h4>
          <div class="card resumo" style="margin-top:10px; padding:10px;">
            <p><strong>Total de Depreciação Anual</strong></p>
            <h2>R$ <?= number_format($total_dep_anual, 2, ',', '.') ?></h2>
          </div>
          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Taxa Anual (%)</th>
                <th>Depreciação Anual</th>
                <th>Anos Decorridos</th>
                <th>Anos Restantes</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($result_dep_anual)): ?>
                <?php foreach ($result_dep_anual as $result): ?>
                  <tr>
                    <td><?= htmlspecialchars($result['nome']) ?></td>
                    <td><?= htmlspecialchars($result['categoria']) ?></td>
                    <td><?= number_format($result['taxa_anual'] ?? 0, 2, ',', '.') ?>%</td>
                    <td>R$ <?= number_format($result['depreciacao_anual'] ?? 0, 2, ',', '.') ?></td>
                    <td><?= number_format($result['anos_decorridos'] ?? 0, 1, ',', '.') ?></td>
                    <td><?= number_format($result['anos_restantes'] ?? 0, 1, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">Nenhum bem encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

        </div>
      </div>

      <!-- POR CATEGORIA -->
      <div class="subsection" id="relatorio-categoria">
        <div class="card">
          <h4>Relatório por Categoria</h4>
          <table>
            <thead>
              <tr>
                <th>Categoria</th>
                <th>Quantidade</th>
                <th>Valor Total</th>
                <th>Depreciação Acumulada</th>
                <th>Valor Contábil</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($res_relatorio && $res_relatorio->num_rows > 0): ?>
                <?php while ($cat = $res_relatorio->fetch_assoc()): ?>
                  <?php $dep_acum = $cat['valor_total'] - $cat['valor_contabil']; ?>
                  <tr>
                    <td><?= htmlspecialchars($cat['categoria']) ?></td>
                    <td><?= $cat['quantidade'] ?></td>
                    <td>R$ <?= number_format($cat['valor_total'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($dep_acum, 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($cat['valor_contabil'], 2, ',', '.') ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5">Nenhuma categoria com bens ativos.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- VALOR CONTÁBIL -->
      <div class="subsection" id="relatorio-contabil">
        <div class="card">
          <h4>Relatório de Valor Contábil</h4>
          <div class="card resumo" style="margin-top:10px; padding:10px;">
            <p><strong>Valor Contábil Total</strong></p>
            <h2>R$ <?= number_format($cont_total['valor_contabil_total'] ?? 0, 2, ',', '.') ?></h2>
          </div>
          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Valor de Aquisição</th>
                <th>Depreciação Acumulada</th>
                <th>Valor Contábil</th>
                <th>% Depreciado</th>
              </tr>
            </thead>
            <tbody>
              <?php $res_bens = $conexao->query($sql_bens);
              if ($res_bens && $res_bens->num_rows > 0):
                while ($b = $res_bens->fetch_assoc()):
                  $perc = ($b['valor_inicial'] > 0) ? (($b['valor_depreciado'] / $b['valor_inicial']) * 100) : 0; ?>
                  <tr>
                    <td><?= htmlspecialchars($b['nome']) ?></td>
                    <td><?= htmlspecialchars($b['categoria']) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                    <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($b['valor_depreciado'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($b['valor_atual'], 2, ',', '.') ?></td>
                    <td><?= number_format($perc, 2, ',', '.') ?>%</td>
                  </tr>
                <?php endwhile;
              else: ?>
                <tr>
                  <td colspan="7">Nenhum bem cadastrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TOTALMENTE DEPRECIADOS -->
      <div class="subsection" id="relatorio-depreciados">
        <div class="card">
          <h4>Relatório de Bens Totalmente Depreciados</h4>
          <div class="card resumo" style="margin-top:10px; padding:10px;">
            <p><strong>Total de Bens Depreciados</strong></p>
            <h2><?= $totalmente_dep['qtd_depreciados'] ?? 0 ?></h2>
          </div>

          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Valor de Aquisição</th>
                <th>Depreciação Total</th>
                <th>Anos em Uso</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($result_dep_anual)): ?>
                <?php foreach ($result_dep_anual as $r): ?>
                  <?php if (($r['anos_restantes'] ?? 0) <= 0): ?>
                    <tr>
                      <td><?= htmlspecialchars($r['nome']) ?></td>
                      <td><?= htmlspecialchars($r['categoria']) ?></td>
                      <td><?= date('d/m/Y', strtotime($r['data_aquisicao'])) ?></td>
                      <td>R$ <?= number_format($r['valor_inicial'] ?? 0, 2, ',', '.') ?></td>
                      <td>R$ <?= number_format($r['depreciacao_anual'] * ($r['anos_decorridos'] ?? 0), 2, ',', '.') ?></td>
                      <td><?= number_format($r['anos_decorridos'] ?? 0, 0, ',', '.') ?> anos</td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">Nenhum bem totalmente depreciado encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- NOVOS BENS -->
      <div class="subsection" id="relatorio-novos">
        <div class="card">
          <h4>Relatório de Novos Bens (Últimos 12 meses)</h4>
          <div class="card resumo" style="margin-top:10px; padding:10px;">
            <p><strong>Valor Total de Novos Bens</strong></p>
            <h2>R$ <?= number_format($valorT_novos_bens['valor_total_novos'] ?? 0, 2, ',', '.') ?></h2>
            <p><?= $qtd_novos['qtd_novos'] ?? 0 ?> bens adquiridos</p>
          </div>

          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Valor de Aquisição</th>
                <th>Vida Útil (anos)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($n_bens)): ?>
                <?php foreach ($n_bens as $b): ?>
                  <tr>
                    <td><?= htmlspecialchars($b['nome']) ?></td>
                    <td><?= htmlspecialchars($b['categoria']) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                    <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                    <td><?= number_format($b['vida_util'], 1, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5">Nenhum bem encontrado nos últimos 12 meses.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- BAIXAS -->
      <div class="subsection" id="relatorio-baixas">
        <div class="card">
          <h4>Relatório de Bens Baixados</h4>
          <div class="card resumo" style="margin-top:10px; padding:10px;">
            <p><strong>Valor Total de Bens Baixados</strong></p>
            <h2>R$ <?= number_format($info_baixas['valor_total_baixados'] ?? 0, 2, ',', '.') ?></h2>
            <p><?= $info_baixas['qtd_baixados'] ?? 0 ?> bens baixados</p>
          </div>

          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Data de Baixa</th>
                <th>Valor de Aquisição</th>
                <th>Motivo</th>

              </tr>
            </thead>
            <tbody>
              <?php if (!empty($bens_baixados)): ?>
                <?php foreach ($bens_baixados as $b): ?>
                  <tr>
                    <td><?= htmlspecialchars($b['nome']) ?></td>
                    <td><?= htmlspecialchars($b['categoria']) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_baixa'])) ?></td>
                    <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($b['motivo'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">Nenhum bem baixado encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- VIDA ÚTIL -->
      <div class="subsection" id="relatorio-vida_util">
        <div class="card">
          <h4>Relatório de Bens Encerrando Vida Útil</h4>
          <div class="card resumo" style="margin-top:10px; padding:10px;">
            <p><strong>Bens com menos de 1 ano de vida útil</strong></p>
            <h2><?= $bens_finais['qtd_bens_finais'] ?? 0 ?></h2>
          </div>
          <table>
            <thead>
              <tr>
                <th>Bem</th>
                <th>Categoria</th>
                <th>Data de Aquisição</th>
                <th>Valor Contábil</th>
                <th>Meses Restantes</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($result_bens_finais)): ?>
                <?php foreach ($result_bens_finais as $b): ?>
                  <tr>
                    <td><?= htmlspecialchars($b['nome']) ?></td>
                    <td><?= htmlspecialchars($b['categoria']) ?></td>
                    <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                    <td>R$ <?= number_format($b['valor_atual'], 2, ',', '.') ?></td>
                    <td><?= number_format($b['meses_restantes'], 1, ',', '.') ?></td>
                    <td><span class="status"><?= htmlspecialchars($b['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">Nenhum bem próximo do fim da vida útil.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>

  <!-- MODAL NOVO BEM -->
  <div id="modal-novo-bem" class="modal">
    <div class="modal-content">
      <h3>Novo Bem Patrimonial</h3>
      <form id="form-novo-bem" method="POST"  action="?" name="acao">
        <label>Descrição:</label>
        <input type="text" name="descricao" required>

        <label>Categoria:</label>
        <select name="id_categoria" required>
          <option value="">Selecione</option>
          <?php $res_cat = $conexao->query("SELECT DISTINCT id_categoria, nome FROM categorias ORDER BY nome ASC");
          while ($c = $res_cat->fetch_assoc()): ?>
            <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
          <?php endwhile; ?>
        </select>

        <label>Valor de Aquisição (R$):</label>
        <input type="number" step="0.01" name="valor_inicial" required>

        <label>Data de Aquisição:</label>
        <input type="date" name="data_aquisicao" required>

        <label>Vida Útil (anos):</label>
        <input type="number" name="vida_util" required>

        <div class="modal-actions">
          <button type="submit">Salvar</button>
          <button type="button" id="fechar-modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal editar bem -->
  <div id="modal-editar-bem" class="modal">
    <div class="modal-content">
      <h3>Editar Bem Patrimonial</h3>
      <form id="form-editar-bem" method="POST"  name="acao">
        <input type="hidden" name="id_bem">

        <label>Descrição:</label>
        <input type="text" name="descricao" required>

        <label>Categoria:</label>
        <select name="id_categoria" required>
          <?php
          $res_cat = $conexao->query("SELECT id_categoria, nome FROM categorias");
          while ($c = $res_cat->fetch_assoc()): ?>
            <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
          <?php endwhile; ?>
        </select>

        <label>Valor de Aquisição:</label>
        <input type="number" step="0.01" name="valor_inicial" required>

        <label>Data de Aquisição:</label>
        <input type="date" name="data_aquisicao" required>

        <label>Vida Útil (anos):</label>
        <input type="number" name="vida_util" required>

        <div class="modal-actions">
          <button type="submit">Salvar</button>
          <button type="button" id="fechar-editar">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL BAIXA DE BEM -->
  <div id="modal-baixa-bem" class="modal">
    <div class="modal-content">
      <h3>Baixar Bem Patrimonial</h3>
      <p>Informe a data e o motivo da baixa do bem: <strong id="bem-nome-baixa"></strong></p>
      <form id="form-baixa-bem" method="POST" action="?">
        <input type="hidden" name="id_bem_baixa">

        <label>Data da Baixa:</label>
        <input type="date" name="data_baixa" required>

        <label>Motivo da Baixa:</label>
        <textarea name="motivo_baixa" rows="3" required></textarea>

        <div class="modal-actions">
          <button type="submit" name="confirmar_baixa">Confirmar</button>
          <button type="button" id="fechar-baixa">Cancelar</button>
        </div>
      </form>
    </div>
  </div>



  <script>
    const menuItens = document.querySelectorAll('nav ul li[data-target]');
    const secoes = document.querySelectorAll('main section');
    menuItens.forEach(item => {
      item.addEventListener('click', () => {
        menuItens.forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        const alvo = item.getAttribute('data-target');
        secoes.forEach(sec => sec.classList.remove('active'));
        document.getElementById(alvo).classList.add('active');
      });
    });
    const subItens = document.querySelectorAll('.sub-menu li');
    const subSections = document.querySelectorAll('.subsection');
    subItens.forEach(sub => {
      sub.addEventListener('click', () => {
        subItens.forEach(i => i.classList.remove('active'));
        sub.classList.add('active');
        const subTarget = sub.getAttribute('data-subtarget');
        subSections.forEach(s => s.classList.remove('active'));
        document.getElementById(subTarget).classList.add('active');
      });
    });
    const modal = document.getElementById('modal-novo-bem');
    const btnNovoBem = document.getElementById('abrir-modal');
    const btnFechar = document.getElementById('fechar-modal');
    btnNovoBem.addEventListener('click', () => modal.style.display = 'flex');
    btnFechar.addEventListener('click', () => modal.style.display = 'none');
    modal.addEventListener('click', e => {
      if (e.target === modal) modal.style.display = 'none';
    });

    // 🔹 Modal de Edição
    const modalEditar = document.getElementById('modal-editar-bem');
    const btnFecharEditar = document.getElementById('fechar-editar');
    const formEditar = document.getElementById('form-editar-bem');

    document.querySelectorAll('.btn-editar').forEach(btn => {
      btn.addEventListener('click', () => {
        formEditar.id_bem.value = btn.dataset.id;
        formEditar.descricao.value = btn.dataset.descricao;
        formEditar.valor_inicial.value = btn.dataset.valor;
        formEditar.data_aquisicao.value = btn.dataset.data;
        formEditar.vida_util.value = btn.dataset.vida;
        modalEditar.style.display = 'flex';
      });
    });

    btnFecharEditar.addEventListener('click', () => modalEditar.style.display = 'none');
    modalEditar.addEventListener('click', e => {
      if (e.target === modalEditar) modalEditar.style.display = 'none';
    });

    //Modal de Baixa
    const modalBaixa = document.getElementById('modal-baixa-bem');
    const formBaixa = document.getElementById('form-baixa-bem');
    const btnFecharBaixa = document.getElementById('fechar-baixa');
    const bemNomeBaixa = document.getElementById('bem-nome-baixa');

    // Botões de baixa (ícone do lixo)
    document.querySelectorAll('a[href*="?baixar="]').forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault(); // impede a navegação direta
        const id = link.href.split('=')[1];
        const nome = link.closest('tr').querySelector('td:first-child').textContent;

        formBaixa.id_bem_baixa.value = id;
        bemNomeBaixa.textContent = nome;
        modalBaixa.style.display = 'flex';
      });
    });

    btnFecharBaixa.addEventListener('click', () => modalBaixa.style.display = 'none');
    modalBaixa.addEventListener('click', e => {
      if (e.target === modalBaixa) modalBaixa.style.display = 'none';
    });

  </script>

</body>

</html>