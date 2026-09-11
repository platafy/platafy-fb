/**
 * PLATAFY FB - Dynamic White Label Branding Module
 * Detects and applies partner custom branding (Name, Logo, Support links)
 * seamless zero-flicker experience while maintaining 100% backwards compatibility.
 */
(function () {
  'use strict';

  const DEFAULT_BRAND = {
    is_white_label: false,
    brand_name: 'PLATAFY FB',
    brand_logo: 'icons/icon128.png',
    support_whatsapp: '5521967659802',
    support_url: 'https://api.whatsapp.com/send?phone=5521967659802',
    brand_meta: '"O Senhor é o meu pastor, nada me faltará" Salmos 23:1-6'
  };

  let currentBrand = null;

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function applyBranding(brand) {
    if (!brand || !brand.is_white_label) {
      restoreDefaultBranding();
      return;
    }

    currentBrand = brand;
    const brandName = (brand.brand_name || 'PLATAFY FB').trim();

    // 1. Page Title
    document.title = brandName;

    // 2. Header Brand Title
    const brandNameEl = document.querySelector('.brand-name');
    if (brandNameEl) {
      let textSpan = brandNameEl.querySelector('.wl-brand-text');
      const versionBadge = brandNameEl.querySelector('span:not(.wl-brand-text)');
      const toggleBtn = brandNameEl.querySelector('#themeToggleBtn');

      if (!textSpan) {
        textSpan = document.createElement('span');
        textSpan.className = 'wl-brand-text';
        textSpan.textContent = brandName;
        brandNameEl.innerHTML = '';
        brandNameEl.appendChild(textSpan);
        if (versionBadge) brandNameEl.appendChild(versionBadge);
        if (toggleBtn) brandNameEl.appendChild(toggleBtn);
      } else {
        textSpan.textContent = brandName;
      }
    }

    // 3. Logo
    const logoImg = document.querySelector('.brand-logo img');
    if (logoImg) {
      if (brand.brand_logo) {
        logoImg.src = brand.brand_logo;
        logoImg.alt = brandName;
        logoImg.onerror = function () {
          logoImg.src = DEFAULT_BRAND.brand_logo;
        };
      } else {
        logoImg.src = DEFAULT_BRAND.brand_logo;
        logoImg.alt = brandName;
      }
      logoImg.style.maxHeight = '38px';
      logoImg.style.maxWidth = '46px';
      logoImg.style.objectFit = 'contain';
      logoImg.style.borderRadius = '6px';
    }

    // 4. Header Subtitle / Meta
    const brandMetaEl = document.querySelector('.brand-meta em');
    if (brandMetaEl) {
      brandMetaEl.textContent = `Licenciado por ${brandName}`;
    }

    // 5. Support Button in Config View (#btnAcquireLicenseConfig)
    const btnSupport = document.getElementById('btnAcquireLicenseConfig');
    if (btnSupport) {
      btnSupport.textContent = `Suporte ${brandName}`;
    }

    // 6. Recovery link
    const recoverLink = document.getElementById('btnCustomerPortalRecover');
    if (recoverLink && (brand.support_url || brand.support_whatsapp)) {
      const targetUrl = brand.support_url || `https://api.whatsapp.com/send?phone=${String(brand.support_whatsapp).replace(/\D/g, '')}&text=${encodeURIComponent('Olá! Preciso de suporte com minha licença ' + brandName)}`;
      recoverLink.href = targetUrl;
      recoverLink.innerHTML = `💬 Contatar Suporte ${escapeHtml(brandName)}`;
    }

    // 7. Area do cliente links
    const clientLinks = document.querySelectorAll('a[href*="/cliente/"]');
    clientLinks.forEach(link => {
      if (link.id !== 'btnCustomerPortalRecover') {
        if (brand.support_url || brand.support_whatsapp) {
          link.href = brand.support_url || `https://api.whatsapp.com/send?phone=${String(brand.support_whatsapp).replace(/\D/g, '')}`;
          link.textContent = `💬 Suporte ${brandName}`;
        }
      }
    });

    document.documentElement.classList.add('is-white-label');
  }

  function restoreDefaultBranding() {
    currentBrand = null;
    document.title = DEFAULT_BRAND.brand_name;

    const brandNameEl = document.querySelector('.brand-name');
    if (brandNameEl) {
      let textSpan = brandNameEl.querySelector('.wl-brand-text');
      const versionBadge = brandNameEl.querySelector('span:not(.wl-brand-text)');
      const toggleBtn = brandNameEl.querySelector('#themeToggleBtn');

      if (textSpan) {
        textSpan.textContent = DEFAULT_BRAND.brand_name;
      } else {
        brandNameEl.innerHTML = `<span class="wl-brand-text">${DEFAULT_BRAND.brand_name}</span> `;
        if (versionBadge) brandNameEl.appendChild(versionBadge);
        if (toggleBtn) brandNameEl.appendChild(toggleBtn);
      }
    }

    const logoImg = document.querySelector('.brand-logo img');
    if (logoImg) {
      logoImg.src = DEFAULT_BRAND.brand_logo;
      logoImg.alt = DEFAULT_BRAND.brand_name;
    }

    const brandMetaEl = document.querySelector('.brand-meta em');
    if (brandMetaEl) {
      brandMetaEl.textContent = DEFAULT_BRAND.brand_meta;
    }

    const btnSupport = document.getElementById('btnAcquireLicenseConfig');
    if (btnSupport) {
      btnSupport.textContent = 'Suporte';
    }

    const recoverLink = document.getElementById('btnCustomerPortalRecover');
    if (recoverLink) {
      recoverLink.href = 'https://fb.platafy.com/cliente/';
      recoverLink.innerHTML = `🔑 Esqueceu sua Licença? Acessar Área do Cliente`;
    }

    const clientLinks = document.querySelectorAll('a[href*="/cliente/"], a[href*="whatsapp.com"]');
    clientLinks.forEach(link => {
      if (link.id !== 'btnCustomerPortalRecover') {
        link.href = 'https://fb.platafy.com/cliente/';
        link.textContent = '🔑 Área do Cliente';
      }
    });

    document.documentElement.classList.remove('is-white-label');
  }

  // Intercept click on btnAcquireLicenseConfig in capture phase
  document.addEventListener('click', function (e) {
    const target = e.target.closest('#btnAcquireLicenseConfig');
    if (target && currentBrand && (currentBrand.support_url || currentBrand.support_whatsapp)) {
      e.preventDefault();
      e.stopPropagation();
      const targetUrl = currentBrand.support_url || `https://api.whatsapp.com/send?phone=${String(currentBrand.support_whatsapp).replace(/\D/g, '')}&text=${encodeURIComponent('Olá! Preciso de suporte com minha licença ' + currentBrand.brand_name)}`;
      window.open(targetUrl, '_blank');
    }
  }, true);

  // Clear branding when license is removed
  document.addEventListener('click', function (e) {
    const removeBtn = e.target.closest('#btnRemoveLicense, #btnRemoveLicenseDashboard');
    if (removeBtn) {
      if (chrome && chrome.storage && chrome.storage.local) {
        chrome.storage.local.remove('partnerBrand');
      }
      restoreDefaultBranding();
    }
  });

  // Intercept fetch to watch for license validation responses
  const originalFetch = window.fetch;
  window.fetch = async function (...args) {
    const response = await originalFetch.apply(this, args);
    try {
      const url = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url ? args[0].url : '');
      if (url.includes('/validate.php') || url.includes('validate.php')) {
        const clone = response.clone();
        clone.json().then(data => {
          if (data && data.valid && data.brand && data.brand.is_white_label) {
            if (chrome && chrome.storage && chrome.storage.local) {
              chrome.storage.local.set({ partnerBrand: data.brand });
            }
            applyBranding(data.brand);
          } else if (data && (!data.valid || !data.brand?.is_white_label)) {
            if (chrome && chrome.storage && chrome.storage.local) {
              chrome.storage.local.remove('partnerBrand');
            }
            restoreDefaultBranding();
          }
        }).catch(() => {});
      }
    } catch (err) {
      console.warn('[Branding] Interceptor error:', err);
    }
    return response;
  };

  // React to chrome.storage changes
  if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.onChanged) {
    chrome.storage.onChanged.addListener((changes, area) => {
      if (area === 'local') {
        if (changes.partnerBrand) {
          if (changes.partnerBrand.newValue && changes.partnerBrand.newValue.is_white_label) {
            applyBranding(changes.partnerBrand.newValue);
          } else {
            restoreDefaultBranding();
          }
        }
        if (changes.licenseActive && changes.licenseActive.newValue === false) {
          chrome.storage.local.remove('partnerBrand');
          restoreDefaultBranding();
        }
      }
    });
  }

  // Boot hydration from storage
  function initBranding() {
    if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
      chrome.storage.local.get(['partnerBrand', 'licenseActive'], (data) => {
        if (data && data.licenseActive !== false && data.partnerBrand && data.partnerBrand.is_white_label) {
          applyBranding(data.partnerBrand);
        } else {
          restoreDefaultBranding();
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBranding);
  } else {
    initBranding();
  }

  window.__applyWhiteLabelBranding = applyBranding;
  window.__restoreDefaultBranding = restoreDefaultBranding;
})();
