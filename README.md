# GPM Soluções — serviços de campo B2B

Aplicação CodeIgniter 4.7.4 / PHP 8.3 / MySQL 8 para empresas contratantes de serviços elétricos. Entregues autenticação, permissões, funcionários, empresas, gestão de OS/checklists e estoque de medidores com retirada e devolução. Eletricista inicia atendimento e registra observações e aplicação/retirada de medidores na própria OS. Eletricista encerra a própria OS após checklist final aprovado e regularização dos medidores, registrando resultado e dados finais. Fotos podem ser anexadas durante o atendimento e consultadas com acesso protegido pela OS. Gestor e Operador consultam o relatório de estoque atual, com medidores por detentor.

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
- CNPJ novo/alterado numérico, com 14 dígitos e verificadores válidos; identificadores legados inalterados são preservados. UC/endereço de atendimento pertencem à OS.
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

Estruturas para reservas exclusivas de medidores, instalação atual, checklists com itens bloqueantes/informativos, avaliações/respostas, ocorrências e autoria em FK. CHECKs impedem liberação do checklist de fechamento. O início admite liberação justificada pelo Gestor nas próximas etapas.

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

Todas as alterações usam POST, CSRF e política central de permissões. As operações de OS e histórico compartilham transação e a proteção já usada na alteração de responsáveis/clientes. Novas rotas: GET `/os`, `/os/nova`, `/os/{id}`, `/os/{id}/editar`; POST `/os`, `/os/{id}/atualizar`, `/os/{id}/atribuir`, `/os/{id}/cancelar`. A configuração de modelos/perguntas usa GET/POST explícitos sob `/checklists`.



## Checklist de início e custódia — etapa 3B

Eletricista responde os modelos ativos de início na própria OS atribuída. Respostas obrigatórias ausentes recusam o formulário; item bloqueante reprovado/sem resposta impede o início do atendimento. Gestor pode liberar somente a avaliação atual de início com justificativa. Informativos não bloqueiam; fechamento nunca aceita liberação. Correções preservam as avaliações/respostas/liberações anteriores, consultáveis na OS.

Operações de medidores, execução/fechamento e fotos permanecem nos próximos pontos de revisão. Detalhes e validação em [EntregaFluxoOperacional.md](docs/EntregaFluxoOperacional.md). Etapa 3B aprovada/commitada em `4931f49`.


## Medidores da OS — etapa 3C

O fluxo administrativo original desta etapa foi substituído pela retirada/devolução direta descrita ao final deste documento. Eletricista registra a retirada no depósito e escolhe um medidor da própria posse ao iniciar nova ligação. Cancelamento libera reserva ainda no depósito; equipamento em posse permanece pendente até devolução pelo responsável. O histórico anterior é preservado.

Eletricista registra perda/roubo/dano do equipamento em sua posse na própria OS. Gestor registra ocorrências e baixa com justificativa; Operador consulta. Defeito em campo mantém posse até devolução direta. Perda mantém último responsável/local e bloqueia sua desativação/exclusão; baixa administrativa encerra custódia ativa, preservando dados históricos e impedindo reativação. Ocorrências e movimentos físicos têm auditorias distintas.

Usar `/os/{id}` para equipamento vinculado e `/medidores/{id}` para ocorrência administrativa sem reserva ativa. Sem alteração de banco/dependências: exige esquema operacional atual. Aplicação/retirada, início/fechamento, fotos e relatório continuam pendentes. Validação em [EntregaFluxoOperacional.md](docs/EntregaFluxoOperacional.md); etapa 3C aprovada/commitada em `9da5491`.


## Atendimento em campo — etapa 4A

Eletricista inicia a própria OS atribuída após checklist de início aprovado/liberado. Nova ligação exige selecionar um medidor elegível em bom estado da própria posse; o início vincula sua custódia à OS. O início registra status em_atendimento e horário do servidor; repetir não altera horário. Durante atendimento, acrescenta observações de até 2.000 caracteres pelo celular, preservadas no histórico com autoria. Gestor/Operador consultam, sem executar essas ações em nome do Eletricista.

Acesse `/os` e o detalhe da OS. Sem mudança de banco/dependências. Aplicação/retirada, fechamento, fotos e relatório ainda pendentes. Evidências e limites em [EntregaFluxoOperacional.md](docs/EntregaFluxoOperacional.md); etapa 4A aprovada/commitada em `2551f04`.


## Operações de campo — etapa 4B

Nova ligação permite aplicação do medidor entregue em bom estado na própria posse, com instalação na UC da OS e auditoria. Retirada exige equipamento instalado na UC e empresa da OS; deixa medidor na viatura até a devolução direta registrada pelo Eletricista. Histórico permanece preservado, enquanto instalação atual deixa de apontar equipamento retirado. Só o retorno físico disponibiliza novamente no depósito. Operações são transacionais e recusam estado inválido, vínculo alheio e repetição de aplicação/retirada.

Usar o detalhe da OS em `/os/{id}`. Sem mudança de banco/dependências. Fechamento, fotos e relatório continuam pendentes. Evidências em [EntregaFluxoOperacional.md](docs/EntregaFluxoOperacional.md); etapa 4B aprovada/commitada em `c99aa9b`.


## Encerramento em campo — etapa 4C

Gestor configura e ativa modelos de checklist de **fechamento** para cada tipo de OS em `/checklists`. Eletricista responde todos os modelos ativos na própria OS em atendimento; itens informativos não bloqueiam. Item bloqueante reprovado exige nova avaliação corrigida, sem liberação do Gestor. Alteração de perguntas exige nova resposta.

Antes de encerrar, registre em Meus medidores a devolução dos equipamentos não aplicados/retirados, inclusive defeituosos na viatura. Perdas exigem baixa administrativa. Outras OS e equipamentos avulsos em custódia não são conciliados automaticamente.

Selecione `executado`, `parcial` ou `nao_executado` e informe observações finais (até 2.000 caracteres), justificando o resultado. Nova ligação executada exige medidor aplicado nesta OS e ainda instalado na UC. Corte executado exige confirmação e leitura final não negativa (zero permitido; até três casas decimais com ponto/vírgula). Corte parcial/não executado pode não ter confirmação/leitura. Confirmação e leitura não se aplicam à nova ligação.

Os POST `/os/{id}/checklists/{modelo}/responder-fechamento` e `/os/{id}/encerrar` verificam papel, vínculo, status, CSRF e campos no servidor. Encerramento grava status `encerrada`, horário do servidor, resultado/dados finais e histórico na mesma transação. Não há reabertura nem edição dos dados finais. Fotos e galeria estão descritas na etapa 4D abaixo; relatório de estoque permanece pendente. Evidências em [EntregaFluxoOperacional.md](docs/EntregaFluxoOperacional.md).


## Fotos do atendimento — etapa 4D

Na própria OS em atendimento, Eletricista envia **uma foto por requisição**, em JPEG, PNG ou WebP, até **10 MiB** e **30 fotos ativas por OS**. Descrição opcional até 255 caracteres. O servidor verifica upload HTTP, tamanho físico, MIME por fileinfo e informações de imagem; nome e MIME enviados pelo navegador não controlam o arquivo salvo. SVG, GIF, documentos e arquivos sem imagem válida são recusados. Após erro, a descrição é preservada e o arquivo deve ser selecionado novamente.

Fotos ficam em `CodeIgniter/writable/uploads/os-fotos/{id_os}/`, com nomes aleatórios e permissões privadas (diretórios 0700/arquivos 0600 no ambiente validado). `Config\Fotos::$directory` permite escolher outro diretório privado pelo servidor; armazenamento sob `public/` é recusado. Mantenha PHP **fileinfo** habilitado e permissão de escrita para o usuário do PHP. Não disponibilize `writable/` pelo webserver. Nenhuma regra de .gitignore foi alterada.

A galeria usa originais com exibição responsiva e carregamento lazy, sem gerar miniaturas. O GET `/os/{id}/fotos/{foto}` verifica papel/vínculo/ID aninhado e anexo ativo antes de servir MIME permitido, nome de download seguro, nosniff e no-store. Gestor/Operador consultam todas as OS autorizadas; Eletricista consulta somente suas OS. Fotos continuam disponíveis após encerramento/cancelamento pelo mesmo controle de acesso.

Antes da finalização, Gestor remove logicamente fotos com motivo; Eletricista remove apenas as que enviou na própria OS em atendimento. Operador consulta. A remoção preserva arquivo, autor original e histórico com ator/motivo, libera uma vaga do limite e impede acesso pela URL anterior. OS encerrada/cancelada não aceita upload nem remoção. Os POST de upload/remover usam CSRF. Inserção/anexo/histórico são transacionais; falha após mover o arquivo compensa removendo apenas o arquivo novo. Faça backup conjunto do banco e do diretório privado, incluindo anexos removidos logicamente.

Configure o PHP do ambiente para permitir 10 MiB por arquivo e um POST maior que o arquivo, por exemplo `upload_max_filesize = 10M` e `post_max_size = 12M`. Para o servidor local, a partir de CodeIgniter:

```bash
php -d upload_max_filesize=10M -d post_max_size=12M spark serve --host 127.0.0.1 --port 8080
```

Limites menores do PHP recusam o upload antes da validação de conteúdo; um POST acima do limite global pode ser recusado por CSRF por perder seus campos. Não repita a ação presumindo sucesso: confirme o anexo na galeria. Não há alteração de schema/migração/seeds/dependências nesta etapa; usar o esquema operacional atual. Evidências em [EntregaFluxoOperacional.md](docs/EntregaFluxoOperacional.md). Etapa 4D aprovada e commitada em `b86cb96`. O relatório de estoque está descrito na etapa 5 abaixo.


## Relatório de estoque atual — etapa 5

Gestor e Operador acessam `/relatorios/estoque`, pelos links **Relatório de estoque** na tela Estoque. Eletricista não acessa o relatório global. A rota é somente GET, autenticada, protegida pela política central e sem cache; não altera estoque. O relatório consulta os dados atuais a cada requisição, sem fotografia histórica, exportação ou agregação de quantidades de materiais/unidades diferentes.

Na aba Medidores, filtre série/modelo/fabricante, estado, localização e detentor/último responsável. Cada equipamento não excluído aparece uma vez, com reserva ativa e UC da instalação atual, quando existentes. Históricos de reservas/aplicações/retiradas não duplicam linhas. Perdidos/baixados ficam explícitos e preservam último local/responsável para auditoria; não representam equipamentos físicos disponíveis, e baixado não representa posse ativa. Os links abrem a OS da reserva ativa e o histórico do medidor.

Há 15 registros por página, contagem total dos resultados filtrados e filtros preservados na paginação. Parâmetros não escalares/enums/detentores inválidos são normalizados para os valores padrão; busca tem limite de 100 caracteres. Página deve ser inteiro positivo de até nove dígitos, caso contrário usa a primeira. Busca usa Query Builder, e conteúdo exibido é escapado. A consulta não é uma fotografia transacional: escritas concorrentes podem alterar resultados entre requisições/páginas. Reservas, entregas, aplicação/retirada e devoluções continuam sujeitas às regras transacionais dos Services existentes.

Sem alterações de banco/schema/diagrama/migração/seeds/dependências ou configuração `.env`/`.gitignore`. Requer o esquema operacional atual. Esta entrega conclui o relatório de estoque do plano; dashboards e relatórios de produtividade gerais da especificação não estão incluídos. Validação e revisão em [EntregaFluxoOperacional.md](docs/EntregaFluxoOperacional.md).


## Dashboard operacional — primeira etapa

`/inicio` apresenta total de OS e distribuição por status, tipo e eletricista, incluindo Não atribuída. Gestor/Operador têm visão global; Eletricista tem Minha visão geral, apenas com suas OS e empresas vinculadas. Filtros GET: `data_inicio`, `data_fim`, `status_oss`, `eletricista`, `cliente`. Período inicialmente do primeiro dia do mês até hoje, pela abertura da OS no fuso America/Fortaleza; data final inclui o dia inteiro. Filtro de eletricista aceita ID ou `sem_atribuicao` para a visão global.

Filtros inválidos retornam 422 e não apresentam indicadores; tentativa do Eletricista de consultar outro profissional/empresa retorna 403. Contagens excluem OS removidas logicamente, preservam referências históricas a empresas/profissionais inativos ou excluídos e incluem estados sem registros com zero real. Empresas sem OS podem ser selecionadas na visão global, retornando resultado vazio. As consultas não representam fotografia transacional entre requisições. Rotas continuam somente leitura e sem cache, sem novos campos, migrations ou dependências.

Dashboard aprovado e commitado em `2205d25`. O relatório por eletricista está descrito na segunda etapa abaixo. Evidências em docs/EntregaFluxoOperacional1.md.


## Relatório por eletricista — segunda etapa

Acesse `/relatorios/eletricistas` pelo menu Relatórios ou pelo dashboard. Gestor/Operador consultam todas as OS; Eletricista acessa Meu relatório de atendimentos, apenas com OS próprias e opções vinculadas. Os filtros `data_inicio`, `data_fim`, `status_oss`, `eletricista`, `cliente` têm a mesma validação e escopo do dashboard. O link entre telas preserva filtros; início padrão é mês atual até hoje, pela abertura da OS em America/Fortaleza, incluindo todo o último dia. Página deve ser inteiro positivo com até nove dígitos: inválida retorna 422; página além do final usa a última disponível.

Resumo global e por profissional mostram OS selecionadas, atendidas, tempo médio, amostras válidas/encerradas fora da média e aplicações/retiradas. Atendidas são todas as encerradas, incluindo executado/parcial/nao_executado. Duração é fechamento menos início, em segundos, somente em OS encerradas com ambos os horários e fechamento igual ou posterior ao início. Zero é duração válida. Horários ausentes/invertidos ficam fora da média e sua quantidade é informada. Sem amostras válidas, aparece Sem dados. Média calculada diretamente sobre todas as durações válidas, arredondada ao segundo mais próximo, sem média de médias; apresentação em horas/minutos/segundos, inclusive acima de 24 horas. É tempo corrido, incluindo esperas, não horas efetivas de trabalho.

Medidores contam operações históricas não excluídas de instalação/retirada associadas às OS selecionadas pela abertura, atribuídas ao profissional da OS. Reutilização do mesmo equipamento, retirada posterior ou datas de fechamento/operações fora do período não apagam essas contagens. Não são equipamentos distintos nem saldo atual. Operações são agregadas por OS antes do join, evitando multiplicação das ordens ou durações.

Listagem de 15 OS por página, abertura e ID decrescentes, com UC, empresa, eletricista, tipo, status/resultado, horários, duração, operações e link autorizado para detalhe. Resumos consideram todas as OS filtradas e independem da página. OS/operações excluídas não entram; nomes históricos de empresas/profissionais inativos/excluídos são preservados. Dados escapados, rotas somente GET e sem cache, permissões/vínculo no servidor, filtros inválidos 422 ou acesso fora do escopo 403. Estado vazio apresenta zero real e média Sem dados. Tabelas têm rolagem interna com foco visível e GET funciona sem JavaScript.

Sem mudanças de schema/migrations/seeds/.env/.gitignore/dependências ou banco original/demo. Não inclui exportação, comparação de desempenho ou fotografia transacional entre páginas/requisições. Validações e revisão em docs/EntregaFluxoOperacional1.md.


## Perfil da própria conta

Os três papéis podem abrir o menu da conta no cabeçalho e escolher **Editar perfil**. Com JavaScript, o formulário abre em modal; sem JavaScript, fica disponível em `/perfil`. Permite alterar nome completo, telefone e identificador de acesso, além da senha. CPF, cargo, matrícula, papel e situação permanecem administrativos.

Alterar o identificador exige um e-mail válido e único; identificadores legados inalterados são preservados. Troca de e-mail ou senha exige a senha atual e regenera a sessão. Nova senha vazia mantém a existente, respeitando mínimo de oito caracteres e máximo de 72 bytes. POST `/perfil/atualizar` usa autorização e CSRF; dados administrativos enviados manualmente são rejeitados. Erros não repopulam senhas. **Sair do sistema** continua sendo POST no menu da conta.

Perfil entregue no commit `5a6ed1d`, na branch `bugfix/perfil-medidores-atendimento`. Confirmações entregues no commit `cc8c7db`. Máscaras entregues no commit `328ac23`. Ordenação de checklist entregue no commit `b62b11d`. Retirada direta de medidores entregue no commit `5c32306`. Cards do Eletricista estão descritos abaixo, validados e aprovados para commit.


## Confirmações e exclusões protegidas

As confirmações de exclusão, atendimento e movimentações de estoque usam modal no estilo do projeto, com Cancelar/Confirmar. Cancelar ou fechar não executa a operação. Validação dos campos precede o modal. Sem JavaScript, o primeiro envio do formulário abre uma página de confirmação sem gravar no banco; o envio final continua nas mesmas rotas POST, com CSRF e nova validação no servidor.

Excluir funcionários, empresas, medidores ou perguntas de checklist exige a **senha atual do Gestor em cada exclusão**. O Service confere conta ativa, papel e senha na transação, inclusive para POST manual, conservando as restrições de próprio acesso, último Gestor, pendências, posse e saldo. A senha não é repopulada, persistida na sessão ou dispensada por confirmação anterior. Remoção de fotos continua exigindo autorização e motivo, sem senha. Início/fechamento de OS, cancelamento e movimentos têm confirmação visual, sem senha de exclusão.

O campo `_confirmacao` controla a alternativa visual (`pendente`/`confirmada`); não concede permissões. POSTs manuais continuam sujeitos às mesmas regras de negócio e senha, mesmo sem esse campo. A confirmação sem JavaScript usa somente as mensagens e os campos permitidos da rota efetivamente solicitada, sem destino arbitrário ou transporte de senhas do primeiro envio.


### Máscaras e validação de dados de contato

Telefone de empresa, funcionário e perfil utiliza máscara de fixo/celular, com DDD e 10 ou 11 números. Telefone de funcionário/perfil continua opcional. CNPJ novo ou alterado exige 14 números, verificadores válidos e não admite sequência repetida; permanece único inclusive após exclusão lógica e é armazenado sem máscara. CEP comercial e de atendimento usa `00000-000` e mantém a normalização existente. Não há consultas externas ou novas dependências.

Valores históricos de telefone e CNPJ podem permanecer inalterados ao editar outros campos. A exceção é decidida pelo Service comparando com o registro atual do banco, nunca por uma indicação do formulário. Alterações passam pelas regras atuais; nenhum dado antigo é corrigido automaticamente. Máscaras tratam edição, seleção e colagem; sem JavaScript, os mesmos critérios são aplicados no servidor.


## Ordenação automática das perguntas de checklist

Somente Gestor gerencia perguntas. A ordem não é editável nos formulários: perguntas novas entram no final, edições conservam a posição e exclusões compactam a sequência de 1 a N. Botões Subir/Descer ao lado do número trocam a pergunta com a vizinha imediata, inclusive sem JavaScript; os limites ficam desabilitados. POST `/checklists/{id}/itens/{item}/mover` aceita `direcao=subir` ou `descer`, exige autenticação, permissão e CSRF e verifica o vínculo com o modelo. Ordem enviada manualmente em criação/edição é ignorada. Exclusão continua exigindo a senha atual do Gestor e preserva as respostas históricas.

Criação, edição, exclusão e movimentação usam transação e lock do checklist, após os locks dos gestores ativos, para impedir posições duplicadas por concorrência. Trocas possuem rollback integral. A estrutura física, FKs e índices permanecem iguais; posições de perguntas excluídas podem ser reaproveitadas pelas perguntas atuais, sem índice de unicidade global.

### Migração de posições antigas

A migration `2026-10-07-000001_NormalizeChecklistOrder` é somente de dados: organiza perguntas não excluídas de cada checklist não excluído, ativo ou inativo, por `ordem_chi` e depois `id_chi`, numerando de 1 até N. Preserva IDs, outros campos, timestamps, perguntas excluídas e avaliações/respostas. Reexecutá-la não modifica uma sequência já correta. Nenhuma migration foi executada no banco original nesta entrega. Checklists com ordem antiga zerada, repetida ou espaçada retornam 422 ao alterar perguntas até a migração explícita; não são corrigidos silenciosamente pelo uso do sistema.

Antes de executar em dados existentes, suspenda as escritas, faça backup completo (incluindo histórico de migrations) e valide primeiro uma cópia isolada com nome terminado em `_tests`. Confira conexão/grupo e `php spark migrate:status -g tests`: somente esta migration deve estar pendente; não execute uma sequência de migrations desconhecida. Com esse pré-requisito confirmado, use `php spark migrate -g tests -n App` na cópia, a partir de `CodeIgniter/`, e confira a sequência, IDs, respostas e registros excluídos. A execução no banco original requer autorização específica e conexão conferida; não use scripts de recriação, refresh ou limpeza.

Falhas de DML nesta migration possuem rollback transacional. A ordem anterior não fica armazenada: `down()` recusa inventá-la e exige recuperação pelo backup. Não use `migrate:rollback` para tentar restaurar posições. O runner do CodeIgniter registra a execução na tabela de migrations; eventual criação dessa tabela envolve DDL MySQL, que não tem o mesmo rollback dos dados. Em falha, verifique dados e histórico antes de repetir, mantenha escritas suspensas e use o backup para recuperar a situação anterior quando necessário.


## Correções — retirada e devolução direta de medidores

Eletricista acessa **Meus medidores** em `/meus-medidores`, retira fisicamente no galpão e registra a retirada. Medidor disponível no depósito, sem pendência, passa para `em_transito`, na viatura e em sua posse. A devolução registra condição disponível ou defeito, retorna ao depósito e limpa a posse; equipamento defeituoso não pode ser declarado disponível. As listas têm busca e paginação independente de 15 registros, com rolagem interna no celular.

POST `/meus-medidores/{id}/retirar` e `/meus-medidores/{id}/devolver` exigem conta Eletricista ativa, autorização, vínculo e CSRF. Confirmação visual tem alternativa em página sem JavaScript. Cada operação física grava movimento de quantidade 1, ator, responsável, horário e OS quando vinculada, na mesma transação; locks impedem dupla retirada/devolução. Eletricista continua sem acesso ao CRUD administrativo. Gestor mantém cadastro/baixa; Gestor e Operador consultam estoque/histórico. Ações antigas de envio, reserva, entrega e recebimento administrativo de medidores são negadas por política (403); os Services também recusam chamadas diretas.

Ao iniciar nova ligação, escolha medidor elegível da própria posse após aprovação do checklist inicial. O início cria a custódia na reserva existente com estado `entregue`, cujo nome físico foi preservado; não há entrega administrativa nem segundo movimento físico. Reservas antigas podem ser retiradas pelo responsável da OS, após checklist aprovado; equipamentos já entregues conservam posse/histórico. Devolução de equipamento vinculado concilia a reserva e permite o fechamento quando os demais requisitos forem cumpridos. Aplicação, retirada em campo, cancelamento, ocorrências e baixa mantêm suas regras. Pendências históricas sem reserva conciliada não são corrigidas automaticamente.

Sem alteração de esquema, migrations, seeds, dependências ou banco original/demo. A etapa de cards abaixo substitui o dashboard do Eletricista e restringe relatórios a Gestor/Operador. Evidências de validação desta correção estão em [EntregaCadastrosBase.md](docs/EntregaCadastrosBase.md).


## Correções — Meus atendimentos do Eletricista

Para Eletricista, `/inicio` encaminha para `/os`, exibindo **Meus atendimentos** conforme `designs/Eletricista.html`, adaptado ao celular e desktop. Saudação, data em America/Fortaleza e quantidade de OS próprias atribuídas/em atendimento são reais; a quantidade independe dos filtros e da página. Cards mostram número, UC, empresa, endereço, tipo, prioridade, status e agendamento. **Abrir atendimento** leva ao detalhe autorizado; checklists, início, medidores e fechamento mantêm as validações existentes. Menu oferece Meus atendimentos, Meus medidores e conta, sem dashboard/relatórios.

Filtros GET `q`, `status_oss` e `page`: padrão Pendentes (atribuídas/em atendimento), com consulta de outros estados e Todos. Busca por número, UC, empresa e endereço; 15 cards por página, preservando busca/status. Ordenação: em atendimento, atribuídas e demais estados; dentro de cada grupo, agendamento crescente, não agendadas ao final e ID crescente como desempate. Parâmetros malformados retornam 422 sem listagem; filtros extras não alteram o vínculo imposto no servidor. OS excluídas não aparecem; nomes de empresas históricas são preservados. Sem resultados, apresenta estado vazio, sem dados fictícios. Consulta funciona sem JavaScript.

Relatórios por eletricista e de estoque negam acesso direto ao Eletricista com 403. Gestor/Operador preservam dashboard, relatórios e listagem administrativa de OS. Autenticação, conta ativa, sessão expirada, conteúdo escapado e respostas sem cache permanecem. Não há mudança de esquema, migrations, seeds, dependências, .env ou banco original/demo. A migração de posições antigas do checklist permanece pendente de autorização específica para dados existentes. Validações e limites registrados em [EntregaCadastrosBase.md](docs/EntregaCadastrosBase.md).


## Filtros de pesquisa em menu suspenso

Listagens de clientes, equipe, OS e medidores, Meus atendimentos/Meus medidores, dashboard e relatórios usam o botão **Filtro**, baseado no modelo visual fornecido. Abra o menu para consultar os campos existentes; Buscar/Filtrar aplica a consulta GET, preservando os valores e a paginação. As permissões e validações anteriores permanecem. O menu fecha pelo botão de fechar, Escape, clique fora ou saída do foco; campos com erro ficam visíveis ao carregar a resposta. No celular, a caixa tem rolagem própria e se posiciona conforme o espaço disponível. Sem JavaScript, o botão continua abrindo/fechando o formulário nativamente. Não foram adicionados filtros às páginas que ainda não oferecem pesquisa.


## Telas de ordens de serviço — referências do Figma

Listagem administrativa baseada em `designs/TelaOS.html`: cliente/UC agrupados, tipo/prioridade, eletricista, abertura/agendamento, badges por status e links para detalhe. Mantidos filtros existentes no menu Filtro, paginação e dados reais. Os cards do Eletricista permanecem em Meus atendimentos.

Detalhe baseado em `designs/TelaEditarOS.html`, que representa a consulta/gestão da OS: cards de dados do cliente e do serviço na coluna principal, medidores/ocorrências/histórico/fotos na lateral, com uma coluna no celular. Histórico apresenta evento, autor, data, transição, observação e dados da alteração. Fotos e upload do Eletricista ficam na coluna principal antes do fechamento. Atribuição, cancelamento com motivo/confirmação, checklists, medidores, atendimento e fechamento mantêm permissões e regras existentes. Não foram adicionadas impressão/exportação ou ações administrativas de medidores apenas por aparecerem no protótipo.

Cadastro/edição harmonizados com os cards. Descrição e observações têm a mesma fonte dos demais campos, áreas compactas de três linhas, lado a lado no desktop e empilhadas no celular. No detalhe, usam a mesma hierarquia de labels/conteúdo. Textos longos e quebras de linha são preservados e escapados. Datas da OS usam apresentação brasileira; auditoria mantém segundos e horários armazenados não mudam.


## Retirada excepcional após nova ligação

Após aplicar o medidor, a OS mostra a instalação registrada e orienta a continuar o atendimento. A retirada desse equipamento fica recolhida em **Retirada excepcional do medidor**, com justificativa obrigatória de até 1000 caracteres e confirmação visual. O servidor exige a justificativa também em POST manual e chamadas ao Service, registrando-a no histórico na mesma transação. Retirada de equipamento instalado por outra OS mantém o fluxo anterior. Não é possível reaplicar o equipamento retirado nesta mesma OS; o resultado do fechamento continua sujeito às regras existentes. Sem JavaScript, a página de confirmação preserva a justificativa.

Validação desta correção: 35 testes/440 assertions de operações de campo, fechamento e relatório de estoque passaram sequencialmente no MySQL dedicado `gpm_os_etapa1_tests`. Sintaxe PHP e `git diff --check` passaram; conferência visual no navegador ainda pendente. Commit autorizado pelo usuário; sem push ou integração.


## Escopo do estoque

O estoque e o atendimento trabalham somente com medidores. O módulo de consumíveis foi removido da aplicação, das permissões, rotas, relatórios, scripts de criação e seeds. Instalações novas não criam suas tabelas. Bancos já existentes não são migrados ou limpos automaticamente; tabelas antigas permanecem preservadas e não são consultadas pela aplicação.

Validação da remoção: 209 testes/1.467 assertions passaram em 2min45,1s, sequencialmente no MySQL novo `gpm_sem_consumiveis_tests`, com `vendor/bin/phpunit --no-coverage --bootstrap /tmp/gpm-sem-consumiveis-bootstrap.php --filter '/(OperacoesCampoTest|AtendimentoTest|FechamentoTest|ChecklistEntregaTest|RelatorioEstoqueTest|ConfirmacoesTest|MedidorCustodiaTest|OperationalSchemaTest|EstoqueSomenteMedidoresTest|OrdensServicoTest|AccessTest|UsuariosTest)/' tests`, a partir de `CodeIgniter/`. Cobertura inclui criação/migração greenfield sem tabelas removidas, rotas antigas 404 sem escrita, política sem permissões antigas, OS e estoque sem dependência de consumíveis, checklist, medidores, fechamento, exclusões protegidas, visitantes/papéis/sessão/CSRF e rollback. A primeira execução de 79 testes encontrou somente uma expectativa antiga que exigia entrega antes do atendimento; corrigida antes da regressão final. Suíte integral não executada.

Firefox isolado passou em 320/390/1280/1440 px para Gestor/Operador/Eletricista: OS e relatório autorizado, ausência de links do módulo removido e overflow da página, labels, logo, filtros GET com e sem JavaScript, foco/rolagem interna por teclado e ausência de erros em app.js. Configuração temporária de conexão/automação precisou de ajuste antes dessa execução final. Sintaxe de todos os arquivos PHP em app/tests, `php spark routes` e `git diff --check` passaram. Nenhuma migração ou limpeza em original/demo; somente bancos dedicados `_tests` usados. Alterações locais de README e checklist preservadas; `.gitignore` intocado. Regras locais de AGENTS.md atualizadas. Diagramas, requisitos e documentação operacional referenciados não estão disponíveis no diretório docs e não foram recriados. Commit da remoção autorizado explicitamente pelo usuário. Sem push ou integração.
