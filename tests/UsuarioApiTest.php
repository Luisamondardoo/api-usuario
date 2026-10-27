
<?php

// tests/UsuarioApiTest.php

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class UsuarioApiTest extends TestCase
{
    private static $app;

    public static function setUpBeforeClass(): void
    {
        self::$app = require __DIR__ . '/../src/app.php';
    }

    // Função para fazer as requisições
    private function fazerRequisicao(
        string $metodo,
        string $rota,
        array $dados = []
    ): array {
        $request = (new ServerRequestFactory())
            ->createServerRequest($metodo, $rota)
            ->withHeader('Content-Type', 'application/json');

        if (!empty($dados)) {
            $request->getBody()->write(json_encode($dados));
            $request->getBody()->rewind();
        }

        $response = self::$app->handle($request);
        $corpo = (string) $response->getBody();

        return [
            'status' => $response->getStatusCode(),
            'corpo' => $corpo === ''
                ? []
                : json_decode($corpo, true)
        ];
    }

    // TESTE 1: Verificar status da API
    public function testGetStatus(): void
    {
        $resultado = $this->fazerRequisicao('GET', '/status');

        $this->assertEquals(200, $resultado['status']);
        $this->assertEquals('ok', $resultado['corpo']['status']);
    }

    // TESTE 2: Listar usuários
    public function testGetUsuarios(): void
    {
        $resultado = $this->fazerRequisicao('GET', '/usuarios');

        $this->assertEquals(200, $resultado['status']);
        $this->assertCount(5, $resultado['corpo']);
        $this->assertArrayNotHasKey('senha', $resultado['corpo'][0]);
    }

    // TESTE 3: Buscar usuário por ID
    public function testGetUsuarioPorId(): void
    {
        $resultado = $this->fazerRequisicao('GET', '/usuarios/1');

        $this->assertEquals(200, $resultado['status']);
        $this->assertEquals('Ana Silva', $resultado['corpo']['nome']);
        $this->assertEquals('ana@email.com', $resultado['corpo']['email']);
        $this->assertArrayNotHasKey('senha', $resultado['corpo']);
    }

    // TESTE 4: Buscar usuário inexistente
    public function testGetUsuarioInexistente(): void
    {
        $resultado = $this->fazerRequisicao('GET', '/usuarios/999');

        $this->assertEquals(404, $resultado['status']);
        $this->assertEquals(
            'Usuário não encontrado',
            $resultado['corpo']['error']
        );
    }

    // TESTE 5: Criar usuário
    public function testPostCriaUsuario(): void
    {
        $resultado = $this->fazerRequisicao('POST', '/usuarios', [
            'nome' => 'Maria Silva',
            'email' => 'maria@email.com',
            'login' => 'maria',
            'senha' => '123456'
        ]);

        $this->assertEquals(201, $resultado['status']);
        $this->assertEquals('Maria Silva', $resultado['corpo']['nome']);
        $this->assertEquals('maria@email.com', $resultado['corpo']['email']);
        $this->assertEquals('maria', $resultado['corpo']['login']);
        $this->assertArrayNotHasKey('senha', $resultado['corpo']);
    }

    // TESTE 6: Criar usuário sem campos obrigatórios
    public function testPostUsuarioSemCampos(): void
    {
        $resultado = $this->fazerRequisicao('POST', '/usuarios', [
            'nome' => 'Maria Silva'
        ]);

        $this->assertEquals(400, $resultado['status']);
        $this->assertEquals(
            'Campos obrigatórios',
            $resultado['corpo']['error']
        );
    }

    // TESTE 7: Atualizar usuário
    public function testPutAtualizaUsuario(): void
    {
        $resultado = $this->fazerRequisicao('PUT', '/usuarios/2', [
            'nome' => 'Bruno Atualizado',
            'email' => 'bruno.novo@email.com'
        ]);

        $this->assertEquals(200, $resultado['status']);
        $this->assertEquals(
            'Bruno Atualizado',
            $resultado['corpo']['nome']
        );
        $this->assertEquals(
            'bruno.novo@email.com',
            $resultado['corpo']['email']
        );
    }

    // TESTE 8: Atualizar usuário inexistente
    public function testPutUsuarioInexistente(): void
    {
        $resultado = $this->fazerRequisicao('PUT', '/usuarios/999', [
            'nome' => 'Teste'
        ]);

        $this->assertEquals(404, $resultado['status']);
        $this->assertEquals(
            'Usuário não encontrado',
            $resultado['corpo']['error']
        );
    }

    // TESTE 9: Excluir usuário
    public function testDeleteUsuario(): void
    {
        $resultado = $this->fazerRequisicao('DELETE', '/usuarios/4');

        $this->assertEquals(204, $resultado['status']);
    }

    // TESTE 10: Excluir usuário inexistente
    public function testDeleteUsuarioInexistente(): void
    {
        $resultado = $this->fazerRequisicao('DELETE', '/usuarios/999');

        $this->assertEquals(404, $resultado['status']);
        $this->assertEquals(
            'Usuário não encontrado',
            $resultado['corpo']['error']
        );
    }

    // TESTE 11: Login válido
    public function testPostLoginValido(): void
    {
        $resultado = $this->fazerRequisicao('POST', '/login', [
            'login' => 'ana',
            'senha' => '123456'
        ]);

        $this->assertEquals(200, $resultado['status']);
        $this->assertEquals(
            'Login válido',
            $resultado['corpo']['message']
        );
        $this->assertEquals(
            'Ana Silva',
            $resultado['corpo']['usuario']['nome']
        );
    }

    // TESTE 12: Login inválido
    public function testPostLoginInvalido(): void
    {
        $resultado = $this->fazerRequisicao('POST', '/login', [
            'login' => 'ana',
            'senha' => 'senhaerrada'
        ]);

        $this->assertEquals(401, $resultado['status']);
        $this->assertEquals(
            'Credenciais inválidas',
            $resultado['corpo']['error']
        );
    }

    // TESTE 13: Senha atual incorreta
    public function testPutSenhaAtualIncorreta(): void
    {
        $resultado = $this->fazerRequisicao(
            'PUT',
            '/usuarios/1/senha',
            [
                'senha_atual' => 'senhaerrada',
                'nova_senha' => '654321'
            ]
        );

        $this->assertEquals(401, $resultado['status']);
        $this->assertEquals(
            'Senha atual incorreta',
            $resultado['corpo']['error']
        );
    }

    // TESTE 14: Alterar senha com sucesso
    public function testPutAlteraSenha(): void
    {
        $resultado = $this->fazerRequisicao(
            'PUT',
            '/usuarios/3/senha',
            [
                'senha_atual' => '123456',
                'nova_senha' => '654321'
            ]
        );

        $this->assertEquals(200, $resultado['status']);
        $this->assertEquals(
            'Senha alterada',
            $resultado['corpo']['message']
        );
    }

    // TESTE 15: Nova senha muito curta
    public function testPutSenhaMuitoCurta(): void
    {
        $resultado = $this->fazerRequisicao(
            'PUT',
            '/usuarios/5/senha',
            [
                'senha_atual' => '123456',
                'nova_senha' => '123'
            ]
        );

        $this->assertEquals(400, $resultado['status']);
        $this->assertEquals(
            'Nova senha muito curta',
            $resultado['corpo']['error']
        );
    }

    // TESTE 16: Alterar senha de usuário inexistente
    public function testPutSenhaUsuarioInexistente(): void
    {
        $resultado = $this->fazerRequisicao(
            'PUT',
            '/usuarios/999/senha',
            [
                'senha_atual' => '123456',
                'nova_senha' => '654321'
            ]
        );

        $this->assertEquals(404, $resultado['status']);
        $this->assertEquals(
            'Usuário não encontrado',
            $resultado['corpo']['error']
        );
    }
}

