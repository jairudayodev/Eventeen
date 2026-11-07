# Eventeen — protótipos de telas

Criado em 05/10/2026. Cada PNG representa uma única tela ou um estado dessa tela. A identidade usa vermelho \#B7192D, rosa claro, branco e cinza. A logo original está em logo-eventeen.png, com fundo transparente.

## Como usar

Abra indice.html para ver a galeria. Clique em uma imagem para abrir o PNG em tamanho completo. Os arquivos numerados podem ser inseridos diretamente no item 5.4 — Protótipos de Tela da Documentação do Desenvolvimento. O arquivo 06 mostra o estado de confirmação de cancelamento de inscrição; o arquivo 19 mostra a consulta de eventos adaptada ao navegador do celular.

## Telas e requisitos

| Arquivo | Tela | Requisitos relacionados |
| :---- | :---- | :---- |
| 01-login.png | Login | RF02 |
| 02-cadastro-usuario.png | Cadastro de participante | RF01 |
| 03-inicio-participante.png | Início do participante | RF06, RF10, RF12 |
| 04-consulta-eventos.png | Consulta de eventos | RF06, RF09 |
| 05-detalhes-inscricao.png | Detalhes e inscrição no evento | RF07, RF09, RF15 |
| 06-minhas-inscricoes.png | Minhas inscrições e cancelamento | RF08, RF10 |
| 07-historico-certificados.png | Histórico e certificados | RF12, RF17 |
| 08-dashboard-organizador.png | Dashboard do organizador | RF04, RF09, RF10, RF13 |
| 09-gerenciar-eventos.png | Gerenciamento de eventos | RF04, RF05, RF09 |
| 10-cadastro-evento.png | Cadastro e edição de evento | RF04, RF05 |
| 11-atividades.png | Atividades e programação | RF15 |
| 12-palestrantes.png | Gerenciamento de palestrantes | RF14 |
| 13-espacos.png | Espaços disponíveis | RF16 |
| 14-inscricoes-presencas.png | Inscritos e registro de presença | RF10, RF11, RF17 |
| 15-relatorios.png | Relatórios de inscrições e presença | RF13 |
| 16-administracao-usuarios.png | Administração de usuários | RF03, RNF04 |
| 17-parceiros-patrocinios.png | Parceiros e patrocínios | RF18 |
| 18-pagamentos-despesas.png | Pagamentos e despesas | RF19 |
| 19-consulta-eventos-celular.png | Consulta de eventos no navegador do celular | RF06, RNF05 |

## Decisões usadas na proposta

- Seguido o escopo ampliado da Documentação do Desenvolvimento atual: palestrantes, atividades, certificados, parceiros, patrocínios e registros financeiros.  
- Seguido o Estudo de Caso atual para permitir vários organizadores por evento, vários palestrantes por atividade e presença vinculada à inscrição e à atividade.  
- A tela de espaços foi mantida para atender ao RF16 da Documentação do Desenvolvimento. O modelo de banco atual não apresenta essa entidade; esse alinhamento precisa ser decidido pela equipe.  
- Administração de usuários foi incluída para atender ao RF03, ainda que o modelo de banco não apresente uma entidade específica para administradores.  
- Certificados aparecem disponíveis apenas quando há presença confirmada. Emissão e download são ações propostas, sem assinatura digital.  
- A área financeira registra pagamentos de inscrição realizados fora da aplicação, patrocínios e despesas. Não inclui integração com bancos nem processamento de pagamentos.  
- Nomes, contatos, eventos, datas, valores e indicadores exibidos são fictícios, usados apenas para demonstrar a interface. A galeria é um índice das imagens; os botões dentro dos PNGs não são funcionais.  
- Cadastro livre cria acesso de participante. Perfis de organização e administração são definidos na gestão de usuários.

## Fontes fornecidas

Documentação do Desenvolvimento \- Eventeen.pdf; Estudo de Caso 1 – Banco de Dados – Eventeen.pdf; Viabilidade de produto \- Eventeen.docx.pdf.

## Produção

Imagens criadas pela ferramenta integrada de geração de imagens. As instruções usadas estão em prompts-de-criacao.txt.