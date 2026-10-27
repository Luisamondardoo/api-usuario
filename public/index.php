<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware(); // Permite que getParsedBody() leia JSON [citation:14]
$app->addErrorMiddleware(true, true, true);

// ========== Dados iniciais: 5 usuários ==========
$usuarios = [
    ['id' => 1, 'nome' => 'Ana Silva', 'email' => 'ana@email.com', 'login' => 'ana', 'senha' => password_hash('123456', PASSWORD_DEFAULT)],
    ['id' => 2, 'nome' => 'Bruno Costa', 'email' => 'bruno@email.com', 'login' => 'bruno', 'senha' => password_hash('123456', PASSWORD_DEFAULT)],
    ['id' => 3, 'nome' => 'Carla Souza', 'email' => 'carla@email.com', 'login' => 'carla', 'senha' => password_hash('123456', PASSWORD_DEFAULT)],
    ['id' => 4, 'nome' => 'Diego Lima', 'email' => 'diego@email.com', 'login' => 'diego', 'senha' => password_hash('123456', PASSWORD_DEFAULT)],
    ['id' => 5, 'nome' => 'Elisa Rocha', 'email' => 'elisa@email.com', 'login' => 'elisa', 'senha' => password_hash('123456', PASSWORD_DEFAULT)],
];

// Função auxiliar: retornar JSON
function jsonResponse(Response $response, $data, int $status = 200): Response {
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}

// GET /status
$app->get('/status', function (Request $request, Response $response) {
    return jsonResponse($response, ['status' => 'ok']);
});

// GET /usuarios
$app->get('/usuarios', function (Request $request, Response $response) use ($usuarios) {
    // Não retornar o campo senha
    $lista = array_map(function ($u) {
        unset($u['senha']);
        return $u;
    }, $usuarios);
    return jsonResponse($response, array_values($lista));
});

// GET /usuarios/{id}
$app->get('/usuarios/{id}', function (Request $request, Response $response, array $args) use ($usuarios) {
    $id = (int)$args['id'];
    foreach ($usuarios as $u) {
        if ($u['id'] === $id) {
            unset($u['senha']);
            return jsonResponse($response, $u);
        }
    }
    return jsonResponse($response, ['error' => 'Usuário não encontrado'], 404);
});

// POST /usuarios (criar)
$app->post('/usuarios', function (Request $request, Response $response) use (&$usuarios) {
    $body = $request->getParsedBody();
    if (empty($body['nome']) || empty($body['email']) || empty($body['login']) || empty($body['senha'])) {
        return jsonResponse($response, ['error' => 'Campos obrigatórios: nome, email, login, senha'], 400);
    }
    $novoId = max(array_column($usuarios, 'id')) + 1;
    $novo = [
        'id' => $novoId,
        'nome' => $body['nome'],
        'email' => $body['email'],
        'login' => $body['login'],
        'senha' => password_hash($body['senha'], PASSWORD_DEFAULT),
    ];
    $usuarios[] = $novo;
    unset($novo['senha']);
    return jsonResponse($response, $novo, 201);
});

// PUT /usuarios/{id} (atualizar)
$app->put('/usuarios/{id}', function (Request $request, Response $response, array $args) use (&$usuarios) {
    $id = (int)$args['id'];
    $body = $request->getParsedBody();
    foreach ($usuarios as $i => $u) {
        if ($u['id'] === $id) {
            if (isset($body['nome'])) $usuarios[$i]['nome'] = $body['nome'];
            if (isset($body['email'])) $usuarios[$i]['email'] = $body['email'];
            if (isset($body['login'])) $usuarios[$i]['login'] = $body['login'];
            unset($usuarios[$i]['senha']);
            return jsonResponse($response, $usuarios[$i]);
        }
    }
    return jsonResponse($response, ['error' => 'Usuário não encontrado'], 404);
});

// DELETE /usuarios/{id}
$app->delete('/usuarios/{id}', function (Request $request, Response $response, array $args) use (&$usuarios) {
    $id = (int)$args['id'];
    foreach ($usuarios as $i => $u) {
        if ($u['id'] === $id) {
            array_splice($usuarios, $i, 1);
            return jsonResponse($response, ['message' => 'Usuário removido']);
        }
    }
    return jsonResponse($response, ['error' => 'Usuário não encontrado'], 404);
});

// POST /login (validar login e senha)
$app->post('/login', function (Request $request, Response $response) use ($usuarios) {
    $body = $request->getParsedBody();
    $login = $body['login'] ?? '';
    $senha = $body['senha'] ?? '';
    foreach ($usuarios as $u) {
        if ($u['login'] === $login && password_verify($senha, $u['senha'])) {
            return jsonResponse($response, ['message' => 'Login válido', 'usuario' => ['id' => $u['id'], 'nome' => $u['nome'], 'login' => $u['login']]]);
        }
    }
    return jsonResponse($response, ['error' => 'Credenciais inválidas'], 401);
});

// PUT /usuarios/{id}/senha (alterar senha)
$app->put('/usuarios/{id}/senha', function (Request $request, Response $response, array $args) use (&$usuarios) {
    $id = (int)$args['id'];
    $body = $request->getParsedBody();
    $senhaAtual = $body['senha_atual'] ?? '';
    $novaSenha = $body['nova_senha'] ?? '';

    foreach ($usuarios as $i => $u) {
        if ($u['id'] === $id) {
            if (!password_verify($senhaAtual, $u['senha'])) {
                return jsonResponse($response, ['error' => 'Senha atual incorreta'], 401);
            }
            if (strlen($novaSenha) < 6) {
                return jsonResponse($response, ['error' => 'Nova senha deve ter pelo menos 6 caracteres'], 400);
            }
            $usuarios[$i]['senha'] = password_hash($novaSenha, PASSWORD_DEFAULT);
            return jsonResponse($response, ['message' => 'Senha alterada com sucesso']);
        }
    }
    return jsonResponse($response, ['error' => 'Usuário não encontrado'], 404);
});

$app->run();