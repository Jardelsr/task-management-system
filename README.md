# Sistema de Gerenciamento de Tarefas

Uma API moderna de gerenciamento de tarefas construída com Laravel Lumen, usando MySQL para armazenamento de tarefas e MongoDB para logs.

## 🚀 Funcionalidades

- API RESTful para gerenciamento de tarefas
- Banco de dados MySQL para armazenamento de tarefas
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

## 📚 Documentação

A documentação da API estará disponível em:
- http://localhost:8000/api/documentation (em breve)

## 📝 License

Este projeto está licenciado sob a Licença MIT.