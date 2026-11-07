# Eventeen — telas para celular

20 telas em PNG, uma tela por imagem. Foram criadas 19 novas imagens e reunida a consulta de eventos para celular produzida anteriormente. A logo original está em logo-eventeen.png, com fundo transparente.

## Como abrir

Extraia o ZIP e abra indice.html dentro da pasta eventeen-celular. Clique em uma tela para ampliá-la. Os PNGs numerados podem ser inseridos individualmente na documentação.

## Telas e requisitos

| Arquivo | Tela | Perfil | Requisitos |
| :---- | :---- | :---- | :---- |
| 01-login.png | Login | Acesso | RF02 |
| 02-cadastro-usuario.png | Cadastro de participante | Acesso | RF01 |
| 03-inicio-participante.png | Início do participante | Participante | RF06, RF10, RF12 |
| 04-consulta-eventos.png | Consulta de eventos | Participante | RF06, RF09 |
| 05-detalhes-inscricao.png | Detalhes e inscrição | Participante | RF07, RF09, RF15 |
| 06-minhas-inscricoes.png | Minhas inscrições | Participante | RF08, RF10 |
| 07-cancelamento-inscricao.png | Confirmação de cancelamento | Participante | RF08 |
| 08-historico-certificados.png | Histórico e certificados | Participante | RF12, RF17 |
| 09-painel-organizador.png | Painel do organizador | Organizador | RF04, RF09, RF10, RF13 |
| 10-gerenciar-eventos.png | Gerenciamento de eventos | Organizador | RF04, RF05, RF09 |
| 11-cadastro-evento.png | Cadastro e edição de evento | Organizador | RF04, RF05 |
| 12-atividades.png | Atividades e programação | Organizador | RF15 |
| 13-palestrantes.png | Palestrantes | Organizador | RF14 |
| 14-espacos.png | Espaços disponíveis | Organizador | RF16 |
| 15-inscricoes-presencas.png | Inscritos e presença | Organizador | RF10, RF11, RF17 |
| 16-relatorios.png | Relatórios | Organizador | RF13 |
| 17-administracao-usuarios.png | Administração de usuários | Administrador | RF03, RNF04 |
| 18-parceiros-patrocinios.png | Parceiros e patrocínios | Organizador | RF18 |
| 19-pagamentos-despesas.png | Pagamentos e despesas | Organizador | RF19 |
| 20-menu-organizador.png | Menu do organizador | Organizador | RF14–RF19 |

## Decisões para uso no celular

- Uma coluna e cartões substituem as tabelas das versões para computador.  
- Botões maiores, textos curtos e espaços entre ações favorecem o uso por toque.  
- Participante: navegação inferior Início, Eventos, Inscrições e Histórico.  
- Organizador: navegação inferior Painel, Eventos, Presença e Mais. A tela Mais reúne as ferramentas de programação e gestão.  
- Cadastro de evento usa um formulário vertical. Confirmação de cancelamento usa um painel na parte inferior da tela.  
- Presença continua vinculada à inscrição e à atividade; certificados exigem presença confirmada.  
- Pagamentos são registros de movimentações realizadas fora do Eventeen.  
- Trata-se de uma proposta visual para o sistema web no navegador do celular. As imagens não implementam as funções.  
- Nomes, datas, números e valores são exemplos fictícios. Permanecem as decisões de escopo do conjunto anterior, inclusive espaços e administração de usuários previstos nos requisitos.

## Produção

Ferramenta integrada de geração de imagens. As instruções completas usadas na criação estão em prompts-de-criacao.txt. A consulta de eventos e a logo foram reutilizadas do conjunto anterior.