# Criação do Projeto
* Criado o projeto laravel; 
* Criado toda estrutura de players e clubs; 

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
