# 🚀 PLATAFY FB — Extensão de Automação para Facebook

Ferramenta de automação para Facebook que funciona como extensão do Google Chrome. Permite postar em grupos em massa, enviar mensagens privadas, extrair listas de grupos e perfis, tudo com delays configuráveis para simular comportamento humano.

---

## 📥 Instalação

### Passo 1 — Baixe e extraia
Extraia o arquivo `.zip` em uma pasta permanente no seu computador (não delete a pasta após instalar).

### Passo 2 — Abra o Chrome
Acesse `chrome://extensions/` na barra de endereços do Chrome.

### Passo 3 — Ative o Modo Desenvolvedor
No canto superior direito da página de extensões, ative o botão **"Modo do desenvolvedor"**.

### Passo 4 — Carregue a extensão
Clique em **"Carregar sem compactação"** e selecione a pasta extraída do PLATAFY FB.

### Passo 5 — Pronto!
O ícone 🚀 aparecerá na barra de ferramentas do Chrome. Clique nele para abrir o painel.

> **Importante:** Mantenha a pasta da extensão no mesmo local. Se você mover ou deletar a pasta, a extensão será desinstalada automaticamente.

---

## 🔧 Como Usar

### Aba Extrair
1. Abra o Facebook no Chrome e navegue até a página desejada (ex: lista de grupos)
2. Clique em **"Extrair Grupos"** ou **"Extrair Amigos"**
3. Revise a lista e remova itens indesejados
4. Clique em **"Salvar Lista"** para exportar como `.txt`

### Aba Postar
1. Cole ou carregue a lista de grupos (URLs, um por linha)
2. Digite sua(s) mensagem(ns) — use `|` para separar variações
3. Adicione imagens/vídeos se desejar (até 10 arquivos)
4. Configure o delay entre postagens (recomendado: 30–90 segundos)
5. Clique em **"Teste Manual"** para verificar as configurações
6. Clique em **"Iniciar Postagem"** para começar

### Aba Mensagens
1. Cole a lista de perfis (URLs, um por linha)
2. Digite a mensagem — use `{nome}` para personalizar com o nome do perfil
3. Configure o delay e clique em **"Iniciar Envio"**

### Aba Config
- Ative sua licença com a chave fornecida
- Configure comportamentos gerais (simulação humana, pular erros, etc.)

---

## ⏱️ Configurações de Delay Recomendadas

| Atividade | Delay Mínimo | Delay Máximo |
|-----------|-------------|-------------|
| Postagem em grupos | 30s | 90s |
| Envio de mensagens | 45s | 120s |
| Extração de dados | 5s | 15s |

---

## ⚠️ Aviso Legal

Esta ferramenta deve ser utilizada com responsabilidade e dentro dos **Termos de Serviço do Facebook**. O uso excessivo ou abusivo pode resultar em restrições ou banimento da conta. O desenvolvedor não se responsabiliza pelo uso indevido da ferramenta.

---

## 🔧 Solução de Problemas

**A extensão não aparece no Chrome:**
- Verifique se o Modo Desenvolvedor está ativado
- Certifique-se de ter selecionado a pasta correta (que contém o arquivo `manifest.json`)

**"Caixa de postagem não encontrada":**
- Certifique-se de estar na página do grupo no Facebook
- O Facebook pode ter atualizado sua interface; aguarde uma atualização da extensão

**Postagem não funciona:**
- Verifique se você está logado no Facebook
- Tente aumentar o delay entre postagens
- Verifique se o grupo permite postagens de membros

---

*PLATAFY FB v1.0.0 — Desenvolvido pela PLATAFY para automação eficiente no Facebook*
