# PLATAFY FB — Guia Completo de Instalação no cPanel & Mercado Pago

Este guia orienta o passo a passo para colocar o **Painel Admin + Sistema de Assinaturas Mercado Pago** em produção no cPanel (`platafyfb.platafy.com`) e instalar a extensão no navegador.

---

## 📋 1. Passo a Passo no cPanel

### A) Criar o Banco de Dados MySQL
1. Acesse o seu painel **cPanel**.
2. Vá em **Bancos de Dados MySQL** (ou *Assistente de Banco de Dados MySQL*).
3. Crie um novo banco de dados (ex: `suaconta_platafy`).
4. Crie um novo usuário MySQL e defina uma senha forte (ex: `suaconta_admin`).
5. **Vincule o Usuário ao Banco de Dados** dando **TODOS OS PRIVILÉGIOS**.

### B) Importar o Schema do Banco
1. No cPanel, abra o **phpMyAdmin**.
2. Selecione o banco de dados criado na barra lateral esquerda.
3. Clique na aba **Importar**.
4. Selecione o arquivo `panel/sql/schema.sql` deste projeto.
5. Clique em **Executar** no rodapé.

### C) Subir os Arquivos para a Hospedagem
1. Abra o **Gerenciador de Arquivos** no cPanel.
2. Navegue até a pasta do subdomínio `platafyfb.platafy.com` (ou `public_html/FB`).
3. Faça o upload de **todos os arquivos contidos dentro da pasta `panel/`**:
   - `index.php`
   - `dashboard.php`
   - `config.php`
   - `.htaccess`
   - pasta `api/`
   - pasta `includes/`
   - pasta `assets/`
   - pasta `checkout/`

### D) Configurar o `config.php`
No Gerenciador de Arquivos do cPanel, edite o arquivo `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_usuario_cpanel_platafy'); // Insira o nome do BD do cPanel
define('DB_USER', 'seu_usuario_cpanel_admin');   // Insira o usuário MySQL
define('DB_PASS', 'sua_senha_mysql');            // Insira a senha do usuário MySQL

define('MP_ACCESS_TOKEN', 'APP_USR-xxxxxx...');   // Seu Access Token do Mercado Pago
define('MP_ACCESS_TOKEN_TEST', 'TEST-xxxxxx...');  // Seu Test Token do Mercado Pago
define('MP_USE_TEST', true);                      // Mude para false ao ir para Produção!
```

---

## 💳 2. Configurar o Mercado Pago

1. Acesse o [Painel de Desenvolvedores do Mercado Pago](https://www.mercadopago.com.br/developers/).
2. Vá em **Suas integrações** > crie ou selecione uma aplicação.
3. Copie as suas **Credenciais de Produção e Teste** (Access Tokens) para o arquivo `config.php`.
4. Na barra lateral, acesse **Webhooks**.
5. Em **URL de Notificação**, insira:
   `https://platafyfb.platafy.com/api/webhook_mp.php`
6. Marque os seguintes eventos:
   - ✅ `subscription_preapproval` (Assinaturas)
   - ✅ `subscription_authorized_payment` (Pagamentos de Assinaturas)
7. Clique em **Salvar**.

---

## 📦 3. Testar a Integração de Ponta a Ponta

1. **Acessar o Painel Admin**:
   - Acesse `https://platafyfb.platafy.com`
   - **Usuário**: `admin`
   - **Senha**: `platafy2024` *(recomendado alterar a hash no banco `admins`)*
2. **Página de Venda/Checkout Pública**:
   - Acesse `https://platafyfb.platafy.com/checkout/`
   - Escolha um dos 4 planos configurados (Mensal R$ 27,00 | Trimestral R$ 67,00 | Semestral R$ 147,00 | Anual R$ 197,00).
   - Ao preencher o formulário e clicar em **Assinar Agora**, o cliente é direcionado ao Mercado Pago para vincular a cobrança recorrente.
   - Assim que aprovado, o Webhook cria e ativa a licença automaticamente.

---

## 🧩 4. Instalar a Extensão PLATAFY FB no Chrome

1. Abra o Google Chrome e navegue para `chrome://extensions/`.
2. Ative o **Modo do desenvolvedor** (chave no canto superior direito).
3. Clique em **Carregar sem compactação** (*Load unpacked*).
4. Selecione a pasta principal da extensão: `FB-Extensão`.
5. A extensão **PLATAFY FB** (v1.0.0) aparecerá instalada e com a nova logo da marca.
6. Abra o WhatsApp Web (`web.whatsapp.com`), insira uma chave gerada pelo seu painel para desbloquear a extensão!
