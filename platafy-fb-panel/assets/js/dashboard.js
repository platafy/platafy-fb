/**
 * PLATAFY FB - Dashboard JavaScript
 */

let currentPage = 1;
let statusChart = null;
let revenueChart = null;

// ========== NAVIGATION ==========
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const view = link.dataset.view;
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        document.querySelectorAll('.main-content').forEach(v => v.style.display = 'none');
        document.getElementById('view-' + view).style.display = 'block';
        if (view === 'licenses') loadLicenses();
        if (view === 'partners') loadPartners();
        if (view === 'updates') loadUpdatesInfo();
        if (view === 'settings') loadSettingsInfo();
    });
});

// ========== INIT ==========
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    setupFaviconDropzone();
    
    // Search on Enter
    document.getElementById('search-input')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') loadLicenses();
    });
});

// ========== LOAD STATS ==========
async function loadStats() {
    try {
        const data = await apiFetch('api/admin/stats.php');
        if (!data.success) return;
        
        const s = data.stats;
        document.getElementById('stat-total').textContent = s.total;
        document.getElementById('stat-active').textContent = s.active;
        document.getElementById('stat-expired').textContent = s.expired;
        document.getElementById('stat-revenue').textContent = 'R$ ' + s.monthly_revenue.toFixed(2).replace('.', ',');
        
        // Status Chart
        renderStatusChart(s);
        
        // Revenue Chart
        renderRevenueChart(data.revenue_by_month);
        
        // Activity Feed
        renderActivityFeed(data.recent_activity);
        
        // Expiring Soon
        if (data.expiring_soon && data.expiring_soon.length > 0) {
            document.getElementById('expiring-section').style.display = 'block';
            const tbody = document.getElementById('expiring-tbody');
            tbody.innerHTML = data.expiring_soon.map(l => `
                <tr>
                    <td style="font-family:'Orbitron',sans-serif; font-size:11px; color:var(--neon);">
                    <div style="display:inline-flex; align-items:center; gap:6px;">
                        <span>${l.license_key}</span>
                        <button type="button" class="btn-sm btn-secondary" onclick="copyLicenseKey(this, '${l.license_key}')" title="Copiar Chave" style="padding:2px 5px; font-size:9px; border-radius:4px; cursor:pointer;">📋 Copiar</button>
                    </div>
                </td>
                    <td>${l.client_name || '-'}</td>
                    <td>${l.plan_type}</td>
                    <td style="color:var(--warning);">${formatDate(l.expires_at)}</td>
                </tr>
            `).join('');
        }
    } catch (err) {
        console.error('Erro ao carregar stats:', err);
    }
}

function renderStatusChart(stats) {
    const ctx = document.getElementById('chart-status');
    if (!ctx) return;
    if (statusChart) statusChart.destroy();
    
    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Ativas', 'Expiradas', 'Inativas', 'Revogadas'],
            datasets: [{
                data: [stats.active || 0, stats.expired || 0, stats.inactive || 0, stats.revoked || 0],
                backgroundColor: ['#00e676', '#ff5555', '#7882a0', '#ff3333'],
                hoverBackgroundColor: ['#33eb91', '#ff7777', '#9ba5c4', '#ff5555'],
                borderWidth: 2,
                borderColor: '#0a0d1a',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#b0b8cc', font: { family: 'Inter', size: 12, weight: '600' }, padding: 18, usePointStyle: true, pointStyle: 'circle' }
                }
            },
            cutout: '70%'
        }
    });
}

function renderRevenueChart(revenueData) {
    const ctx = document.getElementById('chart-revenue');
    if (!ctx) return;
    if (revenueChart) revenueChart.destroy();
    
    const labels = (revenueData || []).map(r => {
        const [y, m] = r.month.split('-');
        const months = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        return months[parseInt(m) - 1] + '/' + y.slice(2);
    });
    const values = (revenueData || []).map(r => parseFloat(r.total));
    
    revenueChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['Sem dados'],
            datasets: [{
                label: 'Receita (R$)',
                data: values.length ? values : [0],
                backgroundColor: 'rgba(255, 170, 0, 0.25)',
                hoverBackgroundColor: 'rgba(255, 170, 0, 0.45)',
                borderColor: '#ffaa00',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#b0b8cc', font: { family: 'Inter', size: 11 }, callback: v => 'R$ ' + v },
                    grid: { color: 'rgba(255, 170, 0, 0.08)', strokeDash: [4, 4] }
                },
                x: {
                    ticks: { color: '#b0b8cc', font: { family: 'Inter', size: 11 } },
                    grid: { display: false }
                }
            }
        }
    });
}

function renderActivityFeed(activities) {
    const feed = document.getElementById('activity-feed');
    if (!activities || activities.length === 0) {
        feed.innerHTML = '<p style="color:var(--muted); text-align:center; padding:20px;">Nenhuma atividade registrada.</p>';
        return;
    }
    
    feed.innerHTML = activities.map(a => {
        const dotClass = a.action.includes('created') ? 'created' : 
                         a.action.includes('activated') ? 'activated' :
                         a.action.includes('expired') ? 'expired' :
                         a.action.includes('renewed') ? 'renewed' : 'revoked';
        return `
            <div class="activity-item">
                <div class="activity-dot ${dotClass}"></div>
                <div class="activity-text">
                    <strong>${a.license_key || 'Sistema'}</strong> — ${a.details || a.action}
                </div>
                <div class="activity-time">${formatDate(a.created_at)}</div>
            </div>
        `;
    }).join('');
}

// ========== HELPERS DE TELEFONE ==========
function formatPhoneInput(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 11) value = value.slice(0, 11);
    
    if (value.length > 6) {
        input.value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
    } else if (value.length > 2) {
        input.value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
    } else if (value.length > 0) {
        input.value = `(${value}`;
    } else {
        input.value = '';
    }
}

// ========== LOAD LICENSES ==========
async function loadLicenses(page = 1) {
    currentPage = page;
    const search = document.getElementById('search-input')?.value || '';
    const status = document.getElementById('status-filter')?.value || '';
    
    try {
        const params = new URLSearchParams({ action: 'list', search, status, page });
        const data = await apiFetch('api/admin/licenses.php?' + params);
        
        const tbody = document.getElementById('licenses-tbody');
        
        if (!data.licenses || data.licenses.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Nenhuma licença encontrada.</td></tr>';
            document.getElementById('pagination').innerHTML = '';
            return;
        }
        
        tbody.innerHTML = data.licenses.map(l => `
            <tr>
                <td style="font-family:'Orbitron',sans-serif; font-size:12px; color:var(--neon); letter-spacing:1px; font-weight:600;">
                    <div style="display:inline-flex; align-items:center; gap:8px;">
                        <span>${l.license_key}</span>
                        <button type="button" class="btn-sm btn-secondary" onclick="copyLicenseKey(this, '${l.license_key}')" title="Copiar Chave da Licença" style="padding:2px 7px; font-size:10px; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:4px; font-family:sans-serif; text-transform:none;">
                            📋 Copiar
                        </button>
                    </div>
                <td>
                    <strong style="color:var(--text);">${l.client_name || '<span style="color:var(--muted)">—</span>'}</strong>
                    ${l.partner_brand ? `<span class="badge" style="background:rgba(255,170,0,0.15); color:var(--neon); font-size:10px; margin-left:6px; font-weight:600;" title="Parceiro: ${l.partner_name || ''}">WL: ${escapeHtml(l.partner_brand)}</span>` : ''}
                </td>
                <td>
                    ${l.client_phone ? `
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>${l.client_phone}</span>
                            ${l.whatsapp_link ? `
                                <a href="${l.whatsapp_link}" target="_blank" class="btn-sm btn-whatsapp" title="Enviar Licença pelo WhatsApp" style="text-decoration:none; padding:3px 7px;">
                                    💬 Zap
                                </a>
                            ` : ''}
                        </div>
                    ` : '<span style="color:var(--muted)">—</span>'}
                </td>
                <td style="text-transform:capitalize; font-weight:600; color:var(--muted);">${l.plan_type}</td>
                <td><span class="badge badge-${l.status}">${l.status}</span></td>
                <td>${l.expires_at ? formatDate(l.expires_at) : '∞ (Vitalício)'}</td>
                <td style="font-size:11px; color:var(--muted); max-width:110px; overflow:hidden; text-overflow:ellipsis;" title="${l.hwid || ''}">${l.hwid || '—'}</td>
                <td style="text-align:right;">
                    <div class="action-btn-group" style="justify-content:flex-end;">
                        <button class="btn-sm" onclick="viewDetail(${l.id})" title="Ver Detalhes">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            Ver
                        </button>
                        ${l.status === 'active' ? `
                            <button class="btn-sm danger" onclick="quickAction('deactivate',${l.id})" title="Desativar Licença">
                                Desativar
                            </button>
                        ` : ''}
                        ${l.status !== 'active' && l.status !== 'revoked' ? `
                            <button class="btn-sm success" onclick="quickAction('activate',${l.id})" title="Ativar Licença">
                                Ativar
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
        
        // Pagination
        renderPagination(data.page, data.pages);
        
    } catch (err) {
        console.error('Erro ao carregar licenças:', err);
    }
}

function renderPagination(current, total) {
    const container = document.getElementById('pagination');
    if (!container) return;
    if (total <= 1) { container.innerHTML = ''; return; }
    
    let html = '';
    for (let i = 1; i <= total; i++) {
        html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="loadLicenses(${i})">${i}</button>`;
    }
    container.innerHTML = html;
}

// ========== CREATE LICENSE ==========
function openCreateModal() {
    document.getElementById('modal-create').style.display = 'flex';
    document.getElementById('create-name').value = '';
    document.getElementById('create-email').value = '';
    if (document.getElementById('create-phone')) document.getElementById('create-phone').value = '';
    document.getElementById('create-plan').value = 'manual';
    loadPartnerSelectOptions();
}

async function createLicense() {
    const name = document.getElementById('create-name').value.trim();
    const email = document.getElementById('create-email').value.trim();
    const phone = document.getElementById('create-phone')?.value.trim() || '';
    const plan = document.getElementById('create-plan').value;
    const partnerId = document.getElementById('create-partner')?.value || null;
    
    try {
        const data = await apiFetch('api/admin/licenses.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ client_name: name, client_email: email, client_phone: phone, plan_type: plan, partner_id: partnerId })
        });
        
        if (data.success) {
            closeModal('modal-create');
            document.getElementById('generated-key').textContent = data.license.key;
            document.getElementById('key-expiry').textContent = 'Expira em: ' + (data.license.expires_at ? formatDate(data.license.expires_at) : 'Vitalício');
            
            const waContainer = document.getElementById('wa-btn-container');
            if (waContainer) {
                if (data.license.whatsapp_link) {
                    waContainer.innerHTML = `
                        <a href="${data.license.whatsapp_link}" target="_blank" class="btn-primary btn-whatsapp" style="width:100%; justify-content:center; text-decoration:none; padding:12px;">
                            💬 Enviar Licença via WhatsApp
                        </a>
                    `;
                } else {
                    waContainer.innerHTML = '';
                }
            }
            
            document.getElementById('modal-key').style.display = 'flex';
            showToast('✅ Licença criada com sucesso!');
            if (typeof loadStats === 'function') loadStats();
            loadLicenses(1);
        } else {
            showToast('Erro: ' + (data.error || 'Falha ao criar licença'), 'error');
        }
    } catch (err) {
        showToast('Erro de conexão ao criar licença.', 'error');
    }
}

function copyKey() {
    const key = document.getElementById('generated-key').textContent;
    navigator.clipboard.writeText(key).then(() => {
        showToast('📋 Chave da licença copiada para a área de transferência!');
    }).catch(() => {
        showToast('Erro ao copiar chave.', 'error');
    });
}

// ========== VIEW DETAIL ==========
async function viewDetail(id) {
    document.getElementById('modal-detail').style.display = 'flex';
    document.getElementById('detail-content').innerHTML = 'Carregando detalhes...';
    
    try {
        const data = await apiFetch('api/admin/licenses.php?action=detail&id=' + id);
        
        if (!data.success) { document.getElementById('detail-content').innerHTML = 'Erro ao carregar detalhes'; return; }
        
        const l = data.license;
        document.getElementById('detail-content').innerHTML = `
            <div class="detail-grid">
                <div class="detail-item">
            <label>Chave da Licença</label>
            <div style="display:flex; align-items:center; gap:10px; margin-top:4px;">
                <span style="font-family:'Orbitron',sans-serif; color:var(--neon); font-size:13px; font-weight:700;">${l.license_key}</span>
                <button type="button" class="btn-sm btn-secondary" onclick="copyLicenseKey(this, '${l.license_key}')" style="padding:3px 8px; font-size:11px; border-radius:6px; cursor:pointer;">📋 Copiar Licença</button>
            </div>
        </div>
                <div class="detail-item"><label>Status Atual</label><span><span class="badge badge-${l.status}">${l.status}</span></span></div>
                <div class="detail-item"><label>Nome do Cliente</label><span>${l.client_name || '—'}</span></div>
                <div class="detail-item"><label>E-mail</label><span>${l.client_email || '—'}</span></div>
                <div class="detail-item"><label>WhatsApp / Telefone</label><span>${l.client_phone || '—'}</span></div>
                <div class="detail-item"><label>Plano Contratado</label><span style="text-transform:capitalize;">${l.plan_type}</span></div>
                <div class="detail-item"><label>Validade / Expiração</label><span>${l.expires_at ? formatDate(l.expires_at) : '∞ (Vitalício)'}</span></div>
                <div class="detail-item"><label>HWID Registrado</label><span style="font-size:11px; word-break:break-all; color:var(--muted);">${l.hwid || 'Nenhum dispositivo vinculado'}</span></div>
                <div class="detail-item"><label>Último Endereço IP</label><span>${l.last_ip || '—'}</span></div>
                <div class="detail-item"><label>Data de Criação</label><span>${formatDate(l.created_at)}</span></div>
            </div>
            ${data.logs && data.logs.length > 0 ? `
                <h4 style="margin-top:25px; margin-bottom:12px; font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:1px;">Histórico de Atividades</h4>
                <div style="max-height:160px; overflow-y:auto; background:rgba(0,0,0,0.3); border:1px solid rgba(255,170,0,0.1); border-radius:10px; padding:10px 14px;">
                    ${data.logs.map(log => `
                        <div style="padding:8px 0; border-bottom:1px solid rgba(255,170,0,0.05); font-size:12px; display:flex; justify-content:space-between; gap:10px;">
                            <span style="color:var(--text);">${log.details || log.action}</span>
                            <span style="color:var(--muted); font-size:11px; flex-shrink:0;">${formatDate(log.created_at)}</span>
                        </div>
                    `).join('')}
                </div>
            ` : ''}
        `;
        
        document.getElementById('detail-actions').innerHTML = `
            ${l.whatsapp_link ? `<a href="${l.whatsapp_link}" target="_blank" class="btn-sm btn-whatsapp" style="text-decoration:none;">📲 WhatsApp</a>` : ''}
            <button class="btn-sm" onclick="quickAction('reset_hwid',${l.id})">Reset HWID</button>
            <button class="btn-sm success" onclick="quickAction('renew',${l.id})">Renovar</button>
            ${l.status !== 'revoked' ? `<button class="btn-sm danger" onclick="quickAction('revoke',${l.id})">Revogar</button>` : ''}
            <button class="btn-sm danger" onclick="if(confirm('Excluir permanentemente?')) quickAction('delete',${l.id})" style="border-color:rgba(255,51,51,0.5);">Excluir</button>
        `;
        
    } catch (err) {
        document.getElementById('detail-content').innerHTML = 'Erro de conexão';
    }
}

// ========== QUICK ACTIONS ==========
async function quickAction(action, id) {
    try {
        const data = await apiFetch('api/admin/licenses.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        if (data.success) {
            closeModal('modal-detail');
            showToast('✅ Ação realizada com sucesso!');
            loadLicenses(currentPage);
            if (typeof loadStats === 'function') loadStats();
        } else {
            showToast('Erro: ' + (data.error || 'Falha na ação'), 'error');
        }
    } catch (err) {
        showToast('Erro de conexão ao realizar ação.', 'error');
    }
}

// ========== EXPORT CSV ==========
async function exportCSV() {
    try {
        const data = await apiFetch('api/admin/licenses.php?action=list&page=1');
        
        if (!data.licenses) return;
        
        let csv = 'Chave,Cliente,Email,Plano,Status,Expira Em,HWID,Criada Em\n';
        data.licenses.forEach(l => {
            csv += `"${l.license_key}","${l.client_name || ''}","${l.client_email || ''}","${l.plan_type}","${l.status}","${l.expires_at || ''}","${l.hwid || ''}","${l.created_at}"\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'platafy_licencas_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    } catch (err) {
        alert('Erro ao exportar');
    }
}

// ========== HELPERS ==========
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.style.display = 'none';
    });
});

// ========== ATUALIZAÇÕES & BACKUPS ==========
async function loadUpdatesInfo() {
    try {
        const data = await apiFetch('api/admin/updates.php?action=info');
        
        if (!data.success) return;
        
        const ext = data.version?.extension || {};
        const currentVerInput = document.getElementById('update-current-ver');
        if (currentVerInput) {
            currentVerInput.value = ext.latest_version || '1.0.0';
        }
        
        const urlInput = document.getElementById('update-download-url');
        if (urlInput && !urlInput.value) {
            urlInput.value = ext.download_url || '';
        }
        
        const changelogInput = document.getElementById('update-changelog');
        if (changelogInput && !changelogInput.value) {
            changelogInput.value = ext.changelog || '';
        }
        
        const mandatoryInput = document.getElementById('update-mandatory');
        if (mandatoryInput) {
            mandatoryInput.checked = !!ext.mandatory;
        }
        
        // Renderizar lista de backups
        const container = document.getElementById('backups-list-container');
        if (!container) return;
        
        if (!data.backups || data.backups.length === 0) {
            container.innerHTML = '<p style="color:var(--muted); font-size:12px; padding:12px; text-align:center;">Nenhum backup encontrado no servidor.</p>';
            return;
        }
        
        container.innerHTML = data.backups.map(b => {
            const sizeKB = (b.size / 1024).toFixed(1);
            const sizeStr = b.size > 1048576 ? (b.size / 1048576).toFixed(2) + ' MB' : sizeKB + ' KB';
            
            return `
                <div class="backup-item">
                    <div class="backup-item-info">
                        <div class="backup-item-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        </div>
                        <div>
                            <span class="backup-item-name">${b.filename}</span>
                            <div class="backup-item-meta">${formatDate(b.created_at)} • ${sizeStr}</div>
                        </div>
                    </div>
                    <a href="/backups/${b.filename}" download class="btn-sm success" style="text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Download
                    </a>
                </div>
            `;
        }).join('');
        
    } catch (err) {
        console.error('Erro ao carregar informações de atualização:', err);
    }
}

async function publishExtensionUpdate() {
    const newVer = document.getElementById('update-new-ver').value.trim();
    const downloadUrl = document.getElementById('update-download-url').value.trim();
    const changelog = document.getElementById('update-changelog').value.trim();
    const mandatory = document.getElementById('update-mandatory').checked;
    
    if (!newVer) {
        showToast('Por favor, informe a nova versão (ex: 1.0.1).', 'error');
        return;
    }
    
    try {
        const data = await apiFetch('api/admin/updates.php?action=publish', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                latest_version: newVer,
                download_url: downloadUrl,
                changelog: changelog,
                mandatory: mandatory
            })
        });
        
        if (data.success) {
            showToast('🚀 Nova versão v' + newVer + ' publicada com sucesso!');
            loadUpdatesInfo();
            document.getElementById('update-new-ver').value = '';
        } else {
            showToast('Erro: ' + (data.error || 'Falha ao publicar'), 'error');
        }
    } catch (err) {
        showToast('Erro de conexão ao publicar atualização.', 'error');
    }
}

async function createManualBackup() {
    try {
        const btn = event.currentTarget || event.target;
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg> Gerando Backup...`;
        
        const data = await apiFetch('api/admin/updates.php?action=create_backup', { method: 'POST' });
        
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        
        if (data.success) {
            showToast('✅ Backup gerado com sucesso: ' + data.filename);
            loadUpdatesInfo();
        } else {
            showToast('Erro ao gerar backup: ' + (data.error || 'Falha'), 'error');
        }
    } catch (err) {
        showToast('Erro de conexão ao gerar backup.', 'error');
    }
}

// Helper: Copiar comando Cron Job
function copyCronCommand() {
    const textEl = document.getElementById('cron-cmd-text');
    if (!textEl) return;
    
    const textToCopy = textEl.textContent.trim();
    navigator.clipboard.writeText(textToCopy).then(() => {
        showToast('📋 Comando Cron copiado para a área de transferência!');
    }).catch(() => {
        showToast('Erro ao copiar comando.', 'error');
    });
}


// Helper: Requisição centralizada de API com autenticação e tratamento de sessão
async function apiFetch(url, options = {}) {
    options.credentials = options.credentials || 'same-origin';
    
    let cleanUrl = url;
    if (cleanUrl.startsWith('/')) {
        cleanUrl = cleanUrl.substring(1);
    }

    try {
        const res = await fetch(cleanUrl, options);
        if (res.status === 401) {
            showToast('🔒 Sua sessão expirou ou não está autorizada. Redirecionando para o login...', 'error');
            setTimeout(() => { window.location.href = 'index.php'; }, 1500);
            throw new Error('Não autorizado');
        }
        
        const data = await res.json();
        if (data.error && (data.error.includes('Não autorizado') || data.auth_required)) {
            showToast('🔒 Sua sessão expirou ou não está autorizada. Redirecionando para o login...', 'error');
            setTimeout(() => { window.location.href = 'index.php'; }, 1500);
            throw new Error('Não autorizado');
        }
        return data;
    } catch (err) {
        if (err.message === 'Não autorizado') throw err;
        console.error('Erro na requisição API:', err);
        throw err;
    }
}

// ========== CONFIGURAÇÕES DO SISTEMA ==========
async function loadSettingsInfo() {
    try {
        const data = await apiFetch('api/admin/settings.php?action=get_settings');
        if (!data.success) return;
        
        const s = data.settings || {};
        document.getElementById('setting-mp-token').value = s.mp_access_token || '';
        document.getElementById('setting-mp-token-test').value = s.mp_access_token_test || '';
        document.getElementById('setting-mp-public-key').value = s.mp_public_key || '';
        document.getElementById('setting-mp-use-test').checked = !!s.mp_use_test;
        
        // Preview da Logo
        const previewBox = document.getElementById('logo-preview-box');
        if (s.site_logo) {
            previewBox.innerHTML = `<img src="${s.site_logo}" id="settings-logo-preview" style="max-height:60px; max-width:200px;" alt="Logo">`;
        }
        const favPreviewBox = document.getElementById('favicon-preview-box');
        if (favPreviewBox && s.site_favicon) {
            favPreviewBox.innerHTML = `<img src="${s.site_favicon}?t=${Date.now()}" id="settings-favicon-preview" style="max-height:36px; max-width:36px; object-fit:contain;" alt="Favicon">`;
            let favLink = document.querySelector('link[rel="icon"]');
            if (favLink) favLink.href = s.site_favicon;
        } else {
            previewBox.innerHTML = `<span id="settings-logo-preview-text" style="font-family:'Orbitron',sans-serif; color:var(--neon); font-size:20px;">PLATAFY</span>`;
        }
        
    } catch (err) {
        if (err.message !== 'Não autorizado') {
            console.error('Erro ao carregar configurações:', err);
        }
    }
}

async function saveMercadoPagoSettings() {
    const tokenProd = document.getElementById('setting-mp-token').value.trim();
    const tokenTest = document.getElementById('setting-mp-token-test').value.trim();
    const publicKey = document.getElementById('setting-mp-public-key').value.trim();
    const useTest   = document.getElementById('setting-mp-use-test').checked;
    
    try {
        const data = await apiFetch('api/admin/settings.php?action=save_mercadopago', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mp_access_token: tokenProd,
                mp_access_token_test: tokenTest,
                mp_public_key: publicKey,
                mp_use_test: useTest
            })
        });
        
        if (data.success) {
            showToast('✅ Credenciais do Mercado Pago salvas com sucesso!');
        } else {
            showToast('Erro: ' + (data.error || 'Falha ao salvar'), 'error');
        }
    } catch (err) {
        if (err.message !== 'Não autorizado') {
            showToast('Erro de conexão ao salvar credenciais.', 'error');
        }
    }
}

async function changeAdminPassword() {
    const currentPass = document.getElementById('pass-current').value;
    const newPass     = document.getElementById('pass-new').value;
    const confirmPass = document.getElementById('pass-confirm').value;
    
    if (!currentPass || !newPass) {
        showToast('Preencha a senha atual e a nova senha.', 'error');
        return;
    }
    
    if (newPass.length < 6) {
        showToast('A nova senha deve ter no mínimo 6 caracteres.', 'error');
        return;
    }
    
    if (newPass !== confirmPass) {
        showToast('A nova senha e a confirmação não conferem.', 'error');
        return;
    }
    
    try {
        const data = await apiFetch('api/admin/settings.php?action=change_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: currentPass,
                new_password: newPass,
                confirm_password: confirmPass
            })
        });
        
        if (data.success) {
            showToast('🔒 Senha alterada com sucesso!');
            document.getElementById('pass-current').value = '';
            document.getElementById('pass-new').value = '';
            document.getElementById('pass-confirm').value = '';
        } else {
            showToast('Erro: ' + (data.error || 'Falha ao alterar senha'), 'error');
        }
    } catch (err) {
        if (err.message !== 'Não autorizado') {
            showToast('Erro de conexão ao alterar senha.', 'error');
        }
    }
}

async function uploadSystemLogo() {
    const fileInput = document.getElementById('logo-file-input');
    if (!fileInput) return;
    if (!fileInput.files || fileInput.files.length === 0) {
        fileInput.click();
        return;
    }
    
    const formData = new FormData();
    formData.append('logo', fileInput.files[0]);
    
    try {
        const data = await apiFetch('api/admin/settings.php?action=upload_logo', {
            method: 'POST',
            body: formData
        });
        
        if (data.success) {
            showToast('🖼️ Logomarca atualizada com sucesso!');
            
            // Atualizar preview nas configurações
            const previewBox = document.getElementById('logo-preview-box');
            if (previewBox) {
                previewBox.innerHTML = `<img src="${data.logo_url}?t=${Date.now()}" id="settings-logo-preview" style="max-height:55px; max-width:170px;" alt="Logo">`;
            }
            
            // Atualizar logo na Navbar
            const navBrand = document.querySelector('.navbar-brand');
            if (navBrand) {
                navBrand.innerHTML = `<img src="${data.logo_url}?t=${Date.now()}" id="site-logo-img" alt="PLATAFY" style="max-height:38px; vertical-align:middle;">`;
            }
            
            fileInput.value = '';
            const fileNameDisplay = document.getElementById('logo-file-name');
            if (fileNameDisplay) {
                fileNameDisplay.style.display = 'none';
                fileNameDisplay.textContent = '';
            }
        } else {
            showToast('Erro: ' + (data.error || 'Falha ao enviar imagem'), 'error');
        }
    } catch (err) {
        if (err.message !== 'Não autorizado') {
            showToast('Erro de conexão ao enviar logomarca.', 'error');
        }
    }
}

// Helper: Toast Notifications
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast-msg ${type === 'error' ? 'error' : ''}`;
    
    const icon = type === 'error' 
        ? `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`
        : `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`;
        
    toast.innerHTML = `${icon} <span>${message}</span>`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Helper: Toggle Password Visibility
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    
    btn.innerHTML = isPassword
        ? `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`
        : `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
}

// Helper: Handle File Selection Display
function handleFileSelect(input) {
    const nameDisplay = document.getElementById('logo-file-name');
    const btn = document.querySelector('.btn-save-custom');
    if (!input.files || input.files.length === 0) {
        if (nameDisplay) {
            nameDisplay.style.display = 'none';
            nameDisplay.textContent = '';
        }
        if (btn) btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg> Fazer Upload e Aplicar Nova Logomarca';
        return;
    }
    
    const file = input.files[0];
    if (nameDisplay) {
        nameDisplay.textContent = '🖼️ Arquivo selecionado: ' + file.name;
        nameDisplay.style.display = 'inline-block';
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const previewBox = document.getElementById('logo-preview-box');
        if (previewBox) {
            previewBox.innerHTML = `<img src="${e.target.result}" style="max-height:55px; max-width:170px; object-fit:contain;" alt="Preview Logo">`;
        }
    };
    reader.readAsDataURL(file);

    if (btn) {
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Salvar e Aplicar Logomarca (${file.name})`;
    }
}

// Drag & Drop Setup
document.addEventListener('DOMContentLoaded', () => {
    const dropzone = document.getElementById('logo-dropzone');
    const fileInput = document.getElementById('logo-file-input');
    
    if (dropzone && fileInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
        });
        
        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                fileInput.files = files;
                handleFileSelect(fileInput);
            }
        }, false);
    }
});

// ========== PWA REGISTRATION & INSTALLATION ==========
let deferredPwaPrompt = null;

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('Service Worker registrado com sucesso:', reg.scope))
            .catch(err => console.error('Falha ao registrar Service Worker:', err));
    });
}

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPwaPrompt = e;
    const installBtn = document.getElementById('pwa-install-btn');
    if (installBtn) {
        installBtn.style.display = 'inline-flex';
    }
});

async function installPWA() {
    if (!deferredPwaPrompt) return;
    deferredPwaPrompt.prompt();
    const { outcome } = await deferredPwaPrompt.userChoice;
    if (outcome === 'accepted') {
        showToast('🎉 App instalado com sucesso na tela inicial!');
    }
    deferredPwaPrompt = null;
    const installBtn = document.getElementById('pwa-install-btn');
    if (installBtn) installBtn.style.display = 'none';
}



function copyLicenseKey(btn, key) {
    if (!key) return;
    const originalHTML = btn.innerHTML;
    
    function showSuccess() {
        btn.innerHTML = '✅ Copiado!';
        btn.style.borderColor = '#22c55e';
        btn.style.color = '#4ade80';
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2000);
    }
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(key).then(showSuccess).catch(() => fallbackCopy(key, btn, originalHTML));
    } else {
        fallbackCopy(key, btn, originalHTML);
    }
}

function fallbackCopy(key, btn, originalHTML) {
    const input = document.createElement('input');
    input.value = key;
    document.body.appendChild(input);
    input.select();
    try {
        document.execCommand('copy');
        btn.innerHTML = '✅ Copiado!';
        btn.style.borderColor = '#22c55e';
        btn.style.color = '#4ade80';
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2000);
    } catch(e) {}
    document.body.removeChild(input);
}

// ========== UPLOAD DE FAVICON DO SISTEMA ==========
function triggerFaviconUploadOrPicker() {
    const fileInput = document.getElementById('faviconFileInput');
    if (!fileInput) return;
    if (!fileInput.files || fileInput.files.length === 0) {
        fileInput.click();
        return;
    }
    handleFaviconUpload();
}

function handleFaviconSelect(input) {
    const nameDisplay = document.getElementById('favicon-file-name');
    const btn = document.getElementById('btnUploadFavicon');
    if (!input.files || input.files.length === 0) {
        if (nameDisplay) {
            nameDisplay.style.display = 'none';
            nameDisplay.textContent = '';
        }
        if (btn) btn.innerHTML = '📌 Fazer Upload e Aplicar Novo Favicon';
        return;
    }

    const file = input.files[0];
    if (nameDisplay) {
        nameDisplay.textContent = '📌 Arquivo selecionado: ' + file.name;
        nameDisplay.style.display = 'inline-block';
    }

    // Preview instantâneo
    const reader = new FileReader();
    reader.onload = function(e) {
        const previewBox = document.getElementById('favicon-preview-box');
        if (previewBox) {
            previewBox.innerHTML = `<img src="${e.target.result}" style="max-height:36px; max-width:36px; object-fit:contain; border-radius:4px;" alt="Preview Favicon">`;
        }
    };
    reader.readAsDataURL(file);

    if (btn) {
        btn.innerHTML = `🚀 Salvar e Aplicar Favicon (${file.name})`;
    }
}

async function handleFaviconUpload() {
    const fileInput = document.getElementById('faviconFileInput');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        fileInput?.click();
        return;
    }

    const btn = document.getElementById('btnUploadFavicon');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ Enviando Favicon...';
    }

    const formData = new FormData();
    formData.append('favicon', fileInput.files[0]);

    try {
        const data = await apiFetch('api/admin/settings.php?action=upload_favicon', {
            method: 'POST',
            body: formData
        });

        if (data && data.success) {
            showToast('📌 Favicon atualizado com sucesso!');
            const favPreviewBox = document.getElementById('favicon-preview-box');
            if (favPreviewBox) {
                favPreviewBox.innerHTML = `<img src="${data.favicon_url}?t=${Date.now()}" id="settings-favicon-preview" style="max-height:36px; max-width:36px; object-fit:contain;" alt="Favicon">`;
            }
            // Atualizar favicon nas abas dinamicamente
            let favLink = document.querySelector('link[rel="icon"]');
            if (!favLink) {
                favLink = document.createElement('link');
                favLink.rel = 'icon';
                favLink.type = 'image/png';
                document.head.appendChild(favLink);
            }
            favLink.href = data.favicon_url + '?t=' + Date.now();

            fileInput.value = '';
            const nameDisplay = document.getElementById('favicon-file-name');
            if (nameDisplay) {
                nameDisplay.style.display = 'none';
                nameDisplay.textContent = '';
            }
            if (btn) {
                btn.innerHTML = '📌 Fazer Upload e Aplicar Novo Favicon';
            }
        } else {
            showToast(data.error || 'Erro ao enviar favicon.', 'error');
        }
    } catch (err) {
        showToast('Erro de conexão ao enviar favicon.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
        }
    }
}

function setupFaviconDropzone() {
    const dropzone = document.getElementById('faviconDropzone');
    const fileInput = document.getElementById('faviconFileInput');
    if (!dropzone || !fileInput) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.style.borderColor = 'var(--neon)';
            dropzone.style.background = 'rgba(255, 170, 0, 0.08)';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.style.borderColor = 'var(--border)';
            dropzone.style.background = 'rgba(255, 255, 255, 0.02)';
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            handleFaviconSelect(fileInput);
            showToast('📌 Imagem de Favicon selecionada. Clique em Salvar e Aplicar.', 'info');
        }
    }, false);

    const btnUpload = document.getElementById('btnUploadFavicon');
    if (btnUpload) {
        btnUpload.onclick = triggerFaviconUploadOrPicker;
    }
}

// ============================================================
// WHITE LABEL PARTNERS MANAGEMENT
// ============================================================
let partnerCurrentPage = 1;
let partnerSearchTimeout = null;
let cachedPartnersList = [];

function debouncePartnerSearch() {
    clearTimeout(partnerSearchTimeout);
    partnerSearchTimeout = setTimeout(() => {
        loadPartners(1);
    }, 300);
}

async function loadPartners(page = 1) {
    partnerCurrentPage = page;
    const search = document.getElementById('partner-search-input')?.value || '';
    const status = document.getElementById('partner-filter-status')?.value || '';

    try {
        const params = new URLSearchParams({ action: 'list', search, status, page });
        const data = await apiFetch('api/admin/partners.php?' + params);

        if (!data.success) {
            showToast('Erro ao carregar parceiros: ' + (data.error || 'Falha de conexão'), 'error');
            return;
        }

        cachedPartnersList = data.partners || [];

        let totalLicensesCount = 0;
        let totalQuotaCount = 0;
        let activePartnersCount = 0;

        cachedPartnersList.forEach(p => {
            totalLicensesCount += parseInt(p.total_licenses || 0);
            totalQuotaCount += parseInt(p.max_licenses || 0);
            if (p.status === 'active' && !p.is_expired) activePartnersCount++;
        });

        const elTotal = document.getElementById('stat-partner-total');
        if (elTotal) elTotal.textContent = data.total || 0;
        const elActive = document.getElementById('stat-partner-active');
        if (elActive) elActive.textContent = activePartnersCount;
        const elLic = document.getElementById('stat-partner-licenses');
        if (elLic) elLic.textContent = totalLicensesCount;
        const elQuota = document.getElementById('stat-partner-quota');
        if (elQuota) elQuota.textContent = totalQuotaCount;

        const tbody = document.getElementById('partners-tbody');
        if (!tbody) return;

        if (!data.partners || data.partners.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-state" style="text-align:center; padding:30px; color:var(--muted);">Nenhum parceiro cadastrado ainda. Clique em "Novo Parceiro" acima.</td></tr>';
            const pagEl = document.getElementById('partner-pagination');
            if (pagEl) pagEl.style.display = 'none';
            return;
        }

        tbody.innerHTML = data.partners.map(p => {
            const used = parseInt(p.total_licenses || 0);
            const maxL = parseInt(p.max_licenses || 50);
            const usagePercent = Math.min(100, Math.round((used / maxL) * 100));
            const isNearFull = usagePercent >= 90;

            const statusClass = p.is_expired ? 'expired' : p.status;
            const statusLabel = p.is_expired ? 'Expirado' : (p.status === 'active' ? 'Ativo' : (p.status === 'suspended' ? 'Suspenso' : 'Inativo'));

            const logoHtml = p.brand_logo_url 
                ? `<img src="${escapeHtml(p.brand_logo_url)}" alt="${escapeHtml(p.brand_name)}" style="max-height:28px; max-width:80px; vertical-align:middle; border-radius:4px; margin-right:8px; object-fit:contain;">` 
                : `<span style="display:inline-flex; width:28px; height:28px; background:rgba(255,170,0,0.15); border-radius:6px; align-items:center; justify-content:center; color:var(--neon); font-size:12px; font-weight:700; margin-right:8px;">WL</span>`;

            return `
                <tr>
                    <td>
                        <div style="display:flex; align-items:center;">
                            ${logoHtml}
                            <strong style="color:var(--text); font-size:13px;">${escapeHtml(p.brand_name)}</strong>
                        </div>
                    </td>
                    <td>
                        <strong style="color:var(--text);">${escapeHtml(p.partner_name)}</strong>
                        ${p.support_whatsapp ? `<br><small style="color:var(--muted); font-size:11px;">📱 ${escapeHtml(p.support_whatsapp)}</small>` : ''}
                    </td>
                    <td><code style="color:var(--neon); background:rgba(255,170,0,0.08); padding:2px 6px; border-radius:4px;">@${escapeHtml(p.username)}</code></td>
                    <td><span style="font-weight:600; color:var(--muted);">${escapeHtml(p.plan_name || 'White Label Pro')}</span></td>
                    <td style="min-width:140px;">
                        <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:4px;">
                            <span>${used} de ${maxL}</span>
                            <span style="color:${isNearFull ? 'var(--danger, #ef4444)' : 'var(--neon)'}">${usagePercent}%</span>
                        </div>
                        <div style="height:6px; background:rgba(255,255,255,0.08); border-radius:3px; overflow:hidden;">
                            <div style="height:100%; width:${usagePercent}%; background:${isNearFull ? '#ef4444' : 'var(--neon)'}; border-radius:3px;"></div>
                        </div>
                    </td>
                    <td><span class="badge badge-${statusClass}">${statusLabel}</span></td>
                    <td>${p.expires_at ? formatDate(p.expires_at) : '∞ Sem expiração'}</td>
                    <td style="text-align:right;">
                        <div class="action-btn-group" style="justify-content:flex-end;">
                            <button class="btn-sm" onclick="filterLicensesByPartner(${p.id}, '${escapeHtml(p.brand_name)}')" title="Ver Licenças deste Parceiro">
                                Licenças (${used})
                            </button>
                            <button class="btn-sm" onclick="openEditPartnerModal(${p.id})" title="Editar Parceiro">
                                ✏️
                            </button>
                            <button class="btn-sm" onclick="openResetPartnerPassModal(${p.id}, '${escapeHtml(p.partner_name)}')" title="Redefinir Senha">
                                🔑
                            </button>
                            ${p.status === 'active' ? `
                                <button class="btn-sm danger" onclick="togglePartnerStatus(${p.id}, 'suspended')" title="Suspender Parceiro">
                                    Suspender
                                </button>
                            ` : `
                                <button class="btn-sm success" onclick="togglePartnerStatus(${p.id}, 'active')" title="Ativar Parceiro">
                                    Ativar
                                </button>
                            `}
                            <button class="btn-sm danger" onclick="deletePartner(${p.id})" title="Excluir Parceiro">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        renderPartnerPagination(data.page, data.pages);
    } catch (err) {
        console.error('loadPartners error:', err);
    }
}

function renderPartnerPagination(current, total) {
    const container = document.getElementById('partner-pagination');
    if (!container) return;
    if (total <= 1) { container.style.display = 'none'; return; }

    let html = '';
    for (let i = 1; i <= total; i++) {
        html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="loadPartners(${i})">${i}</button>`;
    }
    container.innerHTML = html;
    container.style.display = 'flex';
}

function openPartnerModal(partner = null) {
    const modal = document.getElementById('modal-partner');
    if (!modal) return;

    document.getElementById('partner-id').value = partner?.id || '';
    document.getElementById('partner-name').value = partner?.partner_name || '';
    document.getElementById('partner-username').value = partner?.username || '';
    document.getElementById('partner-username').readOnly = !!partner;
    document.getElementById('partner-brand-name').value = partner?.brand_name || '';
    document.getElementById('partner-logo-url').value = partner?.brand_logo_url || '';
    document.getElementById('partner-whatsapp').value = partner?.support_whatsapp || '';
    document.getElementById('partner-url').value = partner?.support_url || '';
    document.getElementById('partner-plan-name').value = partner?.plan_name || 'White Label Pro';
    document.getElementById('partner-max-licenses').value = partner?.max_licenses || 50;
    document.getElementById('partner-status').value = partner?.status || 'active';
    document.getElementById('partner-notes').value = partner?.notes || '';

    if (partner?.expires_at) {
        document.getElementById('partner-expires-at').value = partner.expires_at.split(' ')[0];
    } else {
        document.getElementById('partner-expires-at').value = '';
    }

    const passGroup = document.getElementById('group-partner-password');
    if (passGroup) {
        passGroup.style.display = partner ? 'none' : 'block';
    }
    if (document.getElementById('partner-password')) {
        document.getElementById('partner-password').value = '';
    }

    const titleEl = document.getElementById('partner-modal-title');
    if (titleEl) {
        titleEl.textContent = partner ? 'Editar Parceiro White Label' : 'Novo Parceiro White Label';
    }

    modal.style.display = 'flex';
}

function openEditPartnerModal(id) {
    const partner = cachedPartnersList.find(p => p.id == id);
    if (partner) {
        openPartnerModal(partner);
    }
}

async function savePartner() {
    const id = document.getElementById('partner-id').value;
    const partnerName = document.getElementById('partner-name').value.trim();
    const username = document.getElementById('partner-username').value.trim();
    const password = document.getElementById('partner-password')?.value.trim();
    const brandName = document.getElementById('partner-brand-name').value.trim();
    const brandLogoUrl = document.getElementById('partner-logo-url').value.trim();
    const supportWhatsapp = document.getElementById('partner-whatsapp').value.trim();
    const supportUrl = document.getElementById('partner-url').value.trim();
    const planName = document.getElementById('partner-plan-name').value.trim();
    const maxLicenses = parseInt(document.getElementById('partner-max-licenses').value) || 50;
    const expiresAt = document.getElementById('partner-expires-at').value;
    const status = document.getElementById('partner-status').value;
    const notes = document.getElementById('partner-notes').value.trim();

    if (!partnerName || !username || (!id && !password) || !brandName) {
        showToast('Preencha os campos obrigatórios (*).', 'error');
        return;
    }

    const payload = {
        id,
        partner_name: partnerName,
        username,
        brand_name: brandName,
        brand_logo_url: brandLogoUrl,
        support_whatsapp: supportWhatsapp,
        support_url: supportUrl,
        plan_name: planName,
        max_licenses: maxLicenses,
        expires_at: expiresAt || null,
        status,
        notes
    };
    if (!id && password) {
        payload.password = password;
    }

    const action = id ? 'update' : 'create';

    try {
        const res = await apiFetch(`api/admin/partners.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (res.success) {
            closeModal('modal-partner');
            showToast(res.message || 'Parceiro salvo com sucesso!');
            loadPartners(partnerCurrentPage);
            loadPartnerSelectOptions();
        } else {
            showToast('Erro: ' + (res.error || 'Falha ao salvar parceiro'), 'error');
        }
    } catch (e) {
        showToast('Erro de conexão ao salvar parceiro', 'error');
    }
}

async function togglePartnerStatus(id, newStatus) {
    const actionLabel = newStatus === 'active' ? 'ativar' : 'suspender';
    if (!confirm(`Deseja realmente ${actionLabel} este parceiro?`)) return;

    try {
        const res = await apiFetch('api/admin/partners.php?action=toggle_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status: newStatus })
        });

        if (res.success) {
            showToast(`Parceiro ${newStatus === 'active' ? 'ativado' : 'suspenso'} com sucesso!`);
            loadPartners(partnerCurrentPage);
        } else {
            showToast('Erro: ' + (res.error || 'Falha ao alterar status'), 'error');
        }
    } catch (e) {
        showToast('Erro ao atualizar status', 'error');
    }
}

function openResetPartnerPassModal(id, name) {
    document.getElementById('partner-pass-id').value = id;
    document.getElementById('partner-pass-name').textContent = `Defina uma nova senha para: ${name}`;
    document.getElementById('partner-pass-new').value = '';
    document.getElementById('modal-partner-pass').style.display = 'flex';
}

async function confirmResetPartnerPassword() {
    const id = document.getElementById('partner-pass-id').value;
    const password = document.getElementById('partner-pass-new').value.trim();

    if (!password || password.length < 4) {
        showToast('A senha deve ter pelo menos 4 caracteres.', 'error');
        return;
    }

    try {
        const res = await apiFetch('api/admin/partners.php?action=reset_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, password })
        });

        if (res.success) {
            closeModal('modal-partner-pass');
            showToast(res.message || 'Senha redefinida com sucesso!');
        } else {
            showToast('Erro: ' + (res.error || 'Falha ao redefinir senha'), 'error');
        }
    } catch (e) {
        showToast('Erro ao redefinir senha', 'error');
    }
}

async function deletePartner(id) {
    if (!confirm('Deseja realmente excluir este parceiro? Suas licenças serão desvinculadas mas continuarão no sistema.')) return;

    try {
        const res = await apiFetch('api/admin/partners.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        if (res.success) {
            showToast(res.message || 'Parceiro excluído com sucesso!');
            loadPartners(1);
            loadPartnerSelectOptions();
        } else {
            showToast('Erro: ' + (res.error || 'Falha ao excluir'), 'error');
        }
    } catch (e) {
        showToast('Erro ao excluir parceiro', 'error');
    }
}

function filterLicensesByPartner(partnerId, brandName) {
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    const licLink = document.querySelector('.nav-link[data-view="licenses"]');
    if (licLink) licLink.classList.add('active');

    document.querySelectorAll('.main-content').forEach(v => v.style.display = 'none');
    document.getElementById('view-licenses').style.display = 'block';

    const searchInput = document.getElementById('search-input');
    if (searchInput) searchInput.value = '';

    loadLicensesWithPartnerFilter(partnerId, brandName);
}

async function loadLicensesWithPartnerFilter(partnerId, brandName, page = 1) {
    currentPage = page;
    try {
        const params = new URLSearchParams({ action: 'list', partner_id: partnerId, page });
        const data = await apiFetch('api/admin/licenses.php?' + params);

        const tbody = document.getElementById('licenses-tbody');
        if (!tbody) return;

        if (!data.licenses || data.licenses.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="empty-state">Nenhuma licença encontrada para o parceiro ${brandName}.</td></tr>`;
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        tbody.innerHTML = data.licenses.map(l => `
            <tr>
                <td style="font-family:'Orbitron',sans-serif; font-size:12px; color:var(--neon); letter-spacing:1px; font-weight:600;">
                    <div style="display:inline-flex; align-items:center; gap:8px;">
                        <span>${l.license_key}</span>
                        <button type="button" class="btn-sm btn-secondary" onclick="copyLicenseKey(this, '${l.license_key}')" title="Copiar Chave da Licença" style="padding:2px 7px; font-size:10px; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:4px; font-family:sans-serif; text-transform:none;">
                            📋 Copiar
                        </button>
                    </div>
                </td>
                <td>
                    <strong style="color:var(--text);">${l.client_name || '<span style="color:var(--muted)">—</span>'}</strong>
                    <span class="badge" style="background:rgba(255,170,0,0.15); color:var(--neon); font-size:10px; margin-left:6px; font-weight:600;">WL: ${escapeHtml(l.partner_brand || brandName)}</span>
                </td>
                <td>
                    ${l.client_phone ? `
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>${l.client_phone}</span>
                            ${l.whatsapp_link ? `
                                <a href="${l.whatsapp_link}" target="_blank" class="btn-sm btn-whatsapp" title="Enviar Licença pelo WhatsApp" style="text-decoration:none; padding:3px 7px;">
                                    💬 Zap
                                </a>
                            ` : ''}
                        </div>
                    ` : '<span style="color:var(--muted)">—</span>'}
                </td>
                <td style="text-transform:capitalize; font-weight:600; color:var(--muted);">${l.plan_type}</td>
                <td><span class="badge badge-${l.status}">${l.status}</span></td>
                <td>${l.expires_at ? formatDate(l.expires_at) : '∞ (Vitalício)'}</td>
                <td style="font-size:11px; color:var(--muted); max-width:110px; overflow:hidden; text-overflow:ellipsis;" title="${l.hwid || ''}">${l.hwid || '—'}</td>
                <td style="text-align:right;">
                    <div class="action-btn-group" style="justify-content:flex-end;">
                        <button class="btn-sm" onclick="viewDetail(${l.id})" title="Ver Detalhes">Ver</button>
                        ${l.status === 'active' ? `
                            <button class="btn-sm danger" onclick="quickAction('deactivate',${l.id})">Desativar</button>
                        ` : `
                            <button class="btn-sm success" onclick="quickAction('activate',${l.id})">Ativar</button>
                        `}
                    </div>
                </td>
            </tr>
        `).join('');

        renderPagination(data.page, data.pages);
        showToast(`Exibindo licenças do parceiro ${brandName}`, 'info');
    } catch (e) {
        console.error(e);
    }
}

async function loadPartnerSelectOptions() {
    const select = document.getElementById('create-partner');
    if (!select) return;

    try {
        const res = await apiFetch('api/admin/partners.php?action=list&status=active');
        if (res.success && Array.isArray(res.partners)) {
            let html = '<option value="">Nenhum (PLATAFY FB Direto)</option>';
            res.partners.forEach(p => {
                html += `<option value="${p.id}">${escapeHtml(p.brand_name)} (@${escapeHtml(p.username)} - Cota: ${p.total_licenses}/${p.max_licenses})</option>`;
            });
            select.innerHTML = html;
        }
    } catch (e) {
        console.error('loadPartnerSelectOptions error:', e);
    }
}
