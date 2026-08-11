# Rodando a aplicação

A forma mais simples de executar o projeto é utilizando Docker. O `docker-compose.yml` automatiza a configuração do ambiente, instalação das dependências, build dos assets, configuração do banco de dados e inicialização dos serviços.

### 1. Subir os containers

Execute:

```bash
docker-compose up
```

O processo de inicialização realiza automaticamente:

* Instalação das extensões PHP necessárias; 
* Configuração do Node.js 22; 
* Instalação do Composer; 
* Execução do `composer install`; 
* Execução do `npm install`; 
* Build dos assets através do `npm run build`; 
* Criação do banco SQLite em `database/database.sqlite`; 
* Execução das migrations e seeders através de `php artisan migrate:refresh --seed`; 
* Inicialização do servidor Laravel na porta `8000`; 
* Inicialização do worker da fila através de `queue:listen`; 
* Inicialização do Vite Dev Server na porta `5173`.

### 2. Acessar a aplicação

Após a inicialização dos containers, a aplicação estará disponível em:

```text
http://localhost:8000
```

