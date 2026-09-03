<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings_utils.php';
requireAdmin();

if (isset($_GET['logout'])) {
    logoutAdmin();
    header('Location: /index.php');
    exit;
}

$siteLogo = getSetting('site_logo', '');
$siteFavicon = getSetting('site_favicon', '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteFavicon ?: '/assets/img/favicon.png') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PLATAFY FB - Dashboard</title>
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0d1a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PLATAFY Admin">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">

    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="navbar-brand">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?php echo htmlspecialchars($siteLogo); ?>" id="site-logo-img" alt="PLATAFY" style="max-height:38px; vertical-align:middle;">
            <?php else: ?>
                <span id="site-logo-text">PLATAFY</span>
            <?php endif; ?>
        </div>
    <button class="mobile-menu-btn" id="mobile-menu-toggle" aria-label="Menu Mobile">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
    
        <div class="navbar-links">
            <a href="#" class="nav-link active" data-view="dashboard">Dashboard</a>
            <a href="#" class="nav-link" data-view="licenses">Licenças</a>
            <a href="#" class="nav-link" data-view="updates">Atualizações & Backups</a>
            <a href="#" class="nav-link" data-view="settings">Configurações</a>
        </div>
        <div class="navbar-actions">
            <button id="pwa-install-btn" class="btn-sm success" style="display:none; align-items:center; gap:5px;" onclick="installPWA()">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Instalar App
            </button>
            <span class="admin-badge">ADMIN</span>
            <a href="?logout=1" class="logout-btn">Sair</a>
        </div>
    </nav>

    <!-- DASHBOARD VIEW -->
    <main class="main-content" id="view-dashboard">
        <div class="settings-header">
            <div class="settings-title-area">
                <h1>
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--neon);"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard Geral
                </h1>
                <p>Acompanhe o desempenho do sistema, estatísticas de licenças ativas, receita e atividades em tempo real.</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary" id="btn-new-license" onclick="openCreateModal()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Gerar Licença
                </button>
            </div>
        </div>
        
        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="stat-total">0</span>
                    <span class="stat-label">Total de Licenças</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="stat-active">0</span>
                    <span class="stat-label">Licenças Ativas</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="stat-expired">0</span>
                    <span class="stat-label">Expiradas</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon gold">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="stat-revenue">R$ 0</span>
                    <span class="stat-label">Receita (30 dias)</span>
                </div>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="card-header-custom" style="margin-bottom:16px; padding-bottom:12px;">
                    <div class="card-icon-box blue" style="width:38px; height:38px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                    </div>
                    <div class="card-header-titles">
                        <h3 style="font-size:15px;">Status das Licenças</h3>
                        <p style="font-size:11px;">Distribuição de licenças por situação atual</p>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="chart-status"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="card-header-custom" style="margin-bottom:16px; padding-bottom:12px;">
                    <div class="card-icon-box" style="width:38px; height:38px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    </div>
                    <div class="card-header-titles">
                        <h3 style="font-size:15px;">Receita Mensal</h3>
                        <p style="font-size:11px;">Faturamento consolidado das assinaturas</p>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="chart-revenue"></canvas>
                </div>
            </div>
        </div>

        <!-- EXPIRING SOON -->
        <div class="section-card" id="expiring-section" style="display:none; border-color:rgba(255, 204, 0, 0.3);">
            <div class="card-header-custom" style="margin-bottom:16px; padding-bottom:12px; border-bottom-color:rgba(255, 204, 0, 0.15);">
                <div class="card-icon-box" style="width:38px; height:38px; background:rgba(255,204,0,0.12); color:var(--warning); border-color:rgba(255,204,0,0.3);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
                <div class="card-header-titles">
                    <h3 style="font-size:15px; color:var(--warning);">Licenças Expirando em Breve</h3>
                    <p style="font-size:11px;">Assinaturas com vencimento previsto para os próximos 7 dias</p>
                </div>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Chave da Licença</th>
                            <th>Cliente</th>
                            <th>Plano</th>
                            <th>Expira em</th>
                        </tr>
                    </thead>
                    <tbody id="expiring-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- RECENT ACTIVITY -->
        <div class="section-card">
            <div class="card-header-custom" style="margin-bottom:16px; padding-bottom:12px;">
                <div class="card-icon-box purple" style="width:38px; height:38px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                </div>
                <div class="card-header-titles">
                    <h3 style="font-size:15px;">Atividade Recente</h3>
                    <p style="font-size:11px;">Histórico das últimas operações e eventos do sistema</p>
                </div>
            </div>
            <div id="activity-feed"></div>
        </div>
    </main>


    <!-- LICENSES VIEW -->
    <main class="main-content" id="view-licenses" style="display:none;">
        <div class="settings-header">
            <div class="settings-title-area">
                <h1>
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--neon);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Gerenciamento de Licenças
                </h1>
                <p>Consulte, filtre, ative, revogue e gerencie o histórico de licenças dos seus clientes.</p>
            </div>
            <div class="header-actions" style="display:flex; gap:10px;">
                <button class="btn-primary" onclick="openCreateModal()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Nova Licença
                </button>
                <button class="btn-outline" onclick="exportCSV()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Exportar CSV
                </button>
            </div>
        </div>
        
        <!-- FILTERS BAR -->
        <div class="filter-bar">
            <div class="search-input-wrapper">
                <input type="text" id="search-input" placeholder="Buscar por chave, nome do cliente ou e-mail..." onkeyup="if(event.key==='Enter') loadLicenses(1)">
                <span class="search-input-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
            </div>

            <select id="status-filter" class="filter-select" onchange="loadLicenses(1)">
                <option value="">Todos os status</option>
                <option value="active">Ativas</option>
                <option value="inactive">Inativas</option>
                <option value="expired">Expiradas</option>
                <option value="revoked">Revogadas</option>
                <option value="pending">Pendentes</option>
            </select>

            <button class="btn-outline" onclick="loadLicenses(1)">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filtrar
            </button>
        </div>
        
        <!-- TABLE CARD -->
        <div class="section-card" style="padding:0; overflow:hidden;">
            <div class="table-container">
                <table class="data-table" id="licenses-table">
                    <thead>
                        <tr>
                            <th>Chave</th>
                            <th>Cliente</th>
                            <th>Contato</th>
                            <th>Plano</th>
                            <th>Status</th>
                            <th>Validade</th>
                            <th>HWID</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="licenses-tbody">
                        <tr><td colspan="8" class="empty-state">Carregando licenças...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination" id="pagination" style="padding:20px 0;"></div>
        </div>
    </main>


    <!-- UPDATES & BACKUPS VIEW -->
    <main class="main-content" id="view-updates" style="display:none;">
        <div class="settings-header">
            <div class="settings-title-area">
                <h1>
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--neon);"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    Atualizações & Backups Automáticos
                </h1>
                <p>Gerencie a distribuição de novas versões da extensão PLATAFY FB e backups de segurança do banco MySQL.</p>
            </div>
            <button class="btn-primary" onclick="createManualBackup()">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Gerar Backup do Banco MySQL
            </button>
        </div>

        <div class="updates-grid">
            <!-- PUBLICAR ATUALIZAÇÃO -->
            <div class="settings-card">
                <div>
                    <div class="card-header-custom">
                        <div class="card-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12l-5 5"></path><path d="M12 15l5 5"></path></svg>
                        </div>
                        <div class="card-header-titles">
                            <h3>Publicar Nova Versão da Extensão</h3>
                            <p>Envie novos arquivos .zip e atualize os clientes conectados</p>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Versão Atual no Servidor</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            </span>
                            <input type="text" id="update-current-ver" readonly style="opacity:0.8; background:rgba(0,0,0,0.5); cursor:not-allowed;" placeholder="1.0.0">
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Nova Versão (ex: 1.0.1)</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path></svg>
                            </span>
                            <input type="text" id="update-new-ver" placeholder="1.0.1">
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>URL de Download do .ZIP da Extensão</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                            </span>
                            <input type="text" id="update-download-url" placeholder="https://platafyfb.platafy.com/downloads/platafy-FB-v1.0.1.zip">
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Notas de Atualização (Changelog)</label>
                        <textarea id="update-changelog" rows="3" class="form-textarea" placeholder="Descreva as novas funcionalidades e correções desta versão..."></textarea>
                    </div>

                    <div class="switch-container">
                        <div class="switch-info">
                            <span class="switch-title">Atualização Obrigatória</span>
                            <span class="switch-subtitle">Exigir atualização imediata para funcionamento</span>
                        </div>
                        <label class="custom-switch">
                            <input type="checkbox" id="update-mandatory">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <button class="btn-primary btn-save-custom" onclick="publishExtensionUpdate()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                    Publicar Atualização para os Clientes
                </button>
            </div>

            
            <!-- GERENCIADOR DE BACKUPS -->
            <div class="settings-card">
                <div class="card-header-custom">
                    <div class="card-icon-box blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                    </div>
                    <div class="card-header-titles">
                        <h3>Backups de Segurança (MySQL)</h3>
                        <p>Histórico de salvamentos do banco de dados</p>
                    </div>
                </div>

                <div class="backup-list-box" id="backup-list-container">
                    <div style="text-align:center; padding:20px; color:var(--muted); font-size:13px;">Carregando backups...</div>
                </div>
            </div></div>
        </div>
    </main>


    <!-- SETTINGS VIEW -->
    <main class="main-content" id="view-settings" style="display:none;">
        <div class="settings-header">
            <div class="settings-title-area">
                <h1>
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--neon);"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Configurações do Sistema
                </h1>
                <p>Gerencie integrações de pagamento, credenciais de acesso do administrador e personalização da marca.</p>
            </div>
            <div class="settings-badge">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                SISTEMA ATIVO
            </div>
        </div>

        <div class="settings-grid">
            <!-- MERCADO PAGO -->
            <div class="settings-card">
                <div>
                    <div class="card-header-custom">
                        <div class="card-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                        </div>
                        <div class="card-header-titles">
                            <h3>Credenciais Mercado Pago</h3>
                            <p>Integração para recebimento de pagamentos das licenças</p>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Access Token (Produção)</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-2-2l2 2m7 0a9 9 0 11-18 0 9 9 0 0118 0z"></path><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </span>
                            <input type="password" id="setting-mp-token" placeholder="APP_USR-...">
                            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('setting-mp-token', this)" title="Alternar Visibilidade">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                        <div class="field-hint">Utilizado para processar cobranças reais via PIX / Cartão.</div>
                    </div>

                    <div class="form-group-custom">
                        <label>Access Token (Teste / Sandbox)</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                            </span>
                            <input type="password" id="setting-mp-token-test" placeholder="TEST-...">
                            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('setting-mp-token-test', this)" title="Alternar Visibilidade">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Public Key (Chave Pública)</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            </span>
                            <input type="text" id="setting-mp-public-key" placeholder="APP_USR-... ou TEST-...">
                        </div>
                    </div>

                    <div class="switch-container">
                        <div class="switch-info">
                            <span class="switch-title">Modo Sandbox / Testes</span>
                            <span class="switch-subtitle">Simular pagamentos sem cobrança real</span>
                        </div>
                        <label class="custom-switch">
                            <input type="checkbox" id="setting-mp-use-test">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <button class="btn-primary btn-save-custom" onclick="saveMercadoPagoSettings()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Salvar Credenciais do Mercado Pago
                </button>
            </div>

            <!-- ALTERAÇÃO DE SENHA ADMIN -->
            <div class="settings-card">
                <div>
                    <div class="card-header-custom">
                        <div class="card-icon-box blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </div>
                        <div class="card-header-titles">
                            <h3>Senha de Administrador</h3>
                            <p>Atualize a chave de segurança para acesso ao painel cPanel</p>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Senha Atual</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            </span>
                            <input type="password" id="pass-current" placeholder="Sua senha atual de acesso">
                            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('pass-current', this)" title="Alternar Visibilidade">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Nova Senha</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-2-2l2 2m7 0a9 9 0 11-18 0 9 9 0 0118 0z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                            <input type="password" id="pass-new" placeholder="Nova senha (mínimo 6 caracteres)">
                            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('pass-new', this)" title="Alternar Visibilidade">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Confirmar Nova Senha</label>
                        <div class="input-relative">
                            <span class="field-icon-left">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            </span>
                            <input type="password" id="pass-confirm" placeholder="Repita exatamente a nova senha">
                            <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('pass-confirm', this)" title="Alternar Visibilidade">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <button class="btn-primary btn-save-custom" style="background: linear-gradient(135deg, #4d5b9a, #4d9aff);" onclick="changeAdminPassword()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Alterar Senha de Acesso
                </button>
            </div>

            <!-- UPLOAD E ALTERAÇÃO DE LOGOMARCA -->

            <!-- UPLOAD E ALTERAÇÃO DE FAVICON -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:40px; height:40px; border-radius:10px; background:rgba(255, 170, 0, 0.15); display:flex; align-items:center; justify-content:center; color:var(--neon); font-size:18px;">
                            📌
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:16px; font-weight:700;">Favicon do Sistema (Ícone da Aba - PNG)</h3>
                            <p style="margin:2px 0 0; font-size:12px; color:var(--muted);">Personalize o ícone que aparece nas abas do navegador no Painel, Cliente e Checkout.</p>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display:grid; grid-template-columns: 200px 1fr; gap:20px; align-items:center;">
                        <!-- Preview do Favicon Atual -->
                        <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:10px; padding:15px; text-align:center;">
                            <div style="font-size:11px; color:var(--muted); text-transform:uppercase; font-weight:700; margin-bottom:10px;">Favicon Atual</div>
                            <div id="favicon-preview-box" style="display:flex; align-items:center; justify-content:center; height:60px; background:rgba(0,0,0,0.2); border-radius:8px; border:1px dashed var(--border);">
                                <?php if (!empty($siteFavicon)): ?>
                                    <img src="<?= htmlspecialchars($siteFavicon) ?>" id="settings-favicon-preview" style="max-height:36px; max-width:36px; object-fit:contain;" alt="Favicon">
                                <?php else: ?>
                                    <span style="font-size:24px;">📌</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Dropzone de Upload do Favicon -->
                        <form id="form-upload-favicon" enctype="multipart/form-data">
                            <input type="file" id="faviconFileInput" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp" style="display:none;">
                            <div id="faviconDropzone" style="border:2px dashed var(--border); border-radius:10px; padding:20px; text-align:center; cursor:pointer; background:rgba(255,255,255,0.02); transition:all 0.2s;" onclick="document.getElementById('faviconFileInput').click()">
                                <div style="font-size:24px; color:var(--neon); margin-bottom:6px;">📌</div>
                                <span style="font-size:13px; font-weight:600; color:var(--text);">Arraste o novo favicon aqui ou clique para buscar</span>
                                <div style="font-size:11px; color:var(--muted); margin-top:4px;">Formatos recomendados: PNG de 32x32px ou 64x64px (Tamanho Máximo: 2MB)</div>
                            </div>
                            <button type="button" class="btn btn-primary" id="btnUploadFavicon" style="width:100%; margin-top:12px; background:linear-gradient(135deg, var(--neon), #d98200); color:#000; font-weight:700; border:none; padding:10px; border-radius:8px; cursor:pointer;">
                                📌 Fazer Upload e Aplicar Novo Favicon
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="settings-card full-width">
                <div>
                    <div class="card-header-custom">
                        <div class="card-icon-box purple">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        </div>
                        <div class="card-header-titles">
                            <h3>Logomarca do Sistema</h3>
                            <p>Personalize o visual do painel e dos comprovantes do PLATAFY FB</p>
                        </div>
                    </div>

                    <div class="logo-upload-wrapper">
                        <div class="logo-current-box">
                            <span class="logo-current-label">Logo Atual</span>
                            <div id="logo-preview-box" style="display:flex; align-items:center; justify-content:center;">
                                <?php if (!empty($siteLogo)): ?>
                                    <img src="<?php echo htmlspecialchars($siteLogo); ?>" id="settings-logo-preview" style="max-height:55px; max-width:170px;" alt="Logo">
                                <?php else: ?>
                                    <span id="settings-logo-preview-text" style="font-family:'Orbitron',sans-serif; color:var(--neon); font-size:20px; letter-spacing:2px;">PLATAFY</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <div class="upload-dropzone" id="logo-dropzone" onclick="document.getElementById('logo-file-input').click()">
                                <input type="file" id="logo-file-input" accept="image/png, image/jpeg, image/webp, image/svg+xml" style="display:none;" onchange="handleFileSelect(this)">
                                <div class="upload-icon-circle">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                </div>
                                <span class="upload-text-main">Arraste a nova logomarca aqui ou clique para buscar</span>
                                <span class="upload-text-sub">Formatos suportados: PNG, JPG, WEBP, SVG (Tamanho Máximo: 2MB)</span>
                                <span class="file-selected-name" id="logo-file-name"></span>
                            </div>

                            <button class="btn-primary btn-save-custom" style="margin-top:16px;" onclick="uploadSystemLogo()">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                Fazer Upload e Aplicar Nova Logomarca
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>


    <!-- CREATE LICENSE MODAL -->
    <div class="modal-overlay" id="modal-create" style="display:none;">
        <div class="modal-card">
            <div class="modal-header">
                <h3 style="display:flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--neon);"><path d="M12 5v14M5 12h14"></path></svg>
                    Gerar Nova Licença
                </h3>
                <button class="modal-close" onclick="closeModal('modal-create')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group-custom">
                    <label>Nome do Cliente</label>
                    <div class="input-relative">
                        <span class="field-icon-left">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </span>
                        <input type="text" id="create-name" placeholder="Nome completo do assinante">
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>E-mail do Cliente</label>
                    <div class="input-relative">
                        <span class="field-icon-left">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <input type="email" id="create-email" placeholder="cliente@email.com">
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>WhatsApp / Celular do Cliente</label>
                    <div class="input-relative">
                        <span class="field-icon-left">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </span>
                        <input type="text" id="create-phone" placeholder="(11) 99999-9999" oninput="formatPhoneInput(this)">
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>Plano da Licença</label>
                    <select id="create-plan" class="filter-select" style="width:100%; padding:12px 16px;">
                        <option value="manual">Manual (30 dias)</option>
                        <option value="mensal">Mensal - R$ 27,00</option>
                        <option value="trimestral">Trimestral - R$ 67,00</option>
                        <option value="semestral">Semestral - R$ 147,00</option>
                        <option value="anual">Anual - R$ 197,00</option>
                        <option value="vitalicio">Vitalício</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-outline" onclick="closeModal('modal-create')">Cancelar</button>
                <button class="btn-primary" onclick="createLicense()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Gerar Licença
                </button>
            </div>
        </div>
    </div>

    <!-- KEY RESULT MODAL -->
    <div class="modal-overlay" id="modal-key" style="display:none;">
        <div class="modal-card">
            <div class="modal-header">
                <h3 style="display:flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Licença Gerada com Sucesso!
                </h3>
                <button class="modal-close" onclick="closeModal('modal-key')">&times;</button>
            </div>
            <div class="modal-body" style="text-align:center;">
                <p style="color:var(--muted); font-size:13px; margin-bottom:15px;">Chave gerada para ativação na extensão:</p>
                <div class="key-display" id="generated-key">XXXX-XXXX-XXXX-XXXX</div>
                <div style="display:flex; flex-direction:column; gap:12px; margin-top:22px;">
                    <button class="btn-primary" style="width:100%; justify-content:center; padding:12px;" onclick="copyKey()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        Copiar Chave
                    </button>
                    <div id="wa-btn-container"></div>
                </div>
                <p id="key-expiry" style="color:var(--muted); font-size:12px; margin-top:15px;"></p>
            </div>
        </div>
    </div>

    <!-- DETAIL MODAL -->
    <div class="modal-overlay" id="modal-detail" style="display:none;">
        <div class="modal-card" style="max-width:620px;">
            <div class="modal-header">
                <h3 style="display:flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--info);"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    Detalhes da Licença
                </h3>
                <button class="modal-close" onclick="closeModal('modal-detail')">&times;</button>
            </div>
            <div class="modal-body" id="detail-content">
                Carregando detalhes...
            </div>
            <div class="modal-footer" id="detail-actions"></div>
        </div>
    </div>


    <script src="/assets/js/dashboard.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('mobile-menu-toggle');
            const navLinks = document.querySelector('.navbar-links');
            if (toggleBtn && navLinks) {
                toggleBtn.addEventListener('click', function() {
                    navLinks.classList.toggle('active');
                });
                document.querySelectorAll('.navbar-links .nav-link').forEach(link => {
                    link.addEventListener('click', function() {
                        navLinks.classList.remove('active');
                    });
                });
            }
        });
    </script>

</body>
</html>
