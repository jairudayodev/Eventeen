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

As telas de gestão seguem os protótipos, são responsivas e estão organizadas por perfil. O gerenciamento de eventos usa o banco de dados; programação, presença, relatórios, parceiros e finanças ainda são prévias ilustrativas.

Para bancos existentes, reimporte `banco.sql` para adicionar a tabela de vínculos entre eventos e organizadores e o status de evento encerrado. Eventos existentes sem vínculo não aparecem na área de gestão até que recebam ao menos um organizador em `evento_organizadores`.

Presenças e certificados dependem de serem registrados na tabela `inscricoes`.

## Como iniciar o servidor PHP

Com o Apache do XAMPP, acesse `http://localhost/Eventeen/`. Alternativamente, na raiz do projeto:

```sh
php -S 127.0.0.1:8000
```