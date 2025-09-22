# Sistema de Gerenciamento de Tarefas

Uma API RESTful robusta para gerenciamento de tarefas construída com Laravel Lumen, oferecendo recursos avançados como exclusão lógica (soft delete), auditoria de atividades e filtragem inteligente.

## 🚀 Características Principais

- **API RESTful Completa**: Operações CRUD completas para tarefas
- **Soft Delete**: Exclusão recuperável de tarefas com possibilidade de restauração
- **Sistema de Auditoria**: Registros completos no MongoDB para rastreamento de atividades
- **Filtragem Avançada**: Sistema robusto de filtros, ordenação e paginação
- **Documentação OpenAPI**: Documentação interativa Swagger/OpenAPI 3.0
- **Tratamento de Erros**: Sistema abrangente de tratamento e validação de erros
- **Versionamento de API**: API versionada com suporte a múltiplas versões
- **Limitação de requisições**: Proteção contra abuso da API

## 🛠 Stack Tecnológica

- **Backend**: Laravel Lumen 11.x
- **PHP**: ^8.2
- **Banco de Dados Principal**: MySQL
- **Sistema de Logs**: MongoDB
- **Documentação**: Swagger/OpenAPI 3.0 (zircote/swagger-php)
- **Containerização**: Docker e Docker Compose

## 📋 Pré-requisitos

- **PHP** >= 8.2
- **Composer** >= 2.0
- **Docker** e **Docker Compose** (recomendado)
- **MySQL/MariaDB** >= 8.0
- **MongoDB** >= 4.0
- **Git**

## ⚡ Instalação Rápida

### Opção 1: Docker (Recomendado)

```bash
# 1. Clone o repositório
git clone https://github.com/Jardelsr/task-management-system.git
cd task-management-system

# 2. Configure o ambiente
cp .env.example .env

# 3. Inicie os serviços com Docker (a partir do diretório docker/)
cd docker/
docker-compose up -d

# 4. Execute as migrações
docker-compose exec app php artisan migrate

# 5. Acesse a API
curl http://localhost:8000/api/v1/
```

### Opção 2: Instalação Manual

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/task-management-system.git
cd task-management-system

# 2. Instale as dependências
composer install

# 3. Configure o ambiente
cp .env.example .env
# Edite o arquivo .env com suas configurações

# 4. Execute as migrações
php artisan migrate

# 5. Inicie o servidor
php -S localhost:8000 -t public
```

### Comandos Docker

```bash
# Executar a partir do diretório docker/
cd docker/
docker-compose up -d

# Ou executar da raiz do projeto
docker-compose -f docker/docker-compose.yml up -d

# Verificar status dos containers
docker-compose ps

# Ver logs
docker-compose logs app
```

## 🎯 Uso da API

### Endpoints Principais

#### 📋 Tarefas - Operações Básicas
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/v1/tasks` | Lista todas as tarefas |
| `POST` | `/api/v1/tasks` | Cria uma nova tarefa |
| `GET` | `/api/v1/tasks/{id}` | Obtém tarefa específica |
| `PUT` | `/api/v1/tasks/{id}` | Atualiza tarefa completa |
| `PATCH` | `/api/v1/tasks/{id}` | Atualização parcial |
| `DELETE` | `/api/v1/tasks/{id}` | Exclusão lógica (soft delete) |

#### 🔄 Tarefas - Operações Especiais
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/api/v1/tasks/{id}/restore` | Restaura tarefa excluída |
| `DELETE` | `/api/v1/tasks/{id}/force` | Exclusão permanente |
| `POST` | `/api/v1/tasks/{id}/complete` | Marca como concluída |
| `POST` | `/api/v1/tasks/{id}/start` | Marca como em progresso |
| `POST` | `/api/v1/tasks/{id}/cancel` | Marca como cancelada |
| `POST` | `/api/v1/tasks/{id}/assign` | Atribui a um usuário |
| `DELETE` | `/api/v1/tasks/{id}/assign` | Remove atribuição |
| `POST` | `/api/v1/tasks/{id}/duplicate` | Duplica tarefa |

#### 📊 Tarefas - Coleções e Estatísticas
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/v1/tasks/stats` | Estatísticas das tarefas |
| `GET` | `/api/v1/tasks/summary` | Resumo das tarefas |
| `GET` | `/api/v1/tasks/trashed` | Lista tarefas excluídas |
| `GET` | `/api/v1/tasks/overdue` | Lista tarefas vencidas |
| `GET` | `/api/v1/tasks/completed` | Lista tarefas concluídas |
| `GET` | `/api/v1/tasks/export` | Exporta tarefas |
| `POST` | `/api/v1/tasks/bulk` | Cria múltiplas tarefas |
| `PUT` | `/api/v1/tasks/bulk` | Atualiza múltiplas tarefas |
| `DELETE` | `/api/v1/tasks/bulk` | Exclui múltiplas tarefas |

#### 📝 Registros e Auditoria
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/v1/logs` | Lista logs do sistema |
| `GET` | `/api/v1/logs/{id}` | Obtém log específico |
| `GET` | `/api/v1/logs/stats` | Estatísticas dos logs |
| `GET` | `/api/v1/logs/recent` | Logs recentes |
| `GET` | `/api/v1/logs/export` | Exporta logs |
| `GET` | `/api/v1/logs/tasks/{id}` | Logs de uma tarefa |
| `GET` | `/api/v1/logs/actions/{action}` | Logs por ação |
| `GET` | `/api/v1/logs/users/{userId}` | Logs por usuário |
| `GET` | `/api/v1/logs/date-range` | Logs por período |
| `DELETE` | `/api/v1/logs/cleanup` | Limpeza de logs antigos |

#### 🔍 Sistema e Documentação
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/` | Visão geral da API |
| `GET` | `/api/v1/info` | Informações da API |
| `GET` | `/api/v1/health` | Status de saúde |
| `GET` | `/health` | Verificação geral de saúde do sistema |
| `GET` | `/health/database/{connection}` | Teste de conexão BD |
| `GET` | `/api/v1/docs` | Documentação interativa |
| `GET` | `/api/v1/openapi.json` | Especificação OpenAPI |

### Exemplos de Uso

#### Criar uma Nova Tarefa

```bash
curl -X POST http://localhost:8000/api/v1/tasks \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Implementar autenticação",
    "description": "Adicionar sistema de login e registro",
    "status": "pending",
    "due_date": "2025-12-31",
    "assigned_to": 1
  }'
```

**Resposta:**
```json
{
  "success": true,
  "message": "Task created successfully",
  "data": {
    "id": 1,
    "title": "Implementar autenticação",
    "description": "Adicionar sistema de login e registro",
    "status": "pending",
    "due_date": "2025-12-31T00:00:00.000000Z",
    "assigned_to": 1,
    "created_at": "2025-09-22T10:30:00.000000Z",
    "updated_at": "2025-09-22T10:30:00.000000Z"
  }
}
```

#### Listar Tarefas com Filtros

```bash
# Tarefas pendentes do usuário 1, ordenadas por prazo
curl "http://localhost:8000/api/v1/tasks?status=pending&assigned_to=1&sort_by=due_date&sort_order=asc"

# Tarefas vencidas com paginação
curl "http://localhost:8000/api/v1/tasks?overdue=true&page=1&limit=10"
```

#### Atualizar Status da Tarefa

```bash
curl -X PATCH http://localhost:8000/api/v1/tasks/1 \
  -H "Content-Type: application/json" \
  -d '{"status": "in_progress"}'
```

## 🔍 Sistema de Filtros

### Parâmetros de Filtragem para Tarefas

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| `status` | string | Status da tarefa | `?status=pending` |
| `assigned_to` | integer | ID do usuário responsável | `?assigned_to=1` |
| `created_by` | integer | ID do criador | `?created_by=2` |
| `overdue` | boolean | Apenas tarefas vencidas | `?overdue=true` |
| `with_due_date` | boolean | Apenas com prazo definido | `?with_due_date=true` |

### Parâmetros de Filtragem para Logs

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| `action` | string | Filtrar por tipo de ação | `?action=created` |
| `task_id` | integer | Filtrar por ID da tarefa | `?task_id=1` |
| `user_id` | integer | Filtrar por ID do usuário | `?user_id=1` |
| `level` | string | Filtrar por nível do log | `?level=info` |
| `source` | string | Filtrar por origem do log | `?source=api` |
| `start_date` | datetime | Data de início (formato: Y-m-d H:i:s) | `?start_date=2025-01-01 00:00:00` |
| `end_date` | datetime | Data de fim (formato: Y-m-d H:i:s) | `?end_date=2025-12-31 23:59:59` |

### Valores Válidos para Filtros

#### Status de Tarefas
- `pending` - Pendente
- `in_progress` - Em andamento  
- `completed` - Concluída
- `cancelled` - Cancelada

#### Ações registradas no Log
- `created` - Criação
- `updated` - Atualização
- `deleted` - Exclusão suave
- `restored` - Restauração
- `soft_delete` - Exclusão suave
- `force_delete` - Exclusão permanente
- `bulk_update` - Atualização em lote
- `status_change` - Mudança de status
- `assignment_change` - Mudança de atribuição
- `metadata_update` - Atualização de metadados

### Parâmetros de Ordenação

| Parâmetro | Valores | Padrão | Descrição |
|-----------|---------|--------|-----------|
| `sort_by` | **Tarefas:** `created_at`, `updated_at`, `due_date`, `title`, `status` | `created_at` | Campo para ordenação |
|           | **Logs:** `created_at`, `action`, `task_id`, `user_id` |  |  |
| `sort_order` | `asc`, `desc` | `desc` | Direção da ordenação |

### Parâmetros de Paginação

| Parâmetro | Tipo | Padrão | Limite | Descrição |
|-----------|------|--------|--------|-----------|
| `page` | integer | `1` | ≥ 1 | Página atual |
| `limit` | integer | `50` | 1-1000 | Itens por página |

### Exemplos de Consultas Avançadas

#### Filtros para Tarefas
```bash
# Tarefas pendentes com prazo vencido
curl "http://localhost:8000/api/v1/tasks?status=pending&overdue=true"

# Tarefas do usuário 1, ordenadas por prazo
curl "http://localhost:8000/api/v1/tasks?assigned_to=1&sort_by=due_date&sort_order=asc"

# Tarefas concluídas com paginação
curl "http://localhost:8000/api/v1/tasks?status=completed&page=2&limit=25"
```

#### Filtros para Logs
```bash
# Logs de criação das últimas 24 horas
curl "http://localhost:8000/api/v1/logs?action=created&start_date=2025-09-21 00:00:00&end_date=2025-09-22 00:00:00"

# Logs de uma tarefa específica
curl "http://localhost:8000/api/v1/logs?task_id=1&sort_by=created_at&sort_order=desc"

# Logs de erro por usuário
curl "http://localhost:8000/api/v1/logs?level=error&user_id=1&limit=10"
```

## 🗂 Status de Tarefas

- `pending` - Pendente
- `in_progress` - Em andamento
- `completed` - Concluída
- `cancelled` - Cancelada

## 📊 Monitoramento

### Health Check

```bash
# Verificar status da API
curl http://localhost:8000/health

# Testar conexões de banco
curl http://localhost:8000/health/database/mysql
curl http://localhost:8000/health/database/mongodb
```

## 🛡️ Tratamento de Erros

### Códigos de Status HTTP usados

- `200` - Sucesso
- `201` - Criado com sucesso
- `400` - Requisição inválida
- `404` - Recurso não encontrado
- `422` - Erro de validação
- `429` - Muitas requisições (Rate Limit)
- `500` - Erro interno do servidor

### Formato de Erro Padrão

```json
{
  "error": "Validation failed",
  "message": "Os dados fornecidos são inválidos",
  "errors": {
    "title": ["O campo título é obrigatório"],
    "status": ["Status deve ser: pending, in_progress, completed, cancelled"]
  },
  "code": "VALIDATION_FAILED"
}
```

## 📚 Documentação da API

### Swagger UI

Acesse a documentação interativa:

- **Local**: http://localhost:8000/api/v1/docs
- **Especificação OpenAPI**: http://localhost:8000/api/v1/openapi.json

### Endpoints de Documentação

```bash
# Informações da API
curl http://localhost:8000/api/v1/info

# Especificação OpenAPI completa
curl http://localhost:8000/api/v1/openapi.json
```

## 🧪 Testes

### Executar Testes

```bash
# Testes unitários
./vendor/bin/phpunit

# Testes com Docker
docker-compose exec app ./vendor/bin/phpunit

# Testes específicos
./vendor/bin/phpunit --filter TaskTest
```

### Validação da API

```bash
# Executar suite de testes de validação
php test_api_validation_formatting.php

# Testar tratamento de erros
php test_comprehensive_error_handling.php
```

## 🔧 Desenvolvimento

### Estrutura do Projeto

```
├── app/                          # Código da aplicação
│   ├── Console/                  # Comandos Artisan personalizados
│   ├── Exceptions/               # Exceções customizadas
│   │   ├── TaskNotFoundException.php
│   │   ├── TaskValidationException.php
│   │   └── DatabaseException.php
│   ├── Http/                     # Camada HTTP
│   │   ├── Controllers/          # Controllers da API
│   │   │   ├── TaskController.php
│   │   │   ├── LogController.php
│   │   │   └── ApiDocumentationController.php
│   │   ├── Middleware/           # Middlewares customizados
│   │   ├── Requests/             # Validação de requisições
│   │   │   ├── CreateTaskRequest.php
│   │   │   ├── UpdateTaskRequest.php
│   │   │   └── ValidationHelper.php
│   │   └── Responses/            # Formatadores de resposta
│   ├── Models/                   # Models Eloquent & MongoDB
│   │   ├── Task.php             # Model principal de tarefas
│   │   └── Log.php              # Model de logs (MongoDB)
│   ├── OpenApi/                  # Anotações OpenAPI/Swagger
│   ├── Providers/                # Service Providers
│   ├── Repositories/             # Repositories para abstração de dados
│   ├── Services/                 # Serviços de negócio
│   │   ├── ValidationMessageService.php
│   │   └── LoggingService.php
│   └── Traits/                   # Traits reutilizáveis
├── bootstrap/                    # Inicialização da aplicação
├── config/                       # Arquivos de configuração
│   ├── api.php                  # Configurações da API
│   ├── database.php             # Configurações de banco de dados
│   ├── mongo.php                # Configurações MongoDB
│   ├── logging.php              # Configurações de logging
│   └── validation_messages.php  # Mensagens de validação
├── database/                     # Database related files
│   └── migrations/              # Migrações do banco MySQL
├── docs/                         # Documentação adicional
│   ├── api-filtering-guide.md
│   ├── soft-delete-implementation.md
│   ├── enhanced-response-formatting.md
│   └── restful-routes-configuration.md
├── public/                       # Arquivos públicos
│   ├── index.php                # Entry point da aplicação
│   ├── swagger-ui/              # Interface Swagger UI
│   └── .htaccess               # Configurações Apache
├── resources/                    # Recursos da aplicação
│   ├── lang/                    # Arquivos de idioma
│   └── views/                   # Views (se houver)
├── routes/                       # Definições de rotas
│   └── web.php                  # Rotas da aplicação
├── storage/                      # Arquivos de storage
│   ├── cache/                   # Cache da aplicação
│   ├── framework/               # Framework files
│   ├── logs/                    # Log files
│   └── test_outputs/            # Outputs de testes
├── tests/                        # Testes automatizados
├── vendor/                       # Dependências Composer
├── .env                         # Variáveis de ambiente
├── .env.example                 # Exemplo de variáveis de ambiente
├── composer.json                # Dependências e autoload
├── composer.lock               # Lock das versões
├── artisan                     # CLI do Laravel
├── docker/                       # Configuração Docker
│   ├── docker-compose.yml       # Orquestração de containers  
│   ├── Dockerfile               # Imagem da aplicação
│   └── apache-vhost.conf        # Configuração Apache
└── README-PT.md                # Este arquivo
```

### Arquivos Principais

#### **Configuração**
- `composer.json` - Dependências do projeto
- `.env` - Variáveis de ambiente
- `artisan` - CLI do Laravel/Lumen
- `docker/docker-compose.yml` - Orquestração de containers
- `docker/Dockerfile` - Imagem da aplicação

#### **Controllers Principais**
- `TaskController.php` - CRUD completo de tarefas + operações especiais
- `LogController.php` - Gerenciamento de logs e auditoria  
- `ApiDocumentationController.php` - Documentação OpenAPI/Swagger
- `HealthController.php` - Endpoints de verificação de saúde do sistema

#### **Models**
- `Task.php` - Model principal com soft delete e validações
- `Log.php` - Model para MongoDB com logs de auditoria

#### **Validação e Requisições**
- `CreateTaskRequest.php` - Validação para criação de tarefas
- `UpdateTaskRequest.php` - Validação para atualização
- `LogValidationRequest.php` - Validação para logs
- `FormRequest.php` - Base para validação de requisições
- `ValidationHelper.php` - Helpers de validação

#### **Configurações Principais**
- `config/api.php` - Configurações da API
- `config/database.php` - Configurações de banco de dados
- `config/mongo.php` - Configurações MongoDB
- `config/logging.php` - Configurações de logging
- `config/validation_messages.php` - Mensagens de validação customizadas
- `config/errors.php` - Configurações de tratamento de erros
- `config/log_responses.php` - Configurações de resposta de logs

#### **Documentação**
- `/docs/` - Documentação técnica detalhada (25+ guias)
- OpenAPI specs (arquivos `openapi-*.json`)
- Swagger UI integrado em `/public/swagger-ui/`

#### **Testes**
- Múltiplos arquivos de teste para validação de API (40+ arquivos)
- Testes de integração com Docker
- Testes de segurança e validação
- Testes de injeção SQL e sanitização de dados

## 📄 Licença

Este projeto está licenciado sob a [Licença MIT](LICENSE).

## 🔄 Histórico de Versões

### v1.0.0
- API RESTful completa para gerenciamento de tarefas
- Sistema de soft delete e restauração
- Logging abrangente com MongoDB
- Documentação OpenAPI/Swagger
- Sistema avançado de filtros e paginação
- Tratamento robusto de erros
- Rate limiting e validação

---