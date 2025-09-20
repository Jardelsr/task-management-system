# Sistema de Gerenciamento de Tarefas

Uma API moderna de gerenciamento de tarefas construída com Laravel Lumen, usando MySQL para armazenamento de tarefas e MongoDB para logs.

## 🚀 Funcionalidades

- API RESTful para gerenciamento de tarefas
- Banco de dados MySQL para arma- Usa padrão repository para manter o controller limpo e testável

## 🛡️ Sistema de Tratamento de Erros

### Visão Geral

O Sistema de Gerenciamento de Tarefas implementa uma estrutura abrangente de tratamento de erros que fornece respostas de erro consistentes e informativas em toda a API. Este sistema inclui exceções customizadas, respostas de erro centralizadas e logging adequado.

### Classes de Exceções Customizadas

#### TaskNotFoundException
- **Propósito**: Lançada quando uma tarefa solicitada não pode ser encontrada
- **Status HTTP**: 404
- **Uso**: `throw new TaskNotFoundException($taskId);`
- **Formato de Resposta**:
```json
{
  "error": "Task not found",
  "message": "Task with ID 123 not found",
  "task_id": 123,
  "code": "TASK_NOT_FOUND"
}
```

#### TaskValidationException
- **Propósito**: Lançada quando a validação de dados da tarefa falha
- **Status HTTP**: 422
- **Uso**: `throw new TaskValidationException($errors, $field);`
- **Formato de Resposta**:
```json
{
  "error": "Validation failed",
  "message": "Task validation failed",
  "errors": {"title": ["O campo título é obrigatório"]},
  "field": "title",
  "code": "VALIDATION_FAILED"
}
```

#### DatabaseException
- **Propósito**: Lançada quando operações de banco de dados falham
- **Status HTTP**: 500
- **Uso**: `throw new DatabaseException($message, $operation, $context);`
- **Formato de Resposta**:
```json
{
  "error": "Database operation failed",
  "message": "Falha ao conectar com o banco de dados",
  "operation": "select",
  "code": "DATABASE_ERROR"
}
```

#### TaskOperationException
- **Propósito**: Lançada quando operações de tarefa falham
- **Status HTTP**: 500
- **Uso**: `throw new TaskOperationException($message, $operation, $taskId);`
- **Formato de Resposta**:
```json
{
  "error": "Task operation failed",
  "message": "Falha ao atualizar status da tarefa",
  "operation": "update",
  "task_id": 123,
  "code": "TASK_OPERATION_FAILED"
}
```

#### LoggingException
- **Propósito**: Lançada quando operações de logging falham
- **Status HTTP**: 500
- **Uso**: `throw new LoggingException($message, $operation, $context);`

### Helper de Respostas de Erro (ErrorResponseTrait)

O `ErrorResponseTrait` fornece métodos consistentes para gerar respostas de erro:

#### Métodos Disponíveis

- `errorResponse($error, $message, $statusCode, $details, $code)` - Resposta de erro genérica
- `validationErrorResponse($errors, $message)` - Resposta de erro de validação
- `notFoundResponse($resource, $id)` - Resposta de recurso não encontrado
- `databaseErrorResponse($operation, $message)` - Resposta de erro de banco de dados
- `unauthorizedResponse($message)` - Resposta 401 Não Autorizado
- `forbiddenResponse($message)` - Resposta 403 Proibido
- `serverErrorResponse($message, $details)` - Resposta 500 Erro do Servidor
- `successResponse($data, $message, $statusCode)` - Resposta de sucesso

#### Exemplo de Uso

```php
// Em um controller
public function someMethod()
{
    try {
        // Alguma operação
    } catch (TaskNotFoundException $e) {
        throw $e; // Deixa o manipulador de exceções lidar com isso
    } catch (\Exception $e) {
        return $this->serverErrorResponse('Operação falhou');
    }
}
```

### Manipulador de Exceções (app/Exceptions/Handler.php)

O manipulador de exceções aprimorado fornece:

1. **Mapeamento Automático de Exceções**: Mapeia exceções customizadas para respostas HTTP apropriadas
2. **Formato de Resposta Consistente**: Todos os erros seguem a mesma estrutura JSON
3. **Integração de Logging**: Registra automaticamente exceções com contexto
4. **Suporte ao Modo Debug**: Mostra informações detalhadas de erro quando `APP_DEBUG=true`
5. **Segurança**: Oculta informações sensíveis no modo produção

#### Formato de Resposta

Todas as respostas de erro seguem esta estrutura:

```json
{
  "success": false,
  "error": "Tipo de erro",
  "message": "Mensagem de erro legível",
  "timestamp": "2025-09-20T10:30:00.000000Z",
  "details": {
    // Detalhes adicionais específicos do erro
  },
  "code": "CODIGO_ERRO",
  "debug": {
    // Informações de debug (apenas no modo debug)
    "file": "/path/to/file.php",
    "line": 42
  }
}
```

### Configuração

O tratamento de erros é configurado em `config/errors.php`:

- **Mensagens Padrão**: Mensagens de erro predefinidas
- **Códigos de Erro**: Códigos de erro padronizados
- **Logging**: Configuração de comportamento de logging
- **Debug**: Configurações do modo debug
- **Rate Limiting**: Limitação de taxa de resposta de erro

### Melhores Práticas

#### 1. Use Exceções Específicas
```php
// Bom
throw new TaskNotFoundException($id);

// Evite
throw new \Exception('Task not found');
```

#### 2. Forneça Contexto
```php
// Bom
throw new DatabaseException(
    'Falha ao atualizar tarefa',
    'update',
    ['task_id' => $id, 'fields' => $data]
);

// Menos útil
throw new DatabaseException('Falha na atualização');
```

#### 3. Deixe o Manipulador de Exceções Lidar com as Respostas
```php
// Bom
public function show($id)
{
    $task = $this->repository->findById($id);
    
    if (!$task) {
        throw new TaskNotFoundException($id);
    }
    
    return $this->successResponse($task);
}

// Evite criação manual de resposta
public function show($id)
{
    try {
        $task = $this->repository->findById($id);
        
        if (!$task) {
            return response()->json(['error' => 'Não encontrado'], 404);
        }
        
        return response()->json($task);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Falhou'], 500);
    }
}
```

#### 4. Use Métodos Helper de Resposta
```php
// Bom
return $this->successResponse($data, 'Tarefa criada com sucesso', 201);

// Menos consistente
return response()->json(['success' => true, 'data' => $data], 201);
```

### Testando o Tratamento de Erros

#### Exemplos de Casos de Teste

```php
/** @test */
public function it_throws_task_not_found_exception_when_task_does_not_exist()
{
    $this->expectException(TaskNotFoundException::class);
    $this->controller->show(999);
}

/** @test */
public function it_returns_proper_error_response_for_not_found_task()
{
    $response = $this->get('/tasks/999');
    
    $response->assertStatus(404)
             ->assertJson([
                 'success' => false,
                 'error' => 'Task not found',
                 'code' => 'TASK_NOT_FOUND'
             ]);
}
```

### Monitoramento e Logging

O sistema de tratamento de erros registra automaticamente:
- Exceções de banco de dados com contexto da operação
- Falhas de operação de tarefa com IDs de tarefa
- Erros de validação (quando configurado)
- Todas as exceções não tratadas

Os logs são estruturados para monitoramento fácil:
```
[2025-09-20 10:30:00] local.ERROR: Database operation failed
{
  "operation": "update",
  "context": {"task_id": 123},
  "message": "Connection timeout",
  "file": "/app/TaskController.php",
  "line": 45
}
```

Este sistema abrangente de tratamento de erros garante respostas de erro consistentes, informativas e seguras em toda a API do Sistema de Gerenciamento de Tarefas.

## 📚 Documentaçãoamento de tarefas
- MongoDB para logging do sistema
- Desenvolvimento em containers Docker
- Documentação completa da API

## 🛠 Stack

- PHP 8.2
- Laravel Lumen 11.0
- MySQL 8.0
- MongoDB 7.0
- Docker & Docker Compose

## 📋 Pré-requisitos

- Docker Desktop instalado
- Git
- Portas livres: 8000 (API), 3306 (MySQL), 27017 (MongoDB)

## ⚙️ Instalação

1. Clone o repositório:
```bash
git clone https://github.com/Jardelsr/task-management-system.git
cd task-management-system
```

2. Copie o arquivo de ambiente:
```bash
copy .env.example .env
```

3. Construa e inicie os containers Docker:
```bash
cd docker
docker-compose up -d --build
```

4. Instale as dependências PHP:
```bash
docker-compose exec app composer install
```

5. Ajuste as permissões:
```bash
docker-compose exec app chmod -R 775 storage
docker-compose exec app chmod -R 775 bootstrap/cache
```

## 🔧 Configuração do Ambiente

O arquivo `.env` contém todas as configurações necessárias:

```env
# Application
APP_NAME=Task Management System
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# MySQL Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=task_management
DB_USERNAME=root
DB_PASSWORD=secret

# MongoDB
MONGO_HOST=mongodb
MONGO_PORT=27017
MONGO_DATABASE=task_logs
```

## 🚦 Comandos Docker

```bash
# Iniciar containers
docker-compose up -d

# Parar containers
docker-compose down

# Visualizar logs
docker-compose logs -f

# Acessar o shell do container
docker-compose exec app bash

# Reconstruir containers
docker-compose up -d --build
```

## 🔍 Verificando a Instalação

1. Verifique se os containers estão em execução:
```bash
docker-compose ps
```

2. Teste o endpoint da API:
```bash
curl http://localhost:8000
```

3. Verifique as conexões com os bancos de dados:
```bash
# MySQL
docker-compose exec mysql mysql -u root -psecret -e "SELECT 1"

# MongoDB
docker-compose exec mongodb mongosh --eval "db.runCommand({ ping: 1 })"
```

## 🐛 Solução de Problemas

Problemas comuns e soluções:

1. **Conflito de portas:**:
   - Verifique se as portas 8000, 3306 ou 27017 estão em uso
   - Modifique os mapeamentos de portas no docker-compose.yml se necessário

2. **Problemas de permissão:**:
   - Execute os comandos `chmod` como mostrado nos passos de instalação
   - Certifique-se de que os volumes montados tenham a propriedade correta

3. **Falhas ao iniciar containers:**:
   - Verifique os logs com `docker-compose logs`
   - Confirme as variáveis de ambiente no arquivo `.env`

## � Guia de Filtros da API

### Endpoint: GET /tasks

O método de filtros aprimorados de index oferece recursos abrangentes de filtragem, ordenação e paginação para listagem de tarefas.

### Parâmetros de Query Disponíveis

#### **Parâmetros de Filtragem**

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| `status` | string | Filtrar por status da tarefa | `?status=pending` |
| `assigned_to` | integer | Filtrar por ID do usuário responsável | `?assigned_to=123` |
| `created_by` | integer | Filtrar por ID do criador | `?created_by=456` |
| `overdue` | boolean | Filtrar apenas tarefas vencidas | `?overdue=true` |
| `with_due_date` | boolean | Filtrar tarefas com prazo definido | `?with_due_date=true` |

#### **Parâmetros de Ordenação**

| Parâmetro | Tipo | Padrão | Descrição | Valores Válidos |
|-----------|------|--------|-----------|-----------------|
| `sort_by` | string | `created_at` | Campo para ordenação | `created_at`, `updated_at`, `due_date`, `title`, `status` |
| `sort_order` | string | `desc` | Direção da ordenação | `asc`, `desc` |

#### **Parâmetros de Paginação**

| Parâmetro | Tipo | Padrão | Descrição | Restrições |
|-----------|------|--------|-----------|------------|
| `limit` | integer | `50` | Resultados por página | Mín: 1, Máx: 1000 |
| `page` | integer | `1` | Número da página | Mín: 1 |

### Valores de Status Válidos

- `pending` - Pendente
- `in_progress` - Em andamento  
- `completed` - Concluída
- `cancelled` - Cancelada

### Exemplos de Requisições

#### Filtragem Básica por Status
```bash
GET /tasks?status=pending
```

#### Filtragem Avançada com Ordenação
```bash
GET /tasks?status=in_progress&assigned_to=123&sort_by=due_date&sort_order=asc
```

#### Paginação com Filtros
```bash
GET /tasks?status=pending&page=2&limit=25
```

#### Obter Tarefas Vencidas
```bash
GET /tasks?overdue=true&sort_by=due_date&sort_order=asc
```

#### Filtragem Complexa
```bash
GET /tasks?created_by=456&with_due_date=true&sort_by=created_at&limit=100
```

### Formato de Resposta

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Título da Tarefa",
            "description": "Descrição da Tarefa",
            "status": "pending",
            "created_by": 123,
            "assigned_to": 456,
            "due_date": "2025-09-25T15:30:00.000000Z",
            "completed_at": null,
            "created_at": "2025-09-20T10:00:00.000000Z",
            "updated_at": "2025-09-20T10:00:00.000000Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 50,
        "total": 150,
        "total_pages": 3,
        "has_next_page": true,
        "has_prev_page": false
    },
    "filters": {
        "status": "pending",
        "assigned_to": null,
        "created_by": null,
        "overdue": null,
        "with_due_date": null,
        "sort_by": "created_at",
        "sort_order": "desc"
    }
}
```

### Respostas de Erro

#### Status Inválido
```json
{
    "error": "Parâmetro status inválido",
    "valid_statuses": ["pending", "in_progress", "completed", "cancelled"]
}
```

#### Campo de Ordenação Inválido
```json
{
    "error": "Parâmetro sort_by inválido",
    "valid_sort_fields": ["created_at", "updated_at", "due_date", "title", "status"]
}
```

#### Ordem de Classificação Inválida
```json
{
    "error": "Parâmetro sort_order inválido",
    "valid_sort_orders": ["asc", "desc"]
}
```

### Considerações de Performance

- Toda a filtragem é realizada no nível do banco de dados para performance otimizada
- Limites de paginação são aplicados (máximo de 1000 por requisição)
- Índices de banco de dados estão em vigor para campos comumente filtrados (status, assigned_to, created_by, due_date)
- Usa padrão repository para manter o controller limpo e testável

## �📚 Documentação

A documentação da API estará disponível em:
- http://localhost:8000/api/documentation (em breve)

## 📝 License

Este projeto está licenciado sob a Licença MIT.