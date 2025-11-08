<?php
require_once '../Controle-Patrimonial/config/conexao.php';

/* =============================
   📊 CONSULTAS PRINCIPAIS DO DASHBOARD
   ============================= */

// 1️⃣ Valor total e quantidade de bens
$sql_total = "SELECT SUM(valor_inicial) AS total_bens, COUNT(*) AS qtd_bens FROM bens";
$res_total = $conn->query($sql_total);
$total = $res_total->fetch_assoc();

// 2️⃣ Valor contábil e quantidade de bens ativos
$sql_contabil = "
  SELECT 
    SUM(valor_atual) AS valor_contabil,
    COUNT(*) AS qtd_ativos
  FROM bens 
  WHERE status = 'ativo'
";
$res_contabil = $conn->query($sql_contabil);
$contabil = $res_contabil->fetch_assoc();

// 3️⃣ Depreciação acumulada e bens totalmente depreciados
$sql_dep = "SELECT 
    SUM(b.valor_inicial - COALESCE(b.valor_atual, 0)) AS dep_total
  FROM bens b
  WHERE b.status = 'ativo'
";
$res_dep = $conn->query($sql_dep);
$dep = $res_dep->fetch_assoc();

$sql_depreciados = "
  SELECT COUNT(*) AS qtd_depreciados 
  FROM bens 
  WHERE valor_atual = 0 AND status = 'ativo'
";
$res_depreciados = $conn->query($sql_depreciados);
$depreciados = $res_depreciados->fetch_assoc();

// 4️⃣ Bens baixados
$sql_baixados = "SELECT COUNT(*) AS qtd_baixados FROM bens WHERE status = 'baixado'";
$res_baixados = $conn->query($sql_baixados);
$baixados = $res_baixados->fetch_assoc();

// 5️⃣ Relatório por categoria
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
$res_relatorio = $conn->query($sql_relatorio);

// 6️⃣ Relatório de novos bens (últimos 6 meses)
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
$res_recentes = $conn->query($sql_recentes);

/* =============================
   ⚙️ CONSULTAS SECUNDÁRIAS
   ============================= */
$sql_bens = "SELECT 
  b.id_bem,
  b.descricao AS nome,
  c.nome AS categoria,
  b.valor_inicial,
  b.data_aquisicao,
  COALESCE(b.valor_atual, 0) AS valor_atual,
  COALESCE(SUM(d.valor_depreciado), (b.valor_inicial - COALESCE(b.valor_atual,0))) AS valor_depreciado,
  b.status
FROM bens b
LEFT JOIN categorias c ON b.id_categoria = c.id_categoria
LEFT JOIN depreciacoes d ON b.id_bem = d.id_bem
GROUP BY b.id_bem, b.descricao, c.nome, b.valor_inicial, b.data_aquisicao, b.valor_atual, b.status
ORDER BY b.id_bem ASC
";
$res_bens = $conn->query($sql_bens);

$dep_anual = "SELECT 
  b.descricao AS nome,
  c.nome AS categoria,
  ROUND((1 / b.vida_util) * 100, 2) AS taxa_anual, 
  ROUND(b.valor_inicial / b.vida_util, 2) AS depreciacao_anual,
  TIMESTAMPDIFF(YEAR, b.data_aquisicao, CURDATE()) AS anos_decorridos,
  GREATEST(b.vida_util - TIMESTAMPDIFF(YEAR, b.data_aquisicao, CURDATE()), 0) AS anos_restantes
FROM bens b
JOIN categorias c ON b.id_categoria = c.id_categoria
WHERE b.status = 'ativo'
";
$res_dep_anual = $conn->query($dep_anual);
$result_dep_anual = $res_dep_anual->fetch_all(MYSQLI_ASSOC);
$total_dep_anual = array_sum(array_column($result_dep_anual, 'depreciacao_anual'));
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Controle Patrimonial</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f5f5f5;
    }

    nav ul {
      display: flex;
      list-style: none;
      gap: 20px;
      margin: 0;
      padding: 0;
    }

    nav li {
      cursor: pointer;
      padding: 8px 14px;
      border-radius: 4px;
    }

    nav li:hover,
    nav li.active {
      background: #34495e;
      color: white;
    }

    main {
      padding: 30px;
    }

    section {
      display: none;
    }

    section.active {
      display: block;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
    }

    .card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .card h3,
    .card h4 {
      margin: 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    th,
    td {
      border-bottom: 1px solid #ddd;
      padding: 10px;
      text-align: left;
    }

    /* 🔹 Estilo do sub-menu */
    .sub-menu ul {
      display: flex;
      gap: 15px;
      list-style: none;
      padding: 0;
      margin: 15px 0;
    }

    .sub-menu li {
      padding: 6px 12px;
      border-radius: 4px;
      background: #e0e0e0;
      cursor: pointer;
      transition: background 0.2s;
    }

    .sub-menu li:hover {
      background: #34495e;
      color: white;
    }

    .sub-menu li.active {
      background: #34495e;
      color: white;
    }

    /* 🔹 Controla visibilidade das subseções */
    .subsection {
      display: none;
      margin-top: 20px;
    }

    .subsection.active {
      display: block;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      justify-content: center;
      align-items: center;
    }

    /* Conteúdo do modal */
    .modal-content {
      background: #fff;
      padding: 25px;
      border-radius: 10px;
      width: 400px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
      text-align: left;
      animation: fadeIn 0.2s ease;
    }

    .modal-content h3 {
      margin-top: 0;
      text-align: center;
    }

    .modal-content label {
      display: block;
      margin-top: 10px;
      font-weight: bold;
    }

    .modal-content input,
    .modal-content select {
      width: 100%;
      padding: 8px;
      margin-top: 4px;
      border: 1px solid #ccc;
      border-radius: 5px;
    }

    .modal-actions {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
    }

    .modal-actions button {
      padding: 8px 14px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .modal-actions button[type="submit"] {
      background: #2ecc71;
      color: white;
    }

    .modal-actions button#fechar-modal {
      background: #e74c3c;
      color: white;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }

      to {
        opacity: 1;
        transform: scale(1);
      }
    }
  </style>
</head>

<body>
  <header>
    <div class="card-header">
      <h1>Controle Patrimonial</h1>
      <p>Registro de bens e controle de depreciação</p>
      <button>Exportar</button>
      <button id="abrir-modal">Novo bem</button>
    </div>
  </header>

  <main>
    <nav>
      <ul>
        <li data-target="dashboard" class="active">Dashboard</li>
        <li data-target="bens">Bens Patrimoniais</li>
        <li data-target="relatorios">Relatórios</li>
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

      <!-- RELATÓRIO POR CATEGORIA -->
      <div class="card">
        <h3>Relatório por Categoria</h3>
        <table>
          <thead>
            <tr>
              <th>Categoria</th>
              <th>Quantidade</th>
              <th>Valor Total</th>
              <th>Valor Contábil</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res_relatorio && $res_relatorio->num_rows > 0): ?>
              <?php while ($cat = $res_relatorio->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($cat['categoria']) ?></td>
                  <td><?= $cat['quantidade'] ?></td>
                  <td>R$ <?= number_format($cat['valor_total'], 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($cat['valor_contabil'], 2, ',', '.') ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4">Nenhuma categoria com bens ativos.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- NOVOS BENS -->
      <div class="card">
        <h3>Relatório de novos bens (Últimos 6 meses)</h3>
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

    <!-- bens patrimoniais -->
    <section id="bens">
      <div>
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Categoria</th>
              <th>Valor Aquisição</th>
              <th>Data Aquisição</th>
              <th>Valor Contábil</th>
              <th>Depreciação</th>
              <th>Situação</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($b = $res_bens->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($b['nome']) ?></td>
                <td><?= htmlspecialchars($b['categoria']) ?></td>
                <td>R$ <?= number_format($b['valor_inicial'], 2, ',', '.') ?></td>
                <td><?= date('d/m/Y', strtotime($b['data_aquisicao'])) ?></td>
                <td>R$ <?= number_format($b['valor_atual'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($b['valor_depreciado'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($b['status']) ?></td>
                <td>
                  <button></button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section id="relatorios">
      <nav class="sub-menu">
        <ul>
          <li class="active" data-subtarget="relatorio-depreciacao">Depreciação Anual</li>
          <li data-subtarget="relatorio-categoria">Por Categoria</li>
          <li data-subtarget="relatorio-contabil">Valor Contábil</li>
          <li data-subtarget="relatorio-depreciados">Depreciados</li>
          <li data-subtarget="relatorio-novos">Novos Bens</li>
          <li data-subtarget="relatorio-baixas">Baixas</li>
          <li data-subtarget="relatorio-vida">Vida Útil</li>
        </ul>
      </nav>

      <!-- 🔹 Sub-seções (conteúdo interno de cada item) -->
      <div class="subsection active" id="relatorio-depreciacao">
        <div class="card">
          <h4>Relatório de Depreciação Anual</h4>

          <div class="card" style="margin-top:10px; padding:10px;">
            <span><strong>Total de Depreciação Anual</strong></span><br>
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
                    <td><?= number_format($result['taxa_anual'], 2, ',', '.') ?>%</td>
                    <td>R$ <?= number_format($result['depreciacao_anual'], 2, ',', '.') ?></td>
                    <td><?= (int) $result['anos_decorridos'] ?> anos</td>
                    <td><?= (int) $result['anos_restantes'] ?> anos</td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">Nenhum bem encontrado.</td>0
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="subsection" id="relatorio-categoria">
        <h3>Relatório por Categoria</h3>
        <p>Aqui vai o relatório agrupado por categoria.</p>
      </div>

      <div class="subsection" id="relatorio-contabil">
        <h3>Valor Contábil</h3>
        <p>Mostra os valores contábeis por grupo de bens.</p>
      </div>

      <div class="subsection" id="relatorio-depreciados">
        <h3>Bens Depreciados</h3>
        <p>Lista de bens totalmente depreciados.</p>
      </div>

      <div class="subsection" id="relatorio-novos">
        <h3>Novos Bens</h3>
        <p>Tabela dos bens cadastrados nos últimos 6 meses.</p>
      </div>

      <div class="subsection" id="relatorio-baixas">
        <h3>Bens Baixados</h3>
        <p>Relatório de baixas e remoções patrimoniais.</p>
      </div>

      <div class="subsection" id="relatorio-vida">
        <h3>Vida Útil</h3>
        <p>Informações sobre a vida útil restante de cada bem.</p>
      </div>
    </section>
  </main>

  <!-- 🔹 MODAL DE CADASTRO DE NOVO BEM -->
  <div id="modal-novo-bem" class="modal">
    <div class="modal-content">
      <h3>Novo Bem Patrimonial</h3>
      <form id="form-novo-bem" method="POST" action="salvar_bem.php">
        <label>Descrição:</label>
        <input type="text" name="descricao" required>

        <label>Categoria:</label>
        <select name="id_categoria" required>
          <option value="">Selecione</option>
          <?php
          $res_cat = $conn->query("SELECT id_categoria, nome FROM categorias GROUP BY nome");
          while ($c = $res_cat->fetch_assoc()):
            ?>
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

  <script>
    // MENU PRINCIPAL
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

    // SUBMENU DE RELATÓRIOS
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

    // 🔹 Modal Novo Bem
    const modal = document.getElementById('modal-novo-bem');
    const btnNovoBem = document.getElementById('abrir-modal');
    const btnFechar = document.getElementById('fechar-modal');

    btnNovoBem.addEventListener('click', () => modal.style.display = 'flex');
    btnFechar.addEventListener('click', () => modal.style.display = 'none');
    modal.addEventListener('click', e => {
      if (e.target === modal) modal.style.display = 'none';
    });
  </script>
</body>

</html> 