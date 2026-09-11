# 🚀 Manual de Implantação e Operação: White Label PLATAFY FB

Este guia contém o passo a passo completo, prático e 100% testado para colocar a funcionalidade **White Label** da extensão **PLATAFY FB** e do painel administrativo em produção.

---

## 🎯 O que é o Plano White Label?

O ecossistema White Label permite que você venda planos para parceiros (agências, revendedores, afiliados). Cada parceiro recebe:
1. **Painel Exclusivo de Parceiro (`/parceiro/`)**:
   - Login próprio e seguro.
   - Barra de cota de licenças (ex: 50 licenças contratadas).
   - Gerador de licenças para os clientes do parceiro.
   - Botão de envio da chave para o cliente via WhatsApp em 1 clique com mensagem personalizada contendo o nome da marca do parceiro.
   - Gestão de clientes (ativar, pausar, resetar HWID, renovar e excluir). Ao excluir uma licença, a vaga é devolvida automaticamente para a cota do parceiro.
2. **Extensão Chrome com Skin Dinâmica (Zero Flicker)**:
   - Quando o cliente digita a licença gerada pelo parceiro, a extensão consulta a API e **muda instantaneamente** o nome da marca no topo, o logo, o subtítulo "Licenciado por [Marca]" e os botões de suporte para o WhatsApp do parceiro.
   - Se o cliente remover a licença ou se ela for padrão (sem parceiro), a extensão mantém o visual original PLATAFY FB.

---

## 📋 Passo 1: Atualização do Banco de Dados (MySQL / phpMyAdmin)

1. Acesse o **cPanel** da sua hospedagem e abra o **phpMyAdmin**.
2. Selecione o banco de dados da PLATAFY FB (`fb.platafy.com`).
3. Clique na aba **SQL** no topo.
4. Abra o arquivo [`platafy-fb-panel/sql/white_label_migration.sql`](file:///c:/Users/andbf/Documents/GitHub/platafy-fb/platafy-fb-panel/sql/white_label_migration.sql), copie todo o conteúdo e cole no campo de texto:

```sql
CREATE TABLE IF NOT EXISTS partners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partner_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    brand_name VARCHAR(100) NOT NULL,
    brand_logo_url TEXT DEFAULT NULL,
    support_whatsapp VARCHAR(30) DEFAULT NULL,
    support_url TEXT DEFAULT NULL,
    plan_name VARCHAR(100) DEFAULT 'White Label Pro',
    max_licenses INT DEFAULT 50,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    expires_at TIMESTAMP NULL DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partner_username (username),
    INDEX idx_partner_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @dbname = DATABASE();
SET @tablename = "licenses";
SET @columnname = "partner_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE licenses ADD COLUMN partner_id INT DEFAULT NULL AFTER id, ADD INDEX idx_partner_id (partner_id);"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
```
5. Clique no botão **Executar** (Go).
6. Pronto! A tabela `partners` e a coluna `partner_id` na tabela `licenses` estão criadas e indexadas de forma idempotente.

---

## 📂 Passo 2: Upload dos Arquivos para o Servidor / cPanel

Suba os arquivos atualizados para a pasta raiz do seu domínio (`fb.platafy.com`):

### Arquivos Novos (Basta fazer o upload):
- `parceiro/index.php` (Tela de login exclusiva do parceiro)
- `parceiro/dashboard.php` (Painel com cota e gestão de licenças do parceiro)
- `parceiro/api.php` (Endpoints protegidos para o parceiro)
- `parceiro/logout.php` (Encerramento de sessão do parceiro)
- `api/admin/partners.php` (CRUD completo de parceiros para o Admin Master)
- `sql/white_label_migration.sql` (Script de migração)

### Arquivos Atualizados (Substituir existentes):
- `index.php` (Login unificado - detecta parceiro e redireciona automaticamente para `/parceiro/dashboard.php`)
- `dashboard.php` (Painel Master com a nova aba **"Parceiros White Label"**, modal de cadastro de parceiro e filtro de licenças por parceiro)
- `assets/js/dashboard.js` (Lógica AJAX e reatividade da gestão de parceiros)
- `includes/auth.php` (Sessões e funções de parceiro: `loginPartner()`, `requirePartner()`, etc.)
- `includes/license_utils.php` (Criação de licença vinculada ao parceiro e mensagem personalizada de WhatsApp)
- `api/validate.php` (Retorna dados dinâmicos da marca `brand: { is_white_label: true, brand_name, brand_logo, support_whatsapp, support_url }`)
- `api/check_license.php` (Verifica suspensão de parceiro na checagem periódica)
- `api/admin/licenses.php` (Listagem com JOIN de parceiros e vinculação de licenças)

---

## 🧩 Passo 3: Atualização da Extensão Chrome

Na pasta `platafy-fb-extension`:

1. **Arquivos atualizados/criados**:
   - `branding.js` *(Novo)*: Intercepta a validação de licença, armazena em cache no `chrome.storage.local` e aplica a marca personalizada na interface com fallback seguro.
   - `popup.html`: Foi adicionada a tag `<script src="branding.js"></script>` antes de `popup.js`.
2. **Como Testar Localmente no Chrome**:
   - Abra o Google Chrome e acesse `chrome://extensions/`.
   - Ative o modo **"Modo do desenvolvedor"** no canto superior direito.
   - Clique em **"Carregar sem compactação"** (Load unpacked) e selecione a pasta `c:\Users\andbf\Documents\GitHub\platafy-fb\platafy-fb-extension`.
   - Caso já esteja carregada, clique no botão de **recarregar (ícone de círculo/seta)** da extensão.
3. **Distribuição para os Clientes**:
   - Compacte a pasta `platafy-fb-extension` em um arquivo `.zip` para fornecer aos parceiros/clientes.

---

## 💡 Passo 4: Como Operar o Sistema

### 1. Criando um Novo Parceiro (Como Admin Master):
1. Acesse o painel Master: `https://fb.platafy.com/dashboard.php`
2. No menu lateral, clique na nova aba **"Parceiros White Label"**.
3. Clique no botão verde **"+ Novo Parceiro"**.
4. Preencha os dados:
   - **Nome do Parceiro**: Ex: `Agência Alfa Marketing`
   - **Usuário**: Ex: `alfa`
   - **Senha**: Ex: `SenhaForte123`
   - **Nome da Marca (White Label)**: Ex: `Alfa Lead Master` *(Este é o nome que aparecerá na extensão dos clientes dele)*
   - **URL do Logo (Opcional)**: Link de uma imagem `.png` com fundo transparente (Ex: `https://meusite.com/logo.png`)
   - **WhatsApp de Suporte**: Ex: `5511999998888` *(Clientes com dúvida serão direcionados para cá)*
   - **Link de Suporte / Site**: Ex: `https://agenciaalfa.com.br`
   - **Cota Máxima de Licenças**: Ex: `50`
   - **Validade do Parceiro**: Data de expiração do contrato ou deixe em branco para vitalício.
5. Clique em **"Salvar Parceiro"**.

### 2. Acesso do Parceiro:
- O parceiro pode fazer login pelo link direto: `https://fb.platafy.com/parceiro/` ou pelo login principal `https://fb.platafy.com/`.
- No painel do parceiro, ele visualiza:
  - Total de licenças utilizadas e restantes da cota dele.
  - Botão **"+ Gerar Licença"**.
  - Ao preencher o nome, telefone e plano do cliente, a chave é gerada instantaneamente.
  - Ao clicar em **"💬 Enviar Licença pelo WhatsApp"**, abre uma mensagem já pronta para o cliente dele, assinada com o nome da marca do parceiro!

### 3. A Experiência do Cliente do Parceiro:
- O cliente instala a extensão e insere a licença gerada pelo parceiro.
- Ao ativar, a extensão se transforma:
  - O título muda para o nome da marca do parceiro (Ex: `Alfa Lead Master`).
  - O logo muda para a imagem informada no cadastro do parceiro.
  - O subtítulo passa a ser: *"Licenciado por Alfa Lead Master"*.
  - O botão de suporte abre diretamente o WhatsApp do parceiro.
- Se a licença for removida, a extensão volta ao visual original da PLATAFY FB.
