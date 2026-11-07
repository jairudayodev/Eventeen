# CLAUDE.md — Projeto Eventeen

> **Leia este arquivo inteiro no início de toda sessão de trabalho nesta pasta.** Ele é a fonte principal de orientação do projeto: define o que é o Eventeen, quais documentos são a base de verdade, como a documentação do TEMPLATE deve ser produzida, como a plataforma deve ser desenvolvida e quais pontos ainda estão em aberto. Sempre que algo mudar (nova atividade, decisão da equipe, alteração de modelagem, definição de stack), **atualize este arquivo antes de encerrar a tarefa** (ver seção 13).

---

## 1\. Identificação do projeto

| Item | Valor |
| :---- | :---- |
| Sistema | **Eventeen** — aplicação web de gerenciamento de eventos acadêmicos e institucionais |
| Instituição | FATEC Ourinhos — Análise e Desenvolvimento de Sistemas |
| Disciplina principal | Engenharia de Software II |
| Ano | 2026 |
| Metodologia | Scrum (exigida pelo TEMPLATE) |
| Integrantes | Carlos Eduardo Barros · Gabriel Arcangelo de Oliveira · Gustavo Rios Pereira · Jair Roger Pereira da Silva · Vitor Corrêa Silva Fantineli |
| Papéis Scrum (PO, SM, Developers) | \[A DEFINIR PELA EQUIPE\] |
| Quantidade e duração das Sprints | \[A DEFINIR PELA EQUIPE\] — o TEMPLATE usa "oito Sprints de duas semanas" apenas como exemplo |
| Stack tecnológica | \[A DEFINIR PELA EQUIPE\] — ver seção 10 |

---

## 2\. Documentos-fonte e hierarquia de confiança

Guarde os originais em `docs/00-referencias/`. Em caso de conflito, siga a ordem abaixo **e registre o conflito na seção 7** — nunca resolva por conta própria.

| \# | Documento | Papel no projeto | Situação |
| :---- | :---- | :---- | :---- |
| 1 | Instruções expressas do usuário/equipe na conversa | Prevalecem sobre tudo | — |
| 2 | Enunciados das atividades (`docs/01-atividades/`) | Definem o que cada entrega exige | Adicionados conforme forem enviados |
| 3 | `TEMPLATE_Projeto_Engenharia_de_Software_II.docx` | **Estrutura obrigatória** da documentação final (capítulos 1 a 16\) | Recebido |
| 4 | `Eventeen - Viabilidade de produto.docx` (Documento de Visão v1.0) | Contexto, problema, público, objetivos, escopo, benefícios, premissas e restrições | Recebido |
| 5 | `Estudo de Caso 1 – Banco de Dados – Eventeen.docx` | Minimundo ampliado, MER, Modelo Relacional, Dicionário de Dados, justificativas | Recebido — trabalho da disciplina de Banco de Dados, individual (campos de nome e RA em aberto); cardinalidades refinadas e dois vínculos adicionados **ainda pendentes de validação com a professora** |

---

## 3\. Regras de trabalho (obrigatórias)

1. **Não inventar dados.** Nomes, datas, papéis, resultados de testes, story points, conteúdo de Sprints, prints, commits, métricas, referências bibliográficas — nada disso pode ser criado sem fonte. Use marcações:  
   - `[DADO NÃO INFORMADO]` · `[A DEFINIR PELA EQUIPE]` · `[CONFIRMAR]` · `[INSERIR DATA]` · `[INSERIR EVIDÊNCIA]`  
2. **Proposta ≠ fato.** Quando Claude sugerir algo (um requisito, uma persona, uma estimativa), marque como `(proposta — validar com a equipe)` até ser aprovado. Só depois registre como decisão (seção 11).  
3. **Capítulos de execução só com dados reais.** Capítulos 8 a 14 (Sprints, evolução do backlog, gerenciamento, testes, incremento, rastreabilidade preenchida, conclusão) descrevem o que *de fato aconteceu*. Nunca preencher com resultados presumidos.  
4. **Contradições:** não escolher arbitrariamente nem criar uma terceira versão. Registrar na seção 7 e, no texto, usar `[ATENÇÃO: ... Confirmar antes da entrega.]`.  
5. **Referências:** apenas fontes efetivamente consultadas pela equipe. Não criar citações, autores, edições ou links.  
6. **Manter o objetivo da tarefa pedida.** Se pedirem revisão, revise; se pedirem um capítulo, entregue o capítulo. Não reiniciar o projeto nem reescrever partes não solicitadas.  
7. **Coerência transversal:** antes de entregar, conferir se nomes, IDs, entidades, regras e escopo batem com as seções 5, 6 e 7 deste arquivo.  
8. **Idioma:** português do Brasil, linguagem técnica e acadêmica.

---

## 4\. Estrutura de pastas (proposta — ajustar se a equipe preferir outra)

eventeen/

├── CLAUDE.md                     ← este arquivo

├── docs/

│   ├── 00-referencias/           ← TEMPLATE, Documento de Visão, Estudo de Caso BD, material da disciplina

│   ├── 01-atividades/            ← enunciados recebidos: AAAA-MM-DD\_nome-da-atividade.(pdf\|docx\|md)

│   ├── 02-documentacao/          ← um .md por capítulo do TEMPLATE (cap01-introducao.md ... cap16-anexos.md)

│   ├── 03-modelagem/             ← diagramas (.brM, .png, .puml, .mmd) e versões

│   ├── 04-prototipos/            ← exports/links do Figma ou ferramenta escolhida

│   ├── 05-sprints/               ← sprint-01.md ... (planning, tarefas, review, retrospective)

│   ├── 06-evidencias/            ← prints do quadro, commits, testes, telas

│   └── 07-entregas/              ← .docx/.pdf finais entregues (nunca editar depois de entregue; nova versão \= novo arquivo)

├── database/                     ← DDL, seeds, scripts de migração

├── src/                          ← código da plataforma (nome/estrutura conforme stack)

└── tests/                        ← testes automatizados e roteiros de teste manual

**Montagem do documento final:** os capítulos são redigidos em `docs/02-documentacao/*.md` e consolidados em `.docx` seguindo exatamente a numeração e os títulos do TEMPLATE. Se a capa deve seguir o modelo FATEC usado no Documento de Visão ou a capa genérica do TEMPLATE: \[CONFIRMAR\]. Se os blocos "O que fazer / Exemplo" do TEMPLATE devem ser removidos na versão final: \[CONFIRMAR\].

---

## 5\. Contexto consolidado do produto

### 5.1 Problema (Documento de Visão)

A FATEC Ourinhos realiza palestras, workshops, atividades acadêmicas, eventos institucionais e confraternizações. Inscrições e presença são controladas por meios dispersos (formulários, planilhas, listas), o que gera dados espalhados, dificuldade de consulta e maior chance de erro no controle de presença.

### 5.2 Objetivo geral

Desenvolver uma aplicação web para centralizar o gerenciamento de eventos na FATEC, permitindo divulgação, inscrição, acompanhamento e controle de presença.

### 5.3 Público / usuários

- **Documento de Visão:** Alunos, Organizadores, Professores, Administradores.  
- **Estudo de Caso BD (escopo ampliado):** Participante, Organizador, Palestrante, Patrocinador (Apoio).  
- ⚠️ Divergência registrada na seção 7 (item I-02).

### 5.4 Escopo

**Dentro (Visão):** cadastro e autenticação de usuários; cadastro, edição e gerenciamento de eventos; consulta de eventos; inscrição; controle de vagas; consulta de inscrições; registro de presença; histórico de participação; relatórios básicos de inscrições e presença.

**Fora (Visão):** app mobile nativo; redes sociais; integração com sistemas acadêmicos da FATEC; integração com sistemas financeiros; processamento de pagamentos; emissão de certificados digitais; WhatsApp; transmissão online; equipamentos físicos de controle de acesso.

**Ampliação (Estudo de Caso BD, "ajustes sugeridos pela professora"):** palestrantes, atividades, espaços, certificados, patrocínios, pagamentos e contas.

- ⚠️ Conflito com o "fora do escopo" da Visão — ver seção 7 (item I-01). **Não implementar nem documentar certificados/pagamentos como escopo do MVP até a equipe decidir.**

### 5.5 Premissas e restrições (Visão)

Acesso à internet; organizadores mantêm os dados dos eventos; presença depende do uso do sistema pelos responsáveis; aplicação web; projeto acadêmico no período letivo; equipe de cinco integrantes do 2º semestre de ADS; MVP prioriza eventos, inscrições e presença; sem integrações externas.

---

## 6\. Modelo de dados vigente (resumo)

Fonte completa: Estudo de Caso BD, seções 2.2 a 2.5. **Versão V1 — pendente de validação com a professora.**

| Tabela | PK | FKs / restrições |
| :---- | :---- | :---- |
| ORGANIZADORES | id\_organizador | — |
| PALESTRANTE | id\_palestrante | — |
| APOIO | id\_apoio | — |
| ESPACOS\_DISPONIVEIS | id\_espaco | — |
| PARTICIPANTES | id\_participante | — |
| EVENTOS | id\_evento | id\_organizador → ORGANIZADORES |
| ATIVIDADES | id\_atividade | id\_evento → EVENTOS *(vínculo adicionado)*; id\_palestrante → PALESTRANTE; id\_espaco → ESPACOS\_DISPONIVEIS |
| PALESTRANTE\_EVENTO | (id\_palestrante, id\_evento) | associativa N:M |
| PATROCINIO | id\_patrocinio | id\_apoio → APOIO; id\_evento → EVENTOS (associativa N:M com atributos) |
| INSCRICOES | id\_inscricao | id\_participante; id\_evento; id\_atividade (opcional, NULL \= inscrição geral) |
| PGTO | id\_pgto | id\_inscricao → INSCRICOES, UNIQUE |
| PRESENCA | id\_presenca | id\_participante; id\_evento *(vínculo adicionado)* |
| CERTIFICADO | id\_certificado | id\_presenca → PRESENCA, UNIQUE |
| CONTAS | id\_conta | id\_pgto → PGTO, UNIQUE *(relacionamento adicionado)* |

**Regras de domínio já documentadas** (base para o item 4.1 do TEMPLATE; IDs RN serão atribuídos quando o capítulo 4 for elaborado):

- Cada evento tem um único organizador responsável.  
- Inscrições confirmadas em um evento não podem ultrapassar o número de vagas.  
- Inscrição tem status Confirmada ou Cancelada; evento tem status Ativo, Cancelado ou Encerrado.  
- Presença é registrada a partir de uma inscrição existente; nem toda inscrição gera presença (ver I-03).  
- Certificado pode ser gerado a partir de presença confirmada; no máximo um por presença.  
- No máximo um pagamento por inscrição e um lançamento em conta por pagamento.  
- Senhas armazenadas com hash.

**Qualquer alteração no modelo** → atualizar Estudo/itens 5.7–5.9, registrar no item 5.10 do TEMPLATE e na seção 11 deste arquivo.

---

## 7\. Inconsistências e pontos em aberto conhecidos

| ID | Ponto | Onde | Situação |
| :---- | :---- | :---- | :---- |
| I-01 | Visão coloca **certificados, pagamentos e integração financeira fora do escopo**; o Estudo de Caso BD os inclui por sugestão da professora | Visão §8 × Estudo §2.1 | Aberto — definir qual escopo vale para o documento de ES II e para o MVP |
| I-02 | Visão prevê **Administradores e Professores**; o modelo de dados não tem entidade/perfil de administrador nem de professor | Visão §4 × Estudo §2.2 | Aberto |
| I-03 | Minimundo diz que presença parte de uma inscrição, mas PRESENCA não referencia INSCRICOES — o modelo permite presença sem inscrição | Estudo §2.1 × §2.3 | Aberto |
| I-04 | Minimundo associa pagamento ao **patrocínio**; o modelo liga PGTO à **inscrição**. O próprio estudo marca o bloco como pendente | Estudo §2.1 × §2.2 | Aberto |
| I-05 | Minimundo diz que o evento "ocorre em um espaço" e a Visão cita "local" do evento; no modelo, o espaço está ligado só às ATIVIDADES | Visão §7 / Estudo §2.1 × §2.3 | Aberto |
| I-06 | Relação entre `vagas_totais` (evento), `capacidade` (espaço) e inscrição por atividade não definida (há controle de vagas por atividade?) | Estudo §2.3 | Aberto |
| I-07 | Cardinalidades refinadas e vínculos adicionados (id\_evento em ATIVIDADES e PRESENCA; PGTO→CONTAS) aguardam validação | Estudo §2.2 | Aberto |
| I-08 | Documento de Visão datado de 13/08/2026, mas o registro de configuração começa em 14/08 | Visão §1 × tabela de configuração | Baixa relevância — confirmar |
| I-09 | Estudo de Caso menciona "Engenharia de Software II/III" | Estudo, cabeçalho | Confirmar a disciplina correta |

Ao resolver um item: mover a decisão para a seção 11 e marcar aqui como "Resolvido em AAAA-MM-DD (D-XX)".

---

## 8\. Roteiro do documento (TEMPLATE) — status por capítulo

Legenda: ⬜ Não iniciado · 🟨 Base disponível (precisa redação/validação) · 🟦 Em elaboração · ✅ Aprovado pela equipe · ⏳ Depende da execução das Sprints

| Cap. | Seção | Status | Base disponível | Falta |
| :---- | :---- | :---- | :---- | :---- |
| 1 | Introdução (1.1–1.7) | 🟨 | Visão §2, §3, §5, §6, §8 | Justificativa (1.4) em texto próprio; delimitação conforme decisão de I-01 |
| 1 | 1.8 Metodologia | 🟨 | Scrum (TEMPLATE) | Quantidade/duração das Sprints, ferramentas |
| 2 | 2.1, 2.2, 2.4, 2.5 | 🟨 | Visão §4, §7, §8, §9 | Ajuste conforme I-01/I-02 |
| 2 | 2.3 Personas | ⬜ | — | Criar (proposta) e validar |
| 2 | 2.6 Product Goal | ⬜ | — | Definir com a equipe |
| 3 | 3.1 Composição | 🟨 | Lista de integrantes | — |
| 3 | 3.2–3.5 Papéis e colaboração | ⬜ | — | PO, SM, Developers, forma de colaboração |
| 4 | 4.1 Regras de negócio | 🟨 | Seção 6 deste arquivo | Codificar RN, resolver I-03 a I-06 |
| 4 | 4.2 RF / 4.3 RNF | 🟨 | Escopo da Visão; senha com hash; aplicação web | Codificar RF/RNF e validar |
| 4 | 4.4 Restrições | 🟨 | Visão §10 | — |
| 4 | 4.5 Critérios de aceitação | ⬜ | — | Definir por requisito |
| 5 | 5.1–5.6 (UC, especificação, protótipos, classes, atividades, sequência) | ⬜ | — | Elaborar |
| 5 | 5.7–5.9 (MER, MR, Dicionário) | 🟨 | Estudo de Caso BD | Validação (I-07) e alinhamento ao escopo |
| 5 | 5.10 Registro de alterações | ⬜ | — | Iniciar com V1 \= modelagem inicial |
| 6 | Product Backlog (6.1–6.5) | ⬜ | — | Histórias, critérios, story points, priorização |
| 7 | Release Plan (7.1–7.5) | ⬜ | — | Depende de 1.8 e do backlog |
| 8 | Sprints do projeto | ⏳ | — | Preencher só com tarefas realmente executadas |
| 9 | Evolução do backlog | ⏳ | — | Idem |
| 10 | Gerenciamento (quadro, versão, equipe) | ⏳ | — | Evidências reais |
| 11 | Integração e testes finais | ⏳ | — | Resultados reais |
| 12 | Incremento final e implantação | ⏳ | — | Idem |
| 13 | Matriz de rastreabilidade | ⬜/⏳ | Convenções da seção 9 | Montar à medida que os IDs forem criados |
| 14 | Conclusão | ⏳ | — | Ao final |
| 15 | Referências | ⬜ | — | Só fontes efetivamente consultadas |
| 16 | Anexos | ⏳ | — | Evidências |

---

## 9\. Convenções de IDs e rastreabilidade

| Artefato | Formato | Exemplo |
| :---- | :---- | :---- |
| Regra de negócio | RN01, RN02… | RN01 |
| Requisito funcional | RF01… | RF03 |
| Requisito não funcional | RNF01… | RNF01 |
| Caso de uso | UC01… | UC02 |
| História de usuário | HU01… | HU05 |
| Item do Product Backlog | PB01… | PB05 |
| Critério de aceitação | CA01… | CA05 |
| Caso de teste | T01… | T05 |
| Sprint | S1, S2… | S3 |
| Decisão | D-01… | D-04 |
| Inconsistência | I-01… | I-03 |

Regras:

- IDs **nunca são reaproveitados**; itens cancelados permanecem com status "Cancelado".  
- Todo RF deve chegar a pelo menos um UC, uma HU, um PB, um CA e um T (capítulo 13).  
- Commits, branches e tarefas do quadro referenciam o PB/HU correspondente (ex.: `PB05: inscrição em evento`).

---

## 10\. Desenvolvimento da plataforma

### 10.1 Stack e ambiente

| Item | Definição |
| :---- | :---- |
| Linguagem / framework back-end | \[A DEFINIR PELA EQUIPE\] |
| Front-end | \[A DEFINIR PELA EQUIPE\] |
| Banco de dados relacional | \[A DEFINIR PELA EQUIPE\] |
| Controle de versão / repositório | \[A DEFINIR PELA EQUIPE\] |
| Quadro de acompanhamento | \[A DEFINIR PELA EQUIPE\] |
| Ferramenta de prototipação | \[A DEFINIR PELA EQUIPE\] (TEMPLATE cita Figma como exemplo) |
| Ambiente de implantação | \[A DEFINIR PELA EQUIPE\] |

Não escolher stack por conta própria: sugerir opções apenas quando pedido e registrar a escolha como decisão (seção 11).

### 10.2 Comandos

\# instalar:   \[A DEFINIR\]

\# executar:   \[A DEFINIR\]

\# testar:     \[A DEFINIR\]

\# banco/migr: \[A DEFINIR\]

### 10.3 Regras para o código

- O esquema do banco deve corresponder ao Modelo Relacional vigente (seção 6). Mudou o schema → atualizar documentação (5.7–5.10) no mesmo trabalho.  
- Implementar apenas o que tiver item no Product Backlog; funcionalidades fora do escopo (seção 5.4) exigem decisão registrada.  
- Senhas sempre com hash; credenciais e variáveis sensíveis fora do repositório (arquivo de ambiente ignorado pelo versionamento).  
- Regras de negócio da seção 6 devem ser validadas no servidor, não só na interface (ex.: limite de vagas, status de inscrição).  
- Toda funcionalidade concluída deve ter caso de teste associado (T) e evidência em `docs/06-evidencias/`.

### 10.4 Definition of Done

\[A DEFINIR PELA EQUIPE\] — sugestão a validar: código revisado, critérios de aceitação atendidos, testes executados, documentação afetada atualizada, item movido para "Concluído" no quadro.

---

## 11\. Registro de decisões

| ID | Data | Decisão | Motivo / fonte | Afeta |
| :---- | :---- | :---- | :---- | :---- |
| D-01 | 2026-09-23 | Criado este CLAUDE.md como guia central do projeto | Solicitação do usuário | Todo o projeto |
|  |  |  |  |  |

---

## 12\. Fluxo de trabalho ao receber uma nova atividade

1. Salvar o enunciado em `docs/01-atividades/AAAA-MM-DD_nome.ext`.  
2. Registrar na seção 12.1 abaixo (data, atividade, capítulos afetados, prazo se informado).  
3. Identificar quais capítulos do TEMPLATE (seção 8\) e quais partes do código a atividade afeta.  
4. Ler as fontes necessárias (seção 2\) e as seções 5, 6 e 7 deste arquivo.  
5. Produzir a entrega com as marcações da seção 3 onde faltarem dados.  
6. Se houver mudança de modelagem → registrar no item 5.10 do TEMPLATE e na seção 11\.  
7. Atualizar: status (seção 8), inconsistências (seção 7), decisões (seção 11\) e histórico (seção 13).  
8. Encerrar listando apenas as pendências reais que dependem da equipe.

### 12.1 Histórico de atividades recebidas

| Data | Atividade | Disciplina | Capítulos/partes afetados | Prazo | Situação |
| :---- | :---- | :---- | :---- | :---- | :---- |
| — | Documento de Visão (Viabilidade de produto) v1.0 | ES | Base dos cap. 1 e 2 | \[DADO NÃO INFORMADO\] | Entregue (conforme documento recebido) \[CONFIRMAR\] |
| 2026-09-22 | Estudo de Caso 1 – Banco de Dados | Banco de Dados | Base dos itens 5.7–5.9 | \[DADO NÃO INFORMADO\] | Documento elaborado; validação pendente (I-07) |
| 2026-09-23 | TEMPLATE Projeto ES II recebido | ES II | Estrutura de todo o documento | \[DADO NÃO INFORMADO\] | Em andamento |

---

## 13\. Histórico de alterações deste arquivo

| Data | Alteração |
| :---- | :---- |
| 2026-09-23 | Versão inicial, a partir do TEMPLATE de ES II, do Documento de Visão v1.0 e do Estudo de Caso 1 – BD |

