# GPM Soluções — serviços de campo B2B

Aplicação CodeIgniter 4.7.4 / PHP 8.3 / MySQL 8 para empresas contratantes de serviços elétricos. Esta etapa entrega autenticação, permissões, funcionários com dados pessoais/cargo, empresas e medidores com transferências auditadas. Atendimento de OS, instalação/retirada em campo, relatórios e anexos permanecem para etapas posteriores.

## Executar

Comandos PHP/Composer/Spark devem ser executados em `CodeIgniter/`:

```bash
cd CodeIgniter
composer install
php spark serve --host 127.0.0.1 --port 8080
```

Acesse http://127.0.0.1:8080/login. O webroot deve ser sempre `CodeIgniter/public/`.

Neste ambiente, `.env` já aponta para `projeto_estagio_b2b`, grupo `demo`. O banco original `projeto_estagio` em 3306 foi preservado; demo/testes usam uma instância exclusiva em `127.0.0.1:33307`, com usuários restritos a seus respectivos bancos.

Para reiniciar a instância local preparada nesta sessão, se estiver parada e os dados temporários ainda existirem:

```bash
/usr/sbin/mysqld --no-defaults --datadir=/tmp/gpm-b2b-mysql-data --socket=/tmp/gpm-b2b-mysql.sock --port=33307 --bind-address=127.0.0.1 --mysqlx=0 --pid-file=/tmp/gpm-b2b-mysql.pid --log-error=/tmp/gpm-b2b-mysql.log --innodb-buffer-pool-size=64M
```

A instância é temporária. Para uso persistente, crie os bancos/usuários no serviço MySQL e configure `.env` conforme `.env.example`. Credenciais e backup `.env.before-b2b` são ignorados pelo Git.

## Preparar outro ambiente

Crie bancos MySQL **distintos e vazios**, copie `.env.example` para `.env` e preencha credenciais dos grupos `demo`/`tests`. Execute:

```bash
php spark app:prepare-demo
php spark app:prepare-demo --group tests
```

O comando recusa banco não vazio, grupo `default` e destino igual ao banco original. Os scripts de `database/` contêm criação/limpeza de tabelas: use-os apenas em bancos dedicados. Atualize dados existentes pela migração incremental.

## Demonstração

| Papel | Identificador |
|---|---|
| Gestor | `gestor@energia.com.br` |
| Operador | `operador@energia.com.br` |
| Eletricista | `eletricista1@energia.com.br` ou `eletricista2@energia.com.br` |

Senha das seeds: `senha123`. O quadro de acessos no login aparece somente com `app.demoMode = true`; use `false` para contas reais.

## Permissões e integridade

- Gestor administra usuários; Gestor/Operador gerenciam empresas, mas somente Gestor pode excluí-las.
- Eletricista tem papel fixo. A própria conta não pode ser excluída/desativada/rebaixada; o último Gestor ativo é preservado.
- OS `aberta`/`em_andamento` bloqueia inativação/exclusão dos envolvidos; medidores em posse bloqueiam o eletricista.
- Escritas/logout são POST com CSRF. Sessão expira após duas horas de inatividade; situação/papel são relidos por requisição.
- CNPJ normalizado de 14 posições, inclusive alfanumérico. UC/endereço de atendimento pertencem à OS.
- Soft delete mantém identificadores reservados e preserva o histórico.

## Migrar banco legado

Faça backup e suspenda escritas concorrentes. Configure o grupo `default` para o banco legado e execute:

```bash
php spark migrate -g default
```

A migração verifica PF, CNPJ ausente/inválido/duplicado e locais incompatíveis **antes de alterações**. Regularize os registros apontados e execute novamente. Não há conversão automática de PF em PJ nem rollback sem restauração de backup. Um esquema já B2B não tem seus dados modificados. A aplicação demonstrativa continua no grupo `demo`.

## Testar

```bash
vendor/bin/phpunit --no-coverage
php spark routes
php spark filter:check GET /usuarios
php spark filter:check POST /clientes/1/excluir
```

Os testes exigem MySQL dedicado com sufixo `_tests`, diferente de `default`/`demo`. Recriam as tabelas de negócio somente nesse banco; execute sem paralelizar no mesmo banco. Cobrem filtros/rotas reais, sessão, senha, CRUDs, transações, pendências e migração.

## Visual e documentos

Views baseadas em `designs/`, logo oficial, Heroicons, Bootstrap 5.3.8 e Inter locais, com licenças junto aos assets. Login, início, equipe e clientes são responsivos; o início não simula métricas operacionais.

- [Especificação](docs/EspecificacaoProjeto.md)
- [Requisitos e aceite](docs/RequisitosProjeto.md)
- [Diagrama](docs/diagramDB.mmd)
- [Retrospectiva](docs/RETROSPECTIVA.md)
- [Entrega, validação e organização das branches](docs/EntregaAutenticacao.md)

A autenticação foi desenvolvida em `feature/autenticacao-acesso-b2b`; a ampliação atual está em `feature/cadastros-base`. Consulte a entrega dos cadastros base para validação e situação dos PRs.


## Cadastros base e migração de funcionários

Acesse `/usuarios`, `/clientes` e `/medidores`. Gestor administra funcionários e estoque; Operador consulta medidores/histórico e gerencia empresas sem excluí-las. Cargo não altera permissões.

Antes de migrar um banco existente, faça backup completo e suspenda escritas de todas as instâncias da aplicação. Em ambiente de demonstração, execute em `CodeIgniter/`:

```bash
php spark migrate -g demo
```

A migração `CentralizeFuncionarioData` verifica CPF inválido/duplicado e técnicos sem conta antes do DDL. Copia os dados pessoais para a conta, preserva IDs, hashes, auditoria, papéis e FKs, e remove as três colunas pessoais do cadastro técnico. Contas sem informação permanecem com campos nulos, inclusive cargo; o login funciona e a próxima edição exige preenchimento. Não reaplique seeds em banco existente.

DDL MySQL não tem rollback transacional. Em falha parcial, mantenha as escritas suspensas e restaure o backup completo antes de repetir; `migrate:rollback` não desfaz esta migração. Nunca execute os scripts de recriação no banco original. Estados legados de medidores não são corrigidos automaticamente.

Testes de concorrência usam processos PHP (`pcntl`) e conexões separadas dentro de um único teste. Continue executando a suíte sequencialmente no MySQL `_tests`.

Veja [entrega dos cadastros base](docs/EntregaCadastrosBase.md) para evidências e limitações.
