<?php
require_once 'mongosql.php';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Query Converter SQL-MongoDB</title>
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-case { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9; }
    .sql { background: #e6f7ff; padding: 10px; border-left: 4px solid #1890ff; margin: 10px 0; }
    .mongo { background: #f6ffed; padding: 10px; border-left: 4px solid #52c41a; margin: 10px 0; }
    .error { color: #f5222d; }
    h1, h2 { color: #333; }
    .success { color: #52c41a; }
</style>
</head><body>';
echo '<h1>Query Converter SQL-MongoDB</h1>';

echo "<span style='background: linear-gradient(90deg, rgba(255,255,0,0.2) 0%, rgba(255,255,0,0.7) 50%, rgba(255,255,0,0.2) 100%); padding: 4px 8px; border-radius: 6px; border-left: 3px solid #FFD700; font-family: \"Segoe UI\", Arial, sans-serif; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: inline-block;'>Site used for query structure testing: <a href='https://www.humongous.io/tools/playground/mongodb/new' target='_blank' rel='noopener noreferrer' style='color: #0066CC; font-weight: bold; text-decoration: none;'>HumongouS.io</a></span><br>";
function test($sql, $tipo) {
    echo '<div class="test-case">';
    echo '<h3>Test ' . htmlspecialchars($tipo) . '</h3>';
    echo '<div class="sql"><strong>SQL:</strong><br><pre>' . htmlspecialchars($sql) . '</pre></div>';
    
    try {
        $start = microtime(true);
        $resultado = MongoSQLConverter::convert($sql);
        $time = round((microtime(true) - $start) * 1000, 2);
        
        echo '<div class="mongo"><strong>MongoDB:</strong> <span class="success">(converted into '.$time.'ms)</span><br><pre>' . htmlspecialchars($resultado) . '</pre></div>';
    } catch (Exception $e) {
        echo '<p class="error"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    
    echo '</div>';
}

// Basic SELECT tests
test("SELECT * FROM usuarios", 'SELECT');
test("SELECT nome, email FROM clientes WHERE id = 123", 'SELECT');
test("SELECT produto, preco FROM itens WHERE preco > 100 ORDER BY preco DESC", 'SELECT');
test("SELECT * FROM clientes LIMIT 10", 'SELECT');
test("SELECT * FROM logs WHERE data BETWEEN '2023-01-01' AND '2023-12-31'", 'SELECT');

// SELECT tests with logical operators
test("SELECT * FROM produtos WHERE preco > 50 AND estoque < 10", 'SELECT');

// SELECT tests with functions and aliases
test("SELECT COUNT(*) as total FROM clientes", 'SELECT');
test("SELECT departamento, AVG(salario) as media_salarial FROM funcionarios GROUP BY departamento", 'SELECT');
test("SELECT nome, email as endereco_email FROM usuarios", 'SELECT');

// INSERT Tests
test("INSERT INTO produtos (id, nome, preco) VALUES (1, 'Camiseta', 29.90)", 'INSERT');
test("INSERT INTO clientes (id, nome, email) VALUES (100, 'Maria Silva', 'maria@exemplo.com')", 'INSERT');
test("INSERT INTO logs (mensagem, nivel, data) VALUES ('Erro ao conectar', 'error', NOW())", 'INSERT');

// UPDATE Tests
test("UPDATE produtos SET preco = 39.90 WHERE id = 1", 'UPDATE');
test("UPDATE clientes SET ativo = false WHERE ultimo_acesso < '2022-01-01'", 'UPDATE');
test("UPDATE estoque SET quantidade = quantidade - 1 WHERE produto_id = 5", 'UPDATE');

// DELETE Tests
test("DELETE FROM sessões WHERE expiracao < NOW()", 'DELETE');
test("DELETE FROM clientes WHERE id = 42", 'DELETE');

// Tests with intentional errors

// test("SELECT * FROM", 'SELECT (erro)');
// test("INSERT INTO tabela VALUES (1, 2, 3)", 'INSERT (erro)');
// test("UPDATE tabela SET coluna = valor", 'UPDATE (erro)');

echo '</body></html>';
?>