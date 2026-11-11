# Eventeen

Aplicação web para gerenciamento de eventos acadêmicos e institucionais da Fatec Ourinhos: divulgação, inscrição com controle de vagas, programação, presença por atividade, certificados, relatórios e controle financeiro dos eventos.

Projeto da disciplina de Engenharia de Software II (ADS, 2026). A documentação do desenvolvimento está em `docs/Documentação do Desenvolvimento - Eventeen v2.docx`.

## Tecnologias

- PHP 8.1 ou superior (PDO MySQL e mbstring), sem frameworks nem dependências externas.
- MySQL 8 ou MariaDB 10.6+ (testado com o MariaDB do XAMPP e com MariaDB 11.4).
- HTML e CSS responsivos (`css/style.css` para a área do participante e `css/gestao.css` para as áreas de gestão).

## Configuração do banco de dados

1. Inicie o Apache e o MySQL pelo XAMPP.
2. No phpMyAdmin, abra **Importar**, selecione `banco.sql` e execute. O script cria o banco `eventeen`, as tabelas e três eventos de exemplo.
3. Confira usuário, senha e endereço do MySQL no início de `config.php`. O padrão local do XAMPP é `root`, sem senha.
4. Acesse `http://localhost/Eventeen/` e crie uma conta para começar.

O arquivo também pode ser importado pelo terminal:

```sh
mysql -u root < banco.sql
```

O `banco.sql` pode ser reimportado sobre um banco existente: ele cria as tabelas que faltam e adiciona as colunas novas (`eventos.valor_inscricao`, `patrocinios.tipo_apoio` e `patrocinios.descricao_apoio`) sem apagar dados.

### Primeiro administrador

O cadastro público cria somente contas de participante. Depois de criar a sua conta, promova-a a administrador uma única vez pelo phpMyAdmin ou pelo terminal:

```sql
UPDATE eventeen.usuarios SET perfil = 'administrador' WHERE email = 'seu-email@exemplo.com';
```

A partir daí, o administrador concede ou revoga os perfis de organizador e administrador pela tela **Usuários**. O último administrador não pode ser revogado.

## Funcionalidades

| Área | Funcionalidades |
| --- | --- |
| Acesso | Cadastro de participante, login, "manter conectado", logout por POST com proteção CSRF, alteração de dados e de senha. O perfil é relido a cada requisição, então concessões e revogações valem imediatamente. |
| Administração | Painel com totais reais, busca e filtro de usuários, edição de dados e de perfil (participante, organizador, administrador), com proteção do último administrador e do organizador único de um evento ativo. |
| Eventos | Cadastro, edição, publicação, cancelamento e encerramento, com vários organizadores, vagas, local e valor de inscrição opcional. As alterações preservam inscrições e demais registros. |
| Consulta e inscrição | Busca e filtro de eventos publicados, detalhes com programação, vagas restantes e valor; inscrição transacional com bloqueio de concorrência e sem duplicidade. |
| Cancelamento | Permitido até 24 horas antes do início do evento e enquanto não houver presença registrada; liberação da vaga uma única vez. |
| Programação | Palestrantes, espaços e atividades por evento; cada atividade tem espaço e ao menos um palestrante, ocorre dentro do período do evento e respeita a capacidade do espaço e do evento, sem conflito de espaço ou de palestrante. |
| Presença | Registro e remoção de presença por atividade, somente para inscrições Confirmadas e atividades do próprio evento. |
| Histórico e certificados | Histórico de eventos e atividades do participante; emissão de certificado em PDF por presença em atividade concluída, com código único e página pública de validação (`validar-certificado.php`). |
| Relatórios | Inscrições confirmadas e canceladas, participantes presentes (distintos) e taxa de presença por evento; presenças por atividade; filtros por evento e período; impressão. |
| Parceiros e finanças | Parceiros; patrocínios financeiros, materiais ou institucionais; pagamentos de patrocínio até o valor acordado; pagamentos de inscrição (um por inscrição, confirmação com data e estorno em até 7 dias); entradas e despesas com rateio entre eventos; saldo por evento. |

## Organização do código

- `index.php`, `cadastro.php`, `sair.php`, `configuracoes-conta.php` e `validar-certificado.php`: acesso, sessão, conta e validação pública de certificados.
- `participante/`: área do participante (início, eventos, detalhes, inscrições, cancelamento, histórico e certificado).
- `organizador/`: painel, eventos, programação, presenças, relatórios, parceiros e financeiro.
- `administrador/`: painel administrativo e gestão de usuários e permissões.
- `includes/`: regras de negócio reutilizadas pelas telas e pelos testes (`inscricoes`, `programacao`, `presencas`, `certificados`, `relatorios`, `financeiro`, `usuarios`) e a estrutura das telas de gestão (`gestao.php`).
- `tests/`: testes automatizados executados pela linha de comando.
- `docs/`: documentação, estudos e protótipos das telas.

Os nomes antigos das páginas do participante na raiz (`eventos.php`, `minhas-inscricoes.php` etc.) continuam disponíveis como atalhos para preservar links existentes.

## Como executar

Com o Apache do XAMPP, acesse `http://localhost/Eventeen/`. Alternativamente, na raiz do projeto:

```sh
php -S 127.0.0.1:8000
```

## Testes automatizados

Com o MySQL/MariaDB iniciado e o banco `eventeen` atualizado por `banco.sql`, execute na raiz do projeto (no XAMPP, use `C:\xampp\php\php.exe` no lugar de `php`):

```sh
php tests/inscricoes.php
php tests/programacao.php
php tests/presencas.php
php tests/certificados.php
php tests/relatorios.php
php tests/usuarios.php
php tests/financeiro.php
php tests/pagamentos.php
```

Cada teste cria dados temporários, imprime `OK` com o número de verificações e remove os dados ao final.

| Arquivo | O que verifica |
| --- | --- |
| `inscricoes.php` | Duplicidade, horário, lotação, cancelamento com 24 h de antecedência e sem presença, reativação e oito inscrições concorrentes para duas vagas. |
| `programacao.php` | Vínculos ao evento, capacidade, período do evento, conflitos de espaço e de palestrante, edição e exclusão. |
| `presencas.php` | Inscrição Confirmada, atividade do evento, permissão do organizador, idempotência, remoção e histórico. |
| `certificados.php` | Exigência de presença, atividade concluída, código único, reemissão sem duplicidade, carga horária, PDF válido, validação pelo código e bloqueio da remoção de presença certificada. |
| `relatorios.php` | Massa do critério CA-RF13 (3 confirmadas, 1 cancelada, 2 presentes), contagem distinta entre atividades, filtro de período e isolamento entre organizadores. |
| `usuarios.php` | Hash com salt, concessão e revogação de perfis, e-mail único, último administrador e organizador único de evento. |
| `financeiro.php` | Parceiros, patrocínios, limite de pagamentos do patrocínio, permissões por evento, soma de rateios e saldo. |
| `pagamentos.php` | Pagamento único por inscrição, evento gratuito, permissões, confirmação com data, estorno em até 7 dias, saldo e tipos de apoio. |

O teste de "último administrador" só é aplicado quando o banco não tem outros administradores.
