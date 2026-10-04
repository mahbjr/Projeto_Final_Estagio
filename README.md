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
- OS `aberta`/`atribuida`/`em_atendimento` bloqueia inativação/exclusão dos envolvidos; medidores em posse bloqueiam o eletricista.
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


## Fluxo operacional — etapa 1 (04/10/2026)

Implementada a fundação de dados, ainda sem telas ou operações de OS: estados `aberta`, `atribuida`, `em_atendimento`, `encerrada`, `cancelada`; resultado separado `executado`, `parcial`, `nao_executado`. Os três estados não finais bloqueiam inativação/exclusão dos envolvidos. Domínio de medidores inclui reserva, perda e baixa, mantendo localização e posse independentes.

Estruturas para reservas exclusivas de medidores, instalação atual, consumíveis com saldos decimais, reservas e movimentos, checklists com itens bloqueantes/informativos, avaliações/respostas, ocorrências e autoria em FK. CHECKs protegem saldos e impedem liberação do checklist de fechamento. O início admite liberação justificada pelo Gestor nas próximas etapas.

Esta entrega é greenfield: migração recusa OS existentes e dados operacionais legados, sem conversão ou limpeza automática. Inicializar banco separado vazio, suspender escritas e fazer backup antes de DDL. Não aplicar código com estados novos contra esquema operacional antigo. Recuperação de DDL parcial exige restauração, pois MySQL não oferece rollback transacional para toda a migração.

Gestor e Operador gerenciarão OS; Eletricista atenderá apenas as próprias. Reservas, atendimento, bloqueios, uploads e relatório ainda não estão disponíveis pela interface. Sem kits, versões de modelos, cadastro de medidor externo, workflow de reparo, miniaturas ou offline. Consulte `EntregaFluxoOperacional.md` para revisão e validação da etapa.

### Inicialização e migração da fundação operacional

Execute comandos a partir de `CodeIgniter/`. `php spark app:prepare-demo --group demo` inicializa somente um banco demo vazio, diferente do default, usando o esquema atual e dados demonstrativos. Não reexecute schema/seeds em banco com dados.

A migration `CreateOperationalFlow` atende um banco de cadastros compatível com tabelas operacionais vazias. Confirme `database.defaultGroup = demo` e as credenciais do banco separado antes de executar `php spark migrate -g demo`, após backup e pausa de escritas. O argumento `-g` não substitui a configuração da conexão default utilizada pelo MigrationRunner. Se houver OS antigas, a migration recusa e exige outro banco vazio. `migrate:rollback` não remove evidências: recuperar por restauração do backup. O novo código depende do esquema atualizado.

O banco `_tests` continua separado do default e demo; suíte sequencial: `vendor/bin/phpunit --no-coverage`. Os testes cobrem estados, rejeição de banco preenchido, preservação de cadastros e constraints físicas. Novos models não autorizam acesso: filtros e Services das próximas etapas realizarão essa verificação.

Branch da etapa: `feature/banco-auth`. Próximas etapas só avançam após revisão explícita. Os PRs do plano têm `develop` como destino; essa branch ainda não existe localmente e não foi criada/publicada nesta etapa. Login e roles existentes são reutilizados, sem recriação de autenticação.

## Gestão de OS e modelos de checklist — etapa 2

Gestor e Operador consultam, cadastram e editam OS em `/os`, atribuem Eletricista ativo e cancelam antes do atendimento. Cadastro começa em `aberta`; atribuição muda para `atribuida`; cancelamento exige motivo e mantém histórico com autor, horário e mudanças. UC/endereço são próprios de cada OS; agendamento é opcional. OS finalizadas são somente consulta. Em atendimento, edição limitada a prioridade, agendamento e observações administrativas.

Eletricista consulta somente suas OS em `/os` e `/os/{id}`. Consultas manuais a OS de outros profissionais retornam 403. A execução em campo será entregue nas próximas etapas. Não há reatribuição, exclusão ou reabertura de OS.

Gestor configura modelos em `/checklists`: cria inativo, adiciona perguntas (resposta esperada Sim/Não, obrigatoriedade, nível bloqueante/informativo, ordem) e ativa quando houver pelo menos uma pergunta. Perguntas são removidas logicamente. Avaliações/respostas anteriores preservam seu texto e evidências; modelos já utilizados não mudam tipo/etapa. Não há versionamento de modelos nem avaliação de checklist pela interface nesta etapa.

Na etapa 2, cancelamento com materiais vinculados era bloqueado; a etapa 3A libera reservas de consumíveis ainda no depósito, conforme descrito abaixo. Vínculos operacionais de medidores continuam bloqueados até a integração seguinte. Os botões de iniciar, concluir, movimentar materiais e anexar fotos não são exibidos antecipadamente.

Todas as alterações usam POST, CSRF e política central de permissões. As operações de OS e histórico compartilham transação e a proteção já usada na alteração de responsáveis/clientes. Novas rotas: GET `/os`, `/os/nova`, `/os/{id}`, `/os/{id}/editar`; POST `/os`, `/os/{id}/atualizar`, `/os/{id}/atribuir`, `/os/{id}/cancelar`. A configuração de modelos/perguntas usa GET/POST explícitos sob `/checklists`.


## Consumíveis e reservas — etapa 3A

Gestor cadastra materiais em `/consumiveis` com unidade e precisão de 0 a 3 casas decimais, registra entradas com quantidade/referência e reserva pela consulta de uma OS atribuída. Operador consulta catálogo, saldos por detentor e histórico. Eletricista vê os materiais apenas nas próprias OS. Entradas têm ator, horário, origem/destino; reserva é auditada no histórico da OS e não movimenta quantidade física.

Quantidades usam ponto ou vírgula decimal, sem separador de milhar; cálculo exato em milésimos e validação no servidor impedem arredondamento e saldo negativo. Reserva exige disponibilidade e não pode se repetir enquanto ativa para o mesmo material/OS. Unidade e precisão não mudam após movimentação, reserva ou saldo positivo. Exclusão lógica exige todos os saldos zerados e nenhuma reserva ativa.

Cancelamento libera somente reservas de consumíveis não entregues. Custódia e conciliações pendentes não são apagadas nem creditadas ficticiamente ao depósito e bloqueiam desativação/exclusão do responsável. Entrega, consumo, devolução, reserva operacional de medidores e checklist em campo seguem nos próximos pontos de revisão. O estoque completo ainda não está entregue. Não há mudança de banco/dependências para este recorte; exige a fundação operacional atual.

Validação e limites: [EntregaFluxoOperacional.md](docs/EntregaFluxoOperacional.md). Branch local `feature/checklist-estoque`, baseada no commit aprovado da etapa 2 (`baa2208`), aguardando revisão para commit deste recorte.
