# Criação do Projeto
* Criado o projeto laravel; 
* Criado toda estrutura de players e clubs; 
* Criado arquivo IA-prompts.ai onde ficará todos os prompts utilizados e os outputs da IA; 

* `php artisan make:model Player -a`
* `php artisan make:model Club -a`
* `php artisan make:controller DashBoardController`
* Para tratar e lidar com os dados de importação e exportação irei utilizar o pacote Laravel Excel https://laravel-excel.com/
* Criado controller de importação e exportação e já deixei criada a rota.
* Ajustando os Models.
# Backend do Fluxo de Importação

Foi desenvolvido o backend responsável pelo fluxo de importação de clubes e jogadores, priorizando **escalabilidade, baixo consumo de memória e tolerância a registros inválidos**.

## `ImportController.php`

Responsável por:

* Receber o upload do arquivo; 
* Armazenar o arquivo em `storage/imports`; 
* Disparar o job `ImportClubsFileJob`.

A utilização de **Jobs** permite que o processamento seja executado de forma assíncrona, evitando que uma importação de grande volume bloqueie a requisição HTTP e proporcionando maior escalabilidade.

## `ImportClubsFileJob`

Responsável por abrir e processar o arquivo utilizando `Storage::readStream()` .

A leitura em **stream** foi utilizada para evitar o carregamento de todo o arquivo na memória, permitindo trabalhar com arquivos grandes de forma mais eficiente e escalável.

Durante o processamento:

* Linhas em branco são ignoradas; 
* Cada linha é convertida de JSON para uma estrutura de dados; 
* Em caso de JSON inválido ou quando o conteúdo não corresponde a um objeto/array esperado:

  + `$skippedLines` é incrementado; 
  + Um `warning` é registrado no log; 
  + A linha é ignorada e o processamento continua normalmente; 
* Os clubes válidos são acumulados em `$clubBuffer`; 
* A quantidade de jogadores (`players`) presentes no buffer também é contabilizada para controlar o tamanho dos lotes.

Essa abordagem evita que um registro inválido interrompa todo o processo de importação.

## `ImportClubsChunkJob`

É um **job filho** responsável pelo processamento de cada lote de clubes gerado pelo `ImportClubsFileJob` .

O processamento realiza as seguintes etapas:

1. Sanitização dos dados utilizando o trait `SanitizesImportData`;
2. Persistência dos clubes em massa na tabela `clubs` através de `upsert`; 
3. Utilização de `club_id` como chave para identificar os registros; 
4. Caso o `upsert` em lote falhe, é utilizado um **fallback linha a linha**, evitando que um registro problemático invalide todo o lote; 
5. Para os clubes que possuem jogadores, os `players` são redistribuídos em novos chunks de **500 registros**; 
6. Cada chunk de jogadores é então enviado para processamento através do `ImportPlayersChunkJob`.

## Estratégia de Processamento

O fluxo foi estruturado para dividir a importação em etapas menores:

```text
Upload
  │
  ▼
ImportController
  │
  ▼
ImportClubsFileJob
  │
  ├── Leitura do arquivo via Stream
  │
  ├── Validação do JSON
  │
  ├── Ignora registros inválidos
  │
  └── Divide os clubes em chunks
          │
          ▼
   ImportClubsChunkJob
          │
          ├── Sanitização
          ├── Upsert em massa
          ├── Fallback linha a linha
          │
          └── Players
                 │
                 ▼
        Chunks de 500 jogadores
                 │
                 ▼
       ImportPlayersChunkJob
```

Essa arquitetura permite processar arquivos de grande volume sem carregar todo o conteúdo na memória, além de distribuir o processamento entre diferentes jobs.
Como resultado, o fluxo possui maior **escalabilidade**, **resiliência a registros inválidos** e melhor controle sobre o consumo de recursos durante a importação.

Até o momento, não precisei utilizar IA, pois este é um fluxo com o qual já tenho bastante familiaridade no desenvolvimento de back-end. Optei por utilizar PHP e Vue.js por serem tecnologias com as quais tenho trabalhado com maior frequência atualmente e nas quais possuo mais experiência e domínio.

## Fluxo do `ExportController`

O `ExportController` é responsável pela exportação dos dados de clubes e jogadores em formato CSV.

### Entrada

As exportações são realizadas através das seguintes rotas:

```http
GET /export?file=clubs
GET /export?file=players
```

A rota é protegida pelos middlewares `auth` e `verified` , garantindo que apenas usuários autenticados e com e-mail verificado possam realizar as exportações.

O parâmetro `file` determina qual tipo de arquivo será gerado:

* `clubs` → exportação de clubes; 
* `players` → exportação de jogadores.

---

### `exportClubs()` — Geração do `clubs.csv`

O método `exportClubs()` é responsável pela geração do arquivo `clubs.csv` .

O fluxo realiza as seguintes etapas:

1. Monta a query utilizando `Club::query()`;
2. Aplica o filtro de campeonato, permitindo apenas clubes da **Série A** e **Série B**;
3. Percorre os registros utilizando `lazyById()`, evitando carregar todos os clubes em memória;
4. Escreve cada registro diretamente no CSV utilizando `fputcsv()`;
5. Retorna o arquivo através de uma `StreamedResponse`.

A utilização de `lazyById()` permite realizar a exportação de grandes volumes de dados com baixo consumo de memória.

---

### `exportPlayers()` — Geração do `players.csv`

O método `exportPlayers()` é responsável pela geração do arquivo `players.csv` .

A consulta utiliza um `join` com a tabela `clubs` :

```php
Player::query()
    ->join('clubs', 'clubs.id', '=', 'players.club_id')
```

Essa abordagem garante duas regras de negócio simultaneamente:

* **Somente jogadores pertencentes a clubes da Série A ou Série B são exportados**, através do filtro aplicado em `clubs.championship`; 
* **Clubes sem jogadores não geram nenhuma linha no arquivo**, pois o `join` utilizado é um `INNER JOIN`.

O processamento segue a mesma estratégia utilizada na exportação de clubes:

1. Monta a query com o `join` entre `players` e `clubs`; 
2. Aplica o filtro de campeonato;
3. Percorre os registros utilizando `lazyById()`;
4. Escreve cada registro diretamente no CSV utilizando `fputcsv()`;
5. Retorna o resultado através de uma `StreamedResponse`.

---

## Estratégia de Streaming

As duas exportações utilizam `lazyById()` em conjunto com `StreamedResponse` e `fputcsv()` .

Essa estratégia evita que todos os registros sejam carregados simultaneamente na memória.

```text
Requisição HTTP
       │
       ▼
  ExportController
       │
       ▼
 index() valida ?file=
       │
       ├── "clubs"
       │      │
       │      ▼
       │   exportClubs()
       │      │
       │      ├── Club::query()
       │      ├── Filtro Série A/B
       │      ├── lazyById()
       │      ├── fputcsv()
       │      └── StreamedResponse
       │
       └── "players"
              │
              ▼
          exportPlayers()
              │
              ├── Player::query()
              ├── JOIN clubs
              ├── Filtro Série A/B
              ├── lazyById()
              ├── fputcsv()
              └── StreamedResponse
```

### Benefícios da abordagem

* **Baixo consumo de memória:** os registros são processados progressivamente; 
* **Suporte a grandes volumes:** evita carregar todo o resultado da consulta em memória; 
* **Resposta em streaming:** o arquivo pode ser enviado ao cliente conforme os dados são processados; 
* **Aplicação das regras de negócio na query:** os filtros são executados diretamente no banco de dados; 
* **Maior eficiência:** evita processamento desnecessário de registros que não serão exportados.
# Fornt-end
* Utilizando Vue.js e TypeScript, crie um front simples só para exibir tres cards: Total de clubes; Total de jogadores; Clubes por série.
* Criado Listagem Clubes e jogadores.
* Criado Modal para importar e exportar os dados.
# Docker
* Utilizei a IA para gerar o script para subir o projeto utilizando docker
# Rodando a aplicação

## Opção 1 — Docker (recomendado)

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

### Serviços executados

```text
Docker Compose
│
├── Laravel
│   └── http://localhost:8000
│
├── Queue Worker
│   └── queue:listen
│
└── Vite
    └── http://localhost:5173
```

Com isso, deixei todo o ambiente necessário para executar e testar a aplicação sendo  configurado automaticamente pelo Docker.

## Opção 2 — Ambiente Local (sem Docker)

Também é possível executar o projeto diretamente no ambiente local, sem utilizar Docker.

### Requisitos

* **PHP 8.4+**
* **Composer**
* **Node.js 22+**
* **npm**

### 1. Instalar as dependências

Na raiz do projeto, execute:

```bash
composer install
npm install
```

### 2. Configurar o ambiente

Caso o arquivo `.env` ainda não exista, crie uma cópia do `.env.example` :

```bash
cp .env.example .env
```

Em seguida, gere a chave de aplicação do Laravel:

```bash
php artisan key:generate
```

### 3. Criar e configurar o banco SQLite

Crie o arquivo do banco de dados:

```bash
touch database/database.sqlite
```

Depois, execute as migrations e os seeders:

```bash
php artisan migrate --seed
```

#### Execução

**Servidor Laravel:**

```bash
php artisan serve
```

```bash
npm run dev
```

### 5. Acessar a aplicação

Após iniciar os serviços, a aplicação estará disponível em:

```text
http://localhost:8000
```

### Estrutura dos serviços

```text
Ambiente Local
│
├── Laravel
│   └── http://localhost:8000
│
├── Queue Worker
│   └── queue:listen --tries=1
│
└── Vite
    └── http://localhost:5173
```

Essa opção permite executar o projeto diretamente no sistema operacional, utilizando as versões locais do PHP, Node.js e demais dependências.
