## Configuração do banco de dados

1. Inicie o Apache e o MySQL pelo XAMPP.
2. No phpMyAdmin, abra **Importar**, selecione `banco.sql` e execute. O script cria o banco `eventeen`, as tabelas e três eventos de exemplo.
3. Confira usuário, senha e endereço do MySQL no início de `config.php`. O padrão local do XAMPP é `root`, sem senha.
4. Acesse `http://localhost/Eventeen/` e crie uma conta para começar.

O arquivo `banco.sql` também pode ser importado pelo terminal:

```sh
mysql -u root < banco.sql
```

## Funcionalidades conectadas

- Cadastro de participante, autenticação e encerramento de sessão por ação POST protegida contra CSRF.
- Configuração da conta: atualização de nome, e-mail, matrícula e alteração de senha.
- Busca e filtro de eventos publicados e futuros.
- Consulta de detalhes e inscrição, com verificação de vagas e prevenção de inscrições duplicadas.
- Consulta e cancelamento de inscrições futuras.
- Cadastro, consulta, edição, cancelamento e encerramento de eventos com vários organizadores; as alterações preservam inscrições e demais registros.
- Cadastro e edição de palestrantes, espaços e atividades por evento, com vínculo restrito aos eventos do organizador.
- Validação da capacidade das atividades em relação ao espaço e ao evento, dos horários dentro do período do evento e de conflitos de espaço e palestrante.
- Registro e remoção de presença por atividade, disponível somente para inscrições Confirmadas e atividades do evento selecionado; o histórico do participante exibe as atividades concluídas.
- Cadastro de parceiros e patrocínios por evento, com controle de negociação, confirmação e pagamentos vinculados ao valor acordado.
- Registro de entradas e despesas, rateio explícito entre eventos organizados pelo usuário e cálculo do saldo por evento considerando somente lançamentos realizados.
- Painel inicial, histórico de participação e certificados, conforme os dados registrados.

## Organização das telas

- `index.php`, `cadastro.php` e `sair.php`: acesso e sessão.
- `participante/`: páginas da área do participante.
- `organizador/`: painel, eventos, programação, presenças, relatórios e ferramentas de gestão.
- `administrador/`: painel administrativo e administração de usuários.
- `includes/gestao.php`: estrutura e navegação compartilhadas pelas telas de gestão.
- `css/style.css` e `css/gestao.css`: estilos da área do participante e das telas de gestão.
- `img/` e `docs/telas/phone/`: recursos visuais e protótipos de referência.

Os nomes antigos das páginas do participante na raiz continuam disponíveis como atalhos para preservar links existentes.

O cadastro público cria contas de participante. Para conceder acesso de organizador ou administrador, altere o campo `perfil` do usuário na tabela `usuarios`; o login encaminha cada perfil para sua área e as páginas verificam a permissão necessária.

As telas de gestão seguem os protótipos, são responsivas e estão organizadas por perfil. O gerenciamento de eventos, palestrantes, espaços, programação, presenças, parceiros, patrocínios e finanças usa o banco de dados; relatórios ainda são uma prévia ilustrativa.

Para bancos existentes, reimporte `banco.sql` para criar as tabelas da programação, adicionar a tabela de vínculos entre eventos e organizadores e o status de evento encerrado. Eventos existentes sem vínculo não aparecem na área de gestão até que recebam ao menos um organizador em `evento_organizadores`.

Presenças e certificados dependem de serem registrados na tabela `inscricoes`.

Para bancos existentes, reimporte `banco.sql` para criar as tabelas de parceiros, patrocínios, lançamentos financeiros e rateios. As despesas rateadas devem somar exatamente o valor total do lançamento e só podem incluir eventos sob a organização do usuário. O saldo por evento considera entradas recebidas menos despesas pagas; valores pendentes e cancelados não alteram o saldo.

## Testes de presença

Com o MySQL do XAMPP iniciado e o banco `eventeen` atualizado por `banco.sql`, execute na raiz do projeto:

```powershell
& 'C:\xampp\php\php.exe' tests\presencas.php
```

O teste verifica a inscrição Confirmada, o vínculo da atividade ao evento e ao organizador, a idempotência do registro, a remoção e a inclusão da atividade no histórico. Os dados criados são removidos ao final.

## Como iniciar o servidor PHP

Com o Apache do XAMPP, acesse `http://localhost/Eventeen/`. Alternativamente, na raiz do projeto:

```sh
php -S 127.0.0.1:8000
```

## Testes de inscrições

Com o MySQL do XAMPP iniciado e o banco `eventeen` criado, execute na raiz do projeto:

```powershell
& 'C:\xampp\php\php.exe' tests\inscricoes.php
```

O teste cria dados temporários e valida duplicidade, horário, lotação, cancelamento e oito inscrições concorrentes para um evento com duas vagas; os dados criados são removidos ao final.

## Testes de programação

Com o MySQL do XAMPP iniciado e o banco `eventeen` atualizado por `banco.sql`, execute:

```powershell
& 'C:\xampp\php\php.exe' tests\programacao.php
```

O teste verifica vínculos, capacidade, período do evento, conflitos de espaço e palestrantes e operações de edição e exclusão. Os dados criados são removidos ao final.

## Testes financeiros

Com o MySQL do XAMPP iniciado e o banco `eventeen` atualizado por `banco.sql`, execute:

```powershell
& 'C:\xampp\php\php.exe' tests\financeiro.php
```

O teste verifica parceiros, patrocínios, limite de pagamentos, permissões por evento, soma de rateios e saldo individual. Os dados criados são removidos ao final.