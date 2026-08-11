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

# Resultados 

## Dashboard 
* <img width="1904" height="936" alt="image" src="https://github.com/user-attachments/assets/7c64ec1a-ff1c-4afa-8aa6-2e17ef815155" />


## Exportar dados
* <img width="1915" height="384" alt="image" src="https://github.com/user-attachments/assets/fffb910e-8da8-449f-a134-a31285d52c29" />

## CSV
[players.csv](https://github.com/user-attachments/files/30921159/players.csv)

[clubs.csv](https://github.com/user-attachments/files/30921168/clubs.csv)


# Contexto

* Durante o desenvolvimento deste desafio utilizei IA de forma pontual e limitada,a maior parte da implementação foi realizada sem o auxílio de IA, por se tratar de um fluxo com o qual já tenho familiaridade e que faz parte de situações comuns do desenvolvimento de back-end no dia a dia, para visualizar onde utilizei IA acesse [aqui](IA-prompts.ai)
* Mo arquivo (project-steps.md)[project-steps.md] descrevi o passo a passo que estava fazendo enquanto desenvolvia cada funcionalidade, assim como cada fluxo e função do sistema bem detalhado.


