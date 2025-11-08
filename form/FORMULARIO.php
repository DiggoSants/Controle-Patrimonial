<?php
$servidor = "localhost";
$username = "root";
$usersenha = "";
$database = "controle_patrimonio";

header('Content-Type: application/json; charset=utf-8');

$conexao = new mysqli($servidor, $username, $usersenha, $database);
if ($conexao->connect_error) {
    http_response_code(500);
    echo json_encode(["ok" => false, "message" => "Erro na conexão: " . $conexao->connect_error]);
    exit;
}

$dados = json_decode(file_get_contents("php://input"), true);
$acao = $_GET['action'] ?? null;

// ========== CADASTRO ==========
if ($acao === 'register') {
    $nome  = $dados['nome']  ?? '';
    $email = $dados['email'] ?? '';
    $senha = $dados['senha'] ?? '';

    if (empty($nome) || empty($email) || empty($senha)) {
        http_response_code(400);
        echo json_encode(["ok" => false, "message" => "Preencha todos os campos."]);
        exit;
    }
    // verifica se já existe e-mail
    $check = $conexao->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        http_response_code(400);
        echo json_encode(["ok" => false, "message" => "E-mail já cadastrado."]);
        exit;
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare("INSERT INTO usuarios (nome, email, senha, status) VALUES (?, ?, ?, 'ativo')");
    $stmt->bind_param("sss", $nome, $email, $senhaHash);

    if ($stmt->execute()) {
        echo json_encode(["ok" => true, "message" => "Usuário registrado com sucesso!"]);
    } else {
        http_response_code(500);
        echo json_encode(["ok" => false, "message" => "Erro ao cadastrar: " . $conexao->error]);
    }
    exit;
}

// ========== LOGIN ==========
$email = $dados['login'] ?? '';
$senha = $dados['senha'] ?? '';

if (empty($email) || empty($senha)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "message" => "Informe e-mail e senha."]);
    exit;
}

$stmt = $conexao->prepare("SELECT senha FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    if (password_verify($senha, $row['senha'])) {
        echo json_encode(["ok" => true, "message" => "Login bem-sucedido!"]);
    } else {
        http_response_code(401);
        echo json_encode(["ok" => false, "message" => "Senha incorreta."]);
    }
} else {
    http_response_code(404);
    echo json_encode(["ok" => false, "message" => "E-mail não encontrado."]);
}

$conexao->close();
?>
