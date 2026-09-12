<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings_utils.php';

requirePartner();

$siteFavicon = getSetting('site_favicon', '');
$partnerSession = getPartnerSession();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteFavicon ?: '/assets/img/favicon.png') ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel do Parceiro - <?= htmlspecialchars($partnerSession['brand'] ?: 'White Label') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .partner-top-logo {
            max-height: 38px;
            max-width: 140px;
            object-fit: contain;
            vertical-align: middle;
            margin-right: 10px;
        }
        .quota-card {
            background: linear-gradient(135deg, rgba(255,170,0,0.1), rgba(10,13,26,0.8));
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        .quota-progress-track {
            flex: 1;
            min-width: 200px;
            height: 10px;
            background: rgba(255,255,255,0.08);
            border-radius: 5px;
            overflow: hidden;
        }
        .quota-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #ffaa00, #ff8800);
            border-radius: 5px;
            transition: width 0.5s;
        }
        .instructions-box {
            background: rgba(77,91,154,0.12);
            border: 1px solid rgba(77,91,154,0.3);
            border-radius: 14px;
            padding: 20px;
            margin-top: 25px;
        }
        .instructions-box h3 {
            font-size: 15px;
            color: #fff;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .instructions-box p {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
        }
        .copy-box-inline {
            background: rgba(0,0,0,0.4);
            border: 1px dashed rgba(255,170,0,0.4);
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            color: #fff;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="navbar-brand" style="display:flex; align-items:center;">
            <span id="partner-logo-container">
                <?php if (!empty($partnerSession['logo'])): ?>
                    <img src="<?= htmlspecialchars($partnerSession['logo']) ?>" class="partner-top-logo" alt="<?= htmlspecialchars($partnerSession['brand']) ?>">
                <?php endif; ?>
            </span>
            <span id="partner-brand-header" style="font-family:'Orbitron',sans-serif; font-size:16px; color:var(--neon); letter-spacing:1px; font-weight:700;">
                <?= htmlspecialchars($partnerSession['brand'] ?: 'PORTAL PARCEIRO') ?>
            </span>
        </div>

        <div class="navbar-actions">
            <span class="admin-badge" style="background:rgba(255,170,0,0.15); color:var(--neon); border-color:rgba(255,170,0,0.3);">
                PARCEIRO: @<?= htmlspecialchars($partnerSession['username']) ?>
            </span>
            <a href="/parceiro/logout.php" class="logout-btn">Sair</a>
        </div>
    </nav>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="main-content" style="display:block;">
        <div class="settings-header">
            <div class="settings-title-area">
                <h1>
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--neon);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Painel do Parceiro White Label
                </h1>
                <p>Gere e gerencie licenças com a sua marca para seus clientes. As licenças ativadas assumirão automaticamente o nome e suporte da sua empresa na extensão.</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary" id="btn-open-create-partner-license" onclick="openPartnerCreateModal()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Gerar Licença de Cliente
                </button>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="partner-stat-quota">0</span>
                    <span class="stat-label">Cota Contratada</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon gold">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="partner-stat-used">0</span>
                    <span class="stat-label">Licenças Criadas</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="partner-stat-active">0</span>
                    <span class="stat-label">Licenças Ativas</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="partner-stat-remaining" style="color:var(--success);">0</span>
                    <span class="stat-label">Cota Disponível</span>
                </div>
            </div>
        </div>

        <!-- BARRA DE PROGRESSO DE COTA -->
        <div class="quota-card">
            <div style="flex:1;">
                <strong style="color:#fff; font-size:14px; display:block; margin-bottom:4px;">
                    Consumo de Licenças: <span id="quota-text-status">0 / 0</span>
                </strong>
                <span id="quota-text-percent" style="font-size:12px; color:var(--muted);">Calculando uso da cota...</span>
            </div>
            <div class="quota-progress-track">
                <div class="quota-progress-bar" id="quota-progress-bar" style="width:0%;"></div>
            </div>
        </div>

        <!-- FILTROS & BUSCA ULTRA MODERNA -->
        <div class="filter-bar" style="margin-top:24px;">
            <div class="search-input-wrapper">
                <input type="text" id="partner-license-search" placeholder="Buscar por chave, nome do cliente ou telefone..." oninput="debouncePartnerLicenseSearch()" onkeyup="if(event.key==='Enter') loadPartnerLicenses(1)">
                <span class="search-input-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
            </div>

            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <select id="partner-license-status" class="filter-select" onchange="loadPartnerLicenses(1)">
                    <option value="">⚡ Todos os Status</option>
                    <option value="active">🟢 Ativas</option>
                    <option value="inactive">⚪ Inativas</option>
                    <option value="expired">🔴 Expiradas</option>
                    <option value="revoked">⛔ Revogadas</option>
                </select>

                <button class="btn-outline" onclick="loadPartnerLicenses(1)" title="Atualizar lista" style="height:42px; padding:0 18px; border-radius:10px; display:inline-flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                    <span>Atualizar</span>
                </button>
            </div>
        </div>

        <!-- TABELA DE LICENÇAS DO PARCEIRO -->
        <div class="table-container" style="margin-top:15px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Chave de Ativação</th>
                        <th>Cliente</th>
                        <th>WhatsApp</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th>Validade</th>
                        <th>Dispositivo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="partner-licenses-tbody">
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--muted);">Carregando licenças...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINAÇÃO -->
        <div class="pagination" id="partner-licenses-pagination" style="display:none;"></div>

        <!-- INSTRUÇÕES PARA OS CLIENTES DO PARCEIRO -->
        <div class="instructions-box">
            <h3>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                Como Entregar a Extensão para seu Cliente
            </h3>
            <p>
                Ao gerar uma licença para seu cliente, envie a chave de ativação para ele por WhatsApp ou e-mail. Ao abrir a extensão no Google Chrome e inserir a chave que você gerou, a extensão exibirá automaticamente o <strong>nome da sua marca e seu canal de suporte</strong>.
            </p>
            <div class="copy-box-inline">
                <span>📋 <strong>Mensagem Pronta para o Cliente:</strong> "Instale a extensão no Chrome, cole sua chave de ativação e pronto! Em caso de dúvidas, nosso suporte está à disposição."</span>
            </div>
        </div>
    </main>

    <!-- MODAL GERAR LICENÇA PARCEIRO -->
    <div class="modal-overlay" id="modal-create-partner-lic" style="display:none;">
        <div class="modal-card" style="max-width:480px;">
            <div class="modal-header">
                <h3 style="display:flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--neon);"><path d="M12 5v14M5 12h14"></path></svg>
                    Gerar Licença para Cliente
                </h3>
                <button class="modal-close" onclick="closeModal('modal-create-partner-lic')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group-custom">
                    <label>Nome do Cliente *</label>
                    <div class="input-relative">
                        <span class="field-icon-left">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </span>
                        <input type="text" id="partner-client-name" style="padding-left:46px;" placeholder="Nome completo do assinante" required>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>E-mail do Cliente</label>
                    <div class="input-relative">
                        <span class="field-icon-left">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <input type="email" id="partner-client-email" style="padding-left:46px;" placeholder="cliente@email.com">
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>WhatsApp / Celular do Cliente</label>
                    <div class="input-relative">
                        <span class="field-icon-left">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </span>
                        <input type="text" id="partner-client-phone" style="padding-left:46px;" placeholder="(11) 99999-9999" oninput="formatPhoneInput(this)">
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>Validade da Licença *</label>
                    <div class="input-relative">
                        <span class="field-icon-left">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                        </span>
                        <select id="partner-client-plan" class="filter-select" style="width:100%; padding-left:46px;">
                            <option value="mensal">Mensal (30 dias)</option>
                            <option value="trimestral">Trimestral (90 dias)</option>
                            <option value="semestral">Semestral (180 dias)</option>
                            <option value="anual">Anual (365 dias)</option>
                            <option value="vitalicio">Vitalício</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-outline" onclick="closeModal('modal-create-partner-lic')">Cancelar</button>
                <button class="btn-primary" onclick="createPartnerClientLicense()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Gerar Licença
                </button>
            </div>
        </div>
    </div>

    <!-- KEY RESULT MODAL PARCEIRO -->
    <div class="modal-overlay" id="modal-key-partner" style="display:none;">
        <div class="modal-card">
            <div class="modal-header">
                <h3 style="display:flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Licença Gerada com Sucesso!
                </h3>
                <button class="modal-close" onclick="closeModal('modal-key-partner')">&times;</button>
            </div>
            <div class="modal-body" style="text-align:center;">
                <p style="color:var(--muted); font-size:13px; margin-bottom:15px;">Chave gerada para ativação na extensão:</p>
                <div class="key-display" id="partner-generated-key">XXXX-XXXX-XXXX-XXXX</div>
                <div style="display:flex; flex-direction:column; gap:12px; margin-top:22px;">
                    <button class="btn-primary" style="width:100%; justify-content:center; padding:12px;" onclick="copyPartnerKey()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        Copiar Chave
                    </button>
                    <div id="partner-wa-btn-container"></div>
                </div>
                <p id="partner-key-expiry" style="color:var(--muted); font-size:12px; margin-top:15px;"></p>
            </div>
        </div>
    </div>

    <script>
        let currentPartnerLicPage = 1;
        let partnerLicSearchTimeout = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadPartnerStats();
            loadPartnerLicenses(1);
        });

        function debouncePartnerLicenseSearch() {
            clearTimeout(partnerLicSearchTimeout);
            partnerLicSearchTimeout = setTimeout(() => {
                loadPartnerLicenses(1);
            }, 300);
        }

        async function apiCall(url, options = {}) {
            try {
                const res = await fetch(url, options);
                return await res.json();
            } catch (err) {
                return { error: 'Falha de conexão com o servidor.' };
            }
        }

        async function loadPartnerStats() {
            const data = await apiCall('/parceiro/api.php?action=stats');
            if (!data.success) return;

            const s = data.stats;
            const p = data.partner;

            document.getElementById('partner-stat-quota').textContent = s.max_licenses;
            document.getElementById('partner-stat-used').textContent = s.total_used;
            document.getElementById('partner-stat-active').textContent = s.active;
            document.getElementById('partner-stat-remaining').textContent = s.remaining;

            const usedPercent = Math.min(100, Math.round((s.total_used / s.max_licenses) * 100));
            document.getElementById('quota-text-status').textContent = `${s.total_used} / ${s.max_licenses} licenças utilizadas`;
            document.getElementById('quota-text-percent').textContent = `${s.remaining} licença(s) disponível(is) na sua cota (${usedPercent}% utilizado)`;
            
            const bar = document.getElementById('quota-progress-bar');
            bar.style.width = usedPercent + '%';
            if (usedPercent >= 90) {
                bar.style.background = 'linear-gradient(90deg, #ef4444, #dc2626)';
            } else {
                bar.style.background = 'linear-gradient(90deg, #ffaa00, #ff8800)';
            }

            if (p.brand_name) {
                document.getElementById('partner-brand-header').textContent = p.brand_name;
            }
            if (p.brand_logo) {
                document.getElementById('partner-logo-container').innerHTML = `<img src="${p.brand_logo}" class="partner-top-logo" alt="${p.brand_name}">`;
            }
        }

        async function loadPartnerLicenses(page = 1) {
            currentPartnerLicPage = page;
            const search = document.getElementById('partner-license-search')?.value || '';
            const status = document.getElementById('partner-license-status')?.value || '';

            const params = new URLSearchParams({ action: 'licenses', search, status, page });
            const data = await apiCall('/parceiro/api.php?' + params);

            const tbody = document.getElementById('partner-licenses-tbody');
            if (!data.licenses || data.licenses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--muted);">Nenhuma licença encontrada. Clique em "Gerar Licença de Cliente" acima.</td></tr>';
                document.getElementById('partner-licenses-pagination').style.display = 'none';
                return;
            }

            tbody.innerHTML = data.licenses.map(l => `
                <tr>
                    <td style="font-family:'Orbitron',sans-serif; font-size:12px; color:var(--neon); letter-spacing:1px; font-weight:600;">
                        <div style="display:inline-flex; align-items:center; gap:8px;">
                            <span>${l.license_key}</span>
                            <button type="button" class="btn-sm btn-secondary" onclick="copyKeyText('${l.license_key}')" title="Copiar Chave" style="padding:2px 7px; font-size:10px; border-radius:6px; cursor:pointer;">
                                📋 Copiar
                            </button>
                        </div>
                    </td>
                    <td><strong style="color:var(--text);">${escapeHtml(l.client_name || '—')}</strong></td>
                    <td>
                        ${l.client_phone ? `
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span>${escapeHtml(l.client_phone)}</span>
                                ${l.whatsapp_link ? `
                                    <a href="${l.whatsapp_link}" target="_blank" class="btn-sm btn-whatsapp" title="Enviar Licença pelo WhatsApp" style="text-decoration:none; padding:3px 7px;">
                                        💬 Zap
                                    </a>
                                ` : ''}
                            </div>
                        ` : '—'}
                    </td>
                    <td style="text-transform:capitalize; font-weight:600; color:var(--muted);">${l.plan_type}</td>
                    <td><span class="badge badge-${l.status}">${l.status}</span></td>
                    <td>${l.expires_at ? formatDate(l.expires_at) : '∞ Vitalício'}</td>
                    <td style="font-size:11px; color:var(--muted); max-width:110px; overflow:hidden; text-overflow:ellipsis;">${l.hwid || '—'}</td>
                    <td style="text-align:right;">
                        <div class="action-btn-group" style="justify-content:flex-end;">
                            ${l.status === 'active' ? `
                                <button class="btn-sm danger" onclick="partnerLicAction('deactivate', ${l.id})" title="Desativar Licença">Desativar</button>
                            ` : `
                                <button class="btn-sm success" onclick="partnerLicAction('activate', ${l.id})" title="Ativar Licença">Ativar</button>
                            `}
                            <button class="btn-sm" onclick="partnerLicAction('reset_hwid', ${l.id})" title="Resetar Computador/HWID">Liberar PC</button>
                            <button class="btn-sm danger" onclick="partnerLicAction('delete', ${l.id})" title="Excluir licença e liberar cota">🗑️ Liberar Cota</button>
                        </div>
                    </td>
                </tr>
            `).join('');

            renderPartnerLicPagination(data.page, data.pages);
        }

        function renderPartnerLicPagination(current, total) {
            const container = document.getElementById('partner-licenses-pagination');
            if (total <= 1) { container.style.display = 'none'; return; }

            let html = '';
            for (let i = 1; i <= total; i++) {
                html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="loadPartnerLicenses(${i})">${i}</button>`;
            }
            container.innerHTML = html;
            container.style.display = 'flex';
        }

        function openPartnerCreateModal() {
            document.getElementById('modal-create-partner-lic').style.display = 'flex';
            document.getElementById('partner-client-name').value = '';
            document.getElementById('partner-client-email').value = '';
            document.getElementById('partner-client-phone').value = '';
            document.getElementById('partner-client-plan').value = 'mensal';
        }

        async function createPartnerClientLicense() {
            const name = document.getElementById('partner-client-name').value.trim();
            const email = document.getElementById('partner-client-email').value.trim();
            const phone = document.getElementById('partner-client-phone').value.trim();
            const plan = document.getElementById('partner-client-plan').value;

            if (!name) {
                alert('Por favor, informe o nome do cliente.');
                return;
            }

            const res = await apiCall('/parceiro/api.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ client_name: name, client_email: email, client_phone: phone, plan_type: plan })
            });

            if (res.success) {
                closeModal('modal-create-partner-lic');
                document.getElementById('partner-generated-key').textContent = res.license.key;
                document.getElementById('partner-key-expiry').textContent = 'Expira em: ' + (res.license.expires_at ? formatDate(res.license.expires_at) : 'Vitalício');

                const waContainer = document.getElementById('partner-wa-btn-container');
                if (res.license.whatsapp_link) {
                    waContainer.innerHTML = `
                        <a href="${res.license.whatsapp_link}" target="_blank" class="btn-primary btn-whatsapp" style="width:100%; justify-content:center; text-decoration:none; padding:12px;">
                            💬 Enviar Licença pelo WhatsApp
                        </a>
                    `;
                } else {
                    waContainer.innerHTML = '';
                }

                document.getElementById('modal-key-partner').style.display = 'flex';
                loadPartnerStats();
                loadPartnerLicenses(1);
            } else {
                alert('Erro: ' + (res.error || 'Falha ao criar licença'));
            }
        }

        async function partnerLicAction(action, id) {
            if (action === 'delete') {
                if (!confirm('Deseja realmente excluir esta licença? O acesso do cliente será revogado e +1 vaga será devolvida para sua cota.')) return;
            }
            const res = await apiCall(`/parceiro/api.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            if (res.success) {
                loadPartnerStats();
                loadPartnerLicenses(currentPartnerLicPage);
            } else {
                alert('Erro: ' + (res.error || 'Falha na ação'));
            }
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function copyPartnerKey() {
            const key = document.getElementById('partner-generated-key').textContent;
            copyKeyText(key);
        }

        function copyKeyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Chave copiada para a área de transferência: ' + text);
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr.replace(/-/g, '/'));
            return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('pt-BR');
        }

        function formatPhoneInput(input) {
            let v = input.value.replace(/\D/g, '');
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 6) {
                input.value = `(${v.slice(0,2)}) ${v.slice(2,7)}-${v.slice(7)}`;
            } else if (v.length > 2) {
                input.value = `(${v.slice(0,2)}) ${v.slice(2)}`;
            } else if (v.length > 0) {
                input.value = `(${v}`;
            }
        }
    </script>
</body>
</html>
