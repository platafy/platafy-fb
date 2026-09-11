'use strict';
(function initPlatafyFbFacebookPanel(){
  if (!window.location.hostname.includes('facebook.com')) return;
  const PANEL_ID = 'platafy-fb-inpage-sidebar';

  function injectStyles(){
    if (document.getElementById('platafy-fb-panel-styles')) return;
    const _style = document.createElement('style');
    _style.id = 'platafy-fb-panel-styles';
    _style.textContent = `
      #platafy-fb-inpage-sidebar {
        position: fixed;
        top: 10px;
        right: 16px;
        width: auto;
        min-width: 175px;
        height: 38px;
        background: #111827;
        color: #f3f4f6;
        border: 1px solid #374151;
        border-radius: 10px;
        box-shadow: 0 10px 20px -3px rgba(0,0,0,0.5), 0 4px 6px -2px rgba(0,0,0,0.3);
        z-index: 9999999;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 12px;
        gap: 12px;
        user-select: none;
        cursor: grab;
        box-sizing: border-box;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
      }
      #platafy-fb-inpage-sidebar:hover {
        border-color: #4b5563;
        box-shadow: 0 12px 24px -2px rgba(0,0,0,0.6);
      }
      #platafy-fb-inpage-sidebar:active {
        cursor: grabbing;
      }
      #platafy-fb-inpage-sidebar.is-dragging {
        transition: none !important;
        cursor: grabbing !important;
      }
      .pfb-panel-title-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        pointer-events: none;
      }
      .pfb-panel-logo {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        object-fit: contain;
        flex-shrink: 0;
        display: block;
      }
      .pfb-panel-title {
        font-weight: 700;
        font-size: 13px;
        color: #f9fafb;
        white-space: nowrap;
        letter-spacing: 0.5px;
      }
      .pfb-switch-wrap {
        display: flex;
        align-items: center;
        cursor: pointer;
      }
      .pfb-switch {
        position: relative;
        display: inline-block;
        width: 36px;
        height: 20px;
        flex-shrink: 0;
        cursor: pointer;
      }
      .pfb-switch input {
        opacity: 0;
        width: 0;
        height: 0;
        margin: 0;
      }
      .pfb-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #374151;
        transition: .25s cubic-bezier(0.4,0,0.2,1);
        border-radius: 20px;
        border: 1px solid #4b5563;
      }
      .pfb-slider:before {
        position: absolute;
        content: "";
        height: 14px;
        width: 14px;
        left: 2px;
        bottom: 2px;
        background-color: #fff;
        transition: .25s cubic-bezier(0.4,0,0.2,1);
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
      }
      .pfb-switch input:checked + .pfb-slider {
        background-color: #2563eb;
        border-color: #3b82f6;
      }
      .pfb-switch input:checked + .pfb-slider:before {
        transform: translateX(16px);
      }
    `;
    (document.head || document.documentElement).appendChild(_style);
  }

  function updateQueueStatusTooltip(_q, _sidebar) {
    if (!_sidebar) return;
    if (!_q || _q['status'] === 'completed' || _q['status'] === 'paused') {
      _sidebar.title = 'PLATAFY FB - Nenhuma automação ativa (Arraste para mover / Clique na chave para abrir o Painel Completo)';
    } else if (_q['status'] === 'running') {
      const _tot = _q['groups']?.length || 0;
      const _curr = _q['index'] || 0;
      _sidebar.title = `PLATAFY FB [🟢 EM EXECUÇÃO] - Postando em grupos (${_curr}/${_tot})...`;
    } else if (_q['status'] === 'waiting_daily') {
      _sidebar.title = `PLATAFY FB [🟡 AGUARDANDO] - Limite diário atingido (${_q['postedToday'] || 0}/${_q['dailyLimit'] || 50})`;
    }
  }

  function makeSidebarDraggable(_sidebar){
    let _isDragging = false, _startX = 0, _startY = 0, _initialLeft = 0, _initialTop = 0, _hasMoved = false;
    try {
      chrome.storage.local.get(['platafyFbSidebarPos'], _res => {
        const _pos = _res['platafyFbSidebarPos'];
        if (_pos && typeof _pos.top === 'number' && typeof _pos.left === 'number') {
          const _maxTop = Math.max(0, window.innerHeight - 44);
          const _maxLeft = Math.max(0, window.innerWidth - 180);
          const _top = Math.min(_maxTop, Math.max(0, _pos.top));
          const _left = Math.min(_maxLeft, Math.max(0, _pos.left));
          _sidebar.style.top = _top + 'px';
          _sidebar.style.left = _left + 'px';
          _sidebar.style.right = 'auto';
        }
      });
    } catch(_e){}

    function _onMouseDown(_e){
      if (_e.target && _e.target.closest('.pfb-switch')) return;
      _isDragging = true;
      _hasMoved = false;
      _startX = _e.clientX || _e.touches?.[0]?.clientX || 0;
      _startY = _e.clientY || _e.touches?.[0]?.clientY || 0;
      const _rect = _sidebar.getBoundingClientRect();
      _initialLeft = _rect.left;
      _initialTop = _rect.top;
      _sidebar.classList.add('is-dragging');
      document.addEventListener('mousemove', _onMouseMove, { passive: false });
      document.addEventListener('mouseup', _onMouseUp);
      document.addEventListener('touchmove', _onMouseMove, { passive: false });
      document.addEventListener('touchend', _onMouseUp);
    }

    function _onMouseMove(_e){
      if (!_isDragging) return;
      const _cX = _e.clientX || _e.touches?.[0]?.clientX || 0;
      const _cY = _e.clientY || _e.touches?.[0]?.clientY || 0;
      const _dX = _cX - _startX;
      const _dY = _cY - _startY;
      if (Math.abs(_dX) > 3 || Math.abs(_dY) > 3) {
        _hasMoved = true;
        if (_e.cancelable) _e.preventDefault();
      }
      if (!_hasMoved) return;
      const _rect = _sidebar.getBoundingClientRect();
      const _maxLeft = Math.max(0, window.innerWidth - _rect.width);
      const _maxTop = Math.max(0, window.innerHeight - _rect.height);
      const _nLeft = Math.min(_maxLeft, Math.max(0, _initialLeft + _dX));
      const _nTop = Math.min(_maxTop, Math.max(0, _initialTop + _dY));
      _sidebar.style.left = _nLeft + 'px';
      _sidebar.style.top = _nTop + 'px';
      _sidebar.style.right = 'auto';
    }

    function _onMouseUp(){
      if (!_isDragging) return;
      _isDragging = false;
      _sidebar.classList.remove('is-dragging');
      document.removeEventListener('mousemove', _onMouseMove);
      document.removeEventListener('mouseup', _onMouseUp);
      document.removeEventListener('touchmove', _onMouseMove);
      document.removeEventListener('touchend', _onMouseUp);
      if (_hasMoved) {
        const _rect = _sidebar.getBoundingClientRect();
        try {
          chrome.storage.local.set({ platafyFbSidebarPos: { top: Math.round(_rect.top), left: Math.round(_rect.left) } });
        } catch(_e){}
      }
    }

    _sidebar.addEventListener('mousedown', _onMouseDown);
    _sidebar.addEventListener('touchstart', _onMouseDown, { passive: true });
  }

  function renderSidebar(){
    injectStyles();
    if(document.getElementById(PANEL_ID)) return;
    const _sidebar = document.createElement('div');
    _sidebar.id = PANEL_ID;
    _sidebar.title = 'PLATAFY FB (Arraste para mover / Clique na chave para abrir o Painel Completo)';
    const _logoUrl = chrome.runtime.getURL('icons/platafy-logo.png');

    _sidebar.innerHTML = `
      <div class="pfb-panel-title-wrap">
        <img class="pfb-panel-logo" src="${_logoUrl}" alt="PLATAFY FB" />
        <span class="pfb-panel-title">PLATAFY FB</span>
      </div>
      <div class="pfb-switch-wrap">
        <label class="pfb-switch" title="Abrir / Fechar Painel Completo">
          <input type="checkbox" id="pfbToggleSwitch" />
          <span class="pfb-slider"></span>
        </label>
      </div>
    `;

    document.body.appendChild(_sidebar);
    makeSidebarDraggable(_sidebar);

    const _toggleInput = document.getElementById('pfbToggleSwitch');
    _toggleInput?.addEventListener('change', function(e) {
      e.stopPropagation();
      if (this.checked) {
        try { chrome.runtime.sendMessage({ action: 'platafyFbOpenTool' }); } catch(_e){}
      } else {
        try { chrome.runtime.sendMessage({ action: 'platafyFbCloseTool' }); } catch(_e){}
      }
    });

    try {
      chrome.runtime.sendMessage({ action: 'platafyFbCheckToolOpen' }, _res => {
        if (_toggleInput) _toggleInput.checked = Boolean(_res?.isOpen);
      });
    } catch(_e){}

    try {
      chrome.storage.local.get(['platafyFbFacebookGroupPostQueueV1'], _res => {
        updateQueueStatusTooltip(_res['platafyFbFacebookGroupPostQueueV1'], _sidebar);
      });
    } catch(_e){}

    try {
      chrome.storage.onChanged.addListener((_ch, _area) => {
        if (_area === 'local') {
          if (_ch['platafyFbToolIsOpen'] !== undefined && _toggleInput) {
            _toggleInput.checked = Boolean(_ch['platafyFbToolIsOpen'].newValue);
          }
          if (_ch['platafyFbFacebookGroupPostQueueV1']) {
            updateQueueStatusTooltip(_ch['platafyFbFacebookGroupPostQueueV1'].newValue, _sidebar);
          }
        }
      });
    } catch(_e){}
  }

  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', renderSidebar) : renderSidebar();
})();

(function(_0x19bd7b,_0x442764){const _0x12b1ea=f4c_0x327f,_0x322244=_0x19bd7b();while(!![]){try{const _0xa949e7=-parseInt(_0x12b1ea(0x2c5))/0x1*(parseInt(_0x12b1ea(0x5e8))/0x2)+-parseInt(_0x12b1ea(0x399))/0x3*(parseInt(_0x12b1ea(0x383))/0x4)+-parseInt(_0x12b1ea(0x4a7))/0x5+parseInt(_0x12b1ea(0x30f))/0x6*(-parseInt(_0x12b1ea(0x2ce))/0x7)+parseInt(_0x12b1ea(0x5b2))/0x8*(parseInt(_0x12b1ea(0x5e2))/0x9)+parseInt(_0x12b1ea(0x3ec))/0xa*(parseInt(_0x12b1ea(0x304))/0xb)+parseInt(_0x12b1ea(0x51e))/0xc*(parseInt(_0x12b1ea(0x332))/0xd);if(_0xa949e7===_0x442764)break;else _0x322244['push'](_0x322244['shift']());}catch(_0x48071e){_0x322244['push'](_0x322244['shift']());}}}(f4c_0x3516,0xa71c3));chrome[f4c_0x8b7765(0x355)]['onMessag'+'e'][f4c_0x8b7765(0x20e)+f4c_0x8b7765(0x357)]((_0x40a315,_0x396e07,_0x59f2bd)=>{const _0xfa9ae3=f4c_0x8b7765;if(_0x40a315['action']===_0xfa9ae3(0x41a))return _0x59f2bd({'status':'ok','url':window[_0xfa9ae3(0x489)][_0xfa9ae3(0x512)]}),!![];if(_0x40a315[_0xfa9ae3(0x4a6)]===_0xfa9ae3(0x1e0)+'roups'){const _0x6c80c=extractGroups();return _0x59f2bd({'success':!![],'data':_0x6c80c}),!![];}if(_0x40a315[_0xfa9ae3(0x4a6)]===_0xfa9ae3(0x2b7)+'riends'){const _0x217949=extractFriends();return _0x59f2bd({'success':!![],'data':_0x217949}),!![];}if(_0x40a315[_0xfa9ae3(0x4a6)]===_0xfa9ae3(0x526)+_0xfa9ae3(0x5ba)+'rsPageSc'+'an')return platafyFbStartMembersPageScan(_0x40a315)[_0xfa9ae3(0x1dc)](_0x59f2bd)['catch'](_0x573900=>_0x59f2bd({'success':![],'error':_0x573900?.['message']||_0xfa9ae3(0x3ff)+'\x20iniciar'+_0xfa9ae3(0x510)+'e\x20membro'+'s.'})),!![];if(_0x40a315[_0xfa9ae3(0x4a6)]===_0xfa9ae3(0x526)+_0xfa9ae3(0x495)+_0xfa9ae3(0x57f)+'n')return platafyFbStopMembersPageScan()[_0xfa9ae3(0x1dc)](_0x59f2bd)[_0xfa9ae3(0x334)](_0x365009=>_0x59f2bd({'success':![],'error':_0x365009?.[_0xfa9ae3(0x58f)]||_0xfa9ae3(0x3ff)+'\x20parar\x20b'+'usca\x20de\x20'+'membros.'})),!![];});function extractGroups(){const _0x2cc4ec=f4c_0x8b7765,_0x1e0ee5=[],_0x1bdff7=new Set(),_0x19e48b=[_0x2cc4ec(0x31a)+_0x2cc4ec(0x1f3)+_0x2cc4ec(0x5a0),'[data-te'+_0x2cc4ec(0x30c)+_0x2cc4ec(0x219)+_0x2cc4ec(0x340)+'\x20a','a[role=\x22'+_0x2cc4ec(0x4b2)+'ref*=\x22/g'+_0x2cc4ec(0x4dc)];return _0x19e48b['forEach'](_0x22d2b7=>{const _0x1ca9c0=_0x2cc4ec;document[_0x1ca9c0(0x2cb)+_0x1ca9c0(0x4cb)](_0x22d2b7)['forEach'](_0x12e29e=>{const _0x5edf77=_0x1ca9c0;try{const _0xb762c=_0x12e29e['href']||'',_0x107fb4=(_0x12e29e[_0x5edf77(0x3d0)+_0x5edf77(0x473)]||_0x12e29e[_0x5edf77(0x375)+'bute'](_0x5edf77(0x4e2)+'el')||'')['trim']();_0xb762c['includes']('/groups/')&&_0x107fb4&&_0x107fb4[_0x5edf77(0x570)]>0x1&&!_0x1bdff7[_0x5edf77(0x3bf)](_0xb762c)&&(!_0xb762c['includes'](_0x5edf77(0x509)+_0x5edf77(0x2ab))&&!_0xb762c['includes'](_0x5edf77(0x509)+_0x5edf77(0x52d))&&(_0x1bdff7[_0x5edf77(0x450)](_0xb762c),_0x1e0ee5[_0x5edf77(0x2ad)]({'name':_0x107fb4['substrin'+'g'](0x0,0x50),'url':_0xb762c[_0x5edf77(0x2c1)]('?')[0x0]})));}catch(_0x26a575){}});}),_0x1e0ee5[_0x2cc4ec(0x1c6)](0x0,0x1f4);}function extractFriends(){const _0x43077c=f4c_0x8b7765,_0x58bdf9=[],_0x114ce0=new Set();return document[_0x43077c(0x2cb)+_0x43077c(0x4cb)](_0x43077c(0x31a)+'\x22faceboo'+_0x43077c(0x22e))['forEach'](_0x3eaae1=>{const _0x21a9f4=_0x43077c;try{const _0x3a9a06=_0x3eaae1[_0x21a9f4(0x512)]||'',_0x36737a=(_0x3eaae1[_0x21a9f4(0x3d0)+_0x21a9f4(0x473)]||'')[_0x21a9f4(0x22c)]();if(_0x36737a&&_0x36737a[_0x21a9f4(0x570)]>0x2&&_0x36737a[_0x21a9f4(0x570)]<0x3c&&!_0x114ce0[_0x21a9f4(0x3bf)](_0x3a9a06)){const _0x6f085b=_0x3a9a06[_0x21a9f4(0x5a4)](/facebook\.com\/[a-zA-Z0-9._]+\/?$/)||_0x3a9a06['includes'](_0x21a9f4(0x4ac)+_0x21a9f4(0x24a)),_0x1645b2=!_0x3a9a06[_0x21a9f4(0x287)](_0x21a9f4(0x509))&&!_0x3a9a06[_0x21a9f4(0x287)]('/pages/')&&!_0x3a9a06[_0x21a9f4(0x287)](_0x21a9f4(0x1ce))&&!_0x3a9a06[_0x21a9f4(0x287)](_0x21a9f4(0x59c)+_0x21a9f4(0x575))&&!_0x3a9a06[_0x21a9f4(0x287)]('/watch/')&&!_0x3a9a06['includes'](_0x21a9f4(0x5a3))&&!_0x3a9a06[_0x21a9f4(0x287)](_0x21a9f4(0x27e))&&!_0x3a9a06[_0x21a9f4(0x287)](_0x21a9f4(0x4f6)+'s/');_0x6f085b&&_0x1645b2&&(_0x114ce0[_0x21a9f4(0x450)](_0x3a9a06),_0x58bdf9['push']({'name':_0x36737a,'url':_0x3a9a06['split']('?')[0x0]}));}}catch(_0x1607f1){}}),_0x58bdf9['slice'](0x0,0x1f4);}window[f4c_0x8b7765(0x489)][f4c_0x8b7765(0x346)]===f4c_0x8b7765(0x5d5)+f4c_0x8b7765(0x428)&&console.log('%c[PLATAFY FB] Extensão ativa nesta aba.', 'color: #2563eb; font-weight: bold;');const PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY='platafyFbMe'+f4c_0x8b7765(0x5b6)+'eScanSta'+f4c_0x8b7765(0x578),PLATAFY_FB_MEMBERS_OVERLAY_ID='platafy-fb-'+'members-'+f4c_0x8b7765(0x1fd)+f4c_0x8b7765(0x4d9)+'y';function platafyFbStorageGet(_0x2bde51){return new Promise(_0x5b21c6=>{const _0x2bdda2=f4c_0x327f;try{chrome['storage'][_0x2bdda2(0x209)][_0x2bdda2(0x586)](_0x2bde51,_0x5b21c6);}catch{_0x5b21c6({});}});}function platafyFbStorageSet(_0x264230){return new Promise(_0xe948fd=>{const _0x392736=f4c_0x327f;try{chrome[_0x392736(0x28a)]['local'][_0x392736(0x2a6)](_0x264230,()=>_0xe948fd(!![]));}catch{_0xe948fd(![]);}});}function platafyFbSleep(_0x4e55a2){return new Promise(_0xd6c68f=>setTimeout(_0xd6c68f,_0x4e55a2));}function f4c_0x327f(_0x4d090c,_0xc3bc70){const _0x351664=f4c_0x3516();return f4c_0x327f=function(_0x327f12,_0x2c4b8e){_0x327f12=_0x327f12-0x1c5;let _0x4130ce=_0x351664[_0x327f12];if(f4c_0x327f['RyqxWB']===undefined){var _0x4ed6af=function(_0x40a315){const _0x396e07='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/=';let _0x59f2bd='',_0x6c80c='';for(let _0x217949=0x0,_0x573900,_0x365009,_0x1e0ee5=0x0;_0x365009=_0x40a315['charAt'](_0x1e0ee5++);~_0x365009&&(_0x573900=_0x217949%0x4?_0x573900*0x40+_0x365009:_0x365009,_0x217949++%0x4)?_0x59f2bd+=String['fromCharCode'](0xff&_0x573900>>(-0x2*_0x217949&0x6)):0x0){_0x365009=_0x396e07['indexOf'](_0x365009);}for(let _0x1bdff7=0x0,_0x19e48b=_0x59f2bd['length'];_0x1bdff7<_0x19e48b;_0x1bdff7++){_0x6c80c+='%'+('00'+_0x59f2bd['charCodeAt'](_0x1bdff7)['toString'](0x10))['slice'](-0x2);}return decodeURIComponent(_0x6c80c);};f4c_0x327f['MkURLW']=_0x4ed6af,_0x4d090c=arguments,f4c_0x327f['RyqxWB']=!![];}const _0x5e2728=_0x351664[0x0],_0x1fe828=_0x327f12+_0x5e2728,_0x5e9c52=_0x4d090c[_0x1fe828];return!_0x5e9c52?(_0x4130ce=f4c_0x327f['MkURLW'](_0x4130ce),_0x4d090c[_0x1fe828]=_0x4130ce):_0x4130ce=_0x5e9c52,_0x4130ce;},f4c_0x327f(_0x4d090c,_0xc3bc70);}function platafyFbCompactText(_0x33dc94){const _0x298667=f4c_0x8b7765;return String(_0x33dc94||'')[_0x298667(0x459)](/\s+/g,'\x20')['trim']();}function platafyFbNormalizeText(_0x100c60){const _0xa39ec1=f4c_0x8b7765;return platafyFbCompactText(_0x100c60)[_0xa39ec1(0x39f)+'e'](_0xa39ec1(0x398))[_0xa39ec1(0x459)](/[\u0300-\u036f]/g,'')[_0xa39ec1(0x3e2)+'ase']();}function platafyFbNormalizeGroupUrl(_0x41a100){const _0x3fc0d7=f4c_0x8b7765;if(!_0x41a100||!String(_0x41a100)['includes'](_0x3fc0d7(0x509)))return'';try{const _0x4ebdcc=new URL(_0x41a100,window['location'][_0x3fc0d7(0x466)]),_0x5b1875=_0x4ebdcc[_0x3fc0d7(0x256)][_0x3fc0d7(0x459)](/\/+$/,'')[_0x3fc0d7(0x2c1)]('/')[_0x3fc0d7(0x52b)](Boolean);if(_0x5b1875[0x0]!==_0x3fc0d7(0x3b7)||!_0x5b1875[0x1]||['feed',_0x3fc0d7(0x52d),_0x3fc0d7(0x573)][_0x3fc0d7(0x287)](_0x5b1875[0x1]))return'';return _0x4ebdcc[_0x3fc0d7(0x599)]=_0x3fc0d7(0x33c),_0x4ebdcc[_0x3fc0d7(0x346)]=_0x4ebdcc[_0x3fc0d7(0x346)]==='facebook'+_0x3fc0d7(0x541)?_0x3fc0d7(0x5d5)+_0x3fc0d7(0x428):_0x4ebdcc['hostname'],_0x4ebdcc[_0x3fc0d7(0x548)]='',_0x4ebdcc[_0x3fc0d7(0x472)]='',''+_0x4ebdcc[_0x3fc0d7(0x466)]+_0x4ebdcc[_0x3fc0d7(0x256)]['replace'](/\/+$/,'');}catch{return'';}}function platafyFbParseMemberCountNumber(_0xc17e4b){const _0x4ae3f0=f4c_0x8b7765,_0x269e68=platafyFbNormalizeText(_0xc17e4b),_0x32dd8e=_0x269e68[_0x4ae3f0(0x5a4)](/(\d+(?:[.,]\d+)?(?:[.,]\d{3})*)\s*(milhao|milhoes|mi|million|millions|m|mil|k)?\s*(?:membros|members)/i);if(!_0x32dd8e)return 0x0;let _0x183e83=_0x32dd8e[0x1]||'';const _0x557ccc=_0x32dd8e[0x2]||'';if(_0x183e83[_0x4ae3f0(0x287)](',')&&_0x183e83['includes']('.'))_0x183e83=_0x183e83['replace'](/\./g,'')[_0x4ae3f0(0x459)](',','.');else{if(_0x183e83['includes'](',')){const _0x116682=/,(\d{1,2})$/['test'](_0x183e83)&&/(mil|k|mi|m|million)/['test'](_0x557ccc);_0x183e83=_0x116682?_0x183e83[_0x4ae3f0(0x459)](',','.'):_0x183e83['replace'](/,/g,'');}else{if(_0x183e83[_0x4ae3f0(0x287)]('.')){const _0x1af0f3=/\.(\d{1,2})$/[_0x4ae3f0(0x46f)](_0x183e83)&&/(mil|k|mi|m|million)/[_0x4ae3f0(0x46f)](_0x557ccc);_0x183e83=_0x1af0f3?_0x183e83:_0x183e83[_0x4ae3f0(0x459)](/\./g,'');}}}let _0x512504=parseFloat(_0x183e83);if(!Number['isFinite'](_0x512504)||_0x512504<=0x0)return 0x0;if(/^(milhao|milhoes|mi|million|millions|m)$/[_0x4ae3f0(0x46f)](_0x557ccc))_0x512504*=0xf4240;else/^(mil|k)$/['test'](_0x557ccc)&&(_0x512504*=0x3e8);return Math[_0x4ae3f0(0x367)](_0x512504);}function platafyFbBadMemberContext(_0x14784d){const _0x4840d5=f4c_0x8b7765,_0x299aed=platafyFbNormalizeText(_0x14784d);return/(?:membros?\s+(?:novo|novos|nova|novas|ativo|ativos)|(?:novo|novos|nova|novas|ativo|ativos)\s+membros?|convidar\s+membros?|adicionar\s+membros?|solicitar\s+participacao|membership questions|answer questions|group rules|regras do grupo|publica(?:cao|coes)|posts?)/i[_0x4840d5(0x46f)](_0x299aed);}function platafyFbFindMemberLine(_0x44c71e){const _0x15ec2c=f4c_0x8b7765,_0x14004b=String(_0x44c71e||''),_0x4f2c7f=_0x14004b[_0x15ec2c(0x2c1)](/\n+|["¢·|]/)['map'](platafyFbCompactText)['filter'](Boolean);for(const _0x472a64 of _0x4f2c7f){if(platafyFbParseMemberCountNumber(_0x472a64)>0x0&&!platafyFbBadMemberContext(_0x472a64))return _0x472a64;}const _0x30635f=platafyFbCompactText(_0x14004b),_0x37f72a=/(\d+(?:[.,]\d+)?(?:[.,]\d{3})*)\s*(milhao|milhoes|mi|million|millions|m|mil|k)?\s*(membros|members)/gi;let _0x32b58d;while((_0x32b58d=_0x37f72a[_0x15ec2c(0x5f5)](_0x30635f))!==null){const _0x190bc7=platafyFbCompactText(_0x32b58d[0x0]),_0x51e71c=_0x30635f[_0x15ec2c(0x1c6)](Math[_0x15ec2c(0x1cf)](0x0,_0x32b58d[_0x15ec2c(0x551)]-0x3c),Math[_0x15ec2c(0x345)](_0x30635f[_0x15ec2c(0x570)],_0x32b58d[_0x15ec2c(0x551)]+_0x32b58d[0x0][_0x15ec2c(0x570)]+0x50));if(platafyFbParseMemberCountNumber(_0x190bc7)>0x0&&!platafyFbBadMemberContext(_0x51e71c))return _0x190bc7;}return'';}function platafyFbIsVisible(_0x5ad47d){const _0x24dfa1=f4c_0x8b7765;if(!_0x5ad47d)return![];const _0x1e9532=_0x5ad47d[_0x24dfa1(0x33f)+_0x24dfa1(0x251)+_0x24dfa1(0x4a2)](),_0x4c957e=window[_0x24dfa1(0x226)+_0x24dfa1(0x4c1)](_0x5ad47d);return _0x1e9532['width']>0x0&&_0x1e9532[_0x24dfa1(0x47e)]>0x0&&_0x4c957e['display']!==_0x24dfa1(0x491)&&_0x4c957e[_0x24dfa1(0x35e)+'ty']!==_0x24dfa1(0x3b4)&&_0x4c957e['opacity']!=='0';}function platafyFbMainRoot(){const _0x1d9045=f4c_0x8b7765;return document['querySel'+_0x1d9045(0x5b1)](_0x1d9045(0x45c)+_0x1d9045(0x3a8))||document[_0x1d9045(0x2cb)+_0x1d9045(0x5b1)](_0x1d9045(0x3ab)+_0x1d9045(0x35a)+_0x1d9045(0x506)+'o\x20princi'+'pal\x22],\x20d'+_0x1d9045(0x5d2)+_0x1d9045(0x382)+_0x1d9045(0x5af)+_0x1d9045(0x5db)+_0x1d9045(0x598)+_0x1d9045(0x5a7)+_0x1d9045(0x4b1)+'n\x20conten'+'t\x22]')||document['body'];}function platafyFbReadMembersOnGroupPage(){const _0x49523c=f4c_0x8b7765,_0x5d4dd6=platafyFbMainRoot(),_0x9a1096=[],_0x416791='span,\x20di'+_0x49523c(0x5d0)+_0x49523c(0x486)+_0x49523c(0x4f2);Array['from'](_0x5d4dd6[_0x49523c(0x2cb)+'ectorAll'](_0x416791))['forEach'](_0x5c186f=>{const _0x5305e3=_0x49523c;if(!platafyFbIsVisible(_0x5c186f)||_0x5c186f[_0x5305e3(0x494)]('#'+PLATAFY_FB_MEMBERS_OVERLAY_ID))return;const _0x54bf89=_0x5c186f['getBound'+'ingClien'+_0x5305e3(0x4a2)](),_0x4d0253=_0x5c186f['innerTex'+'t']||_0x5c186f[_0x5305e3(0x3d0)+_0x5305e3(0x473)]||_0x5c186f[_0x5305e3(0x375)+_0x5305e3(0x566)]('aria-lab'+'el')||'',_0xab9d67=platafyFbFindMemberLine(_0x4d0253),_0x339348=platafyFbParseMemberCountNumber(_0xab9d67);if(!_0xab9d67||!_0x339348)return;const _0x2794da=platafyFbNormalizeText(_0x4d0253),_0x274d27=_0x54bf89[_0x5305e3(0x37c)]*_0x54bf89['height'];let _0x4d8dff=0x0;_0x4d8dff+=Math[_0x5305e3(0x1cf)](0x0,_0x54bf89['top']),_0x4d8dff+=Math[_0x5305e3(0x345)](0xdc,platafyFbCompactText(_0x4d0253)[_0x5305e3(0x570)]);if(_0x54bf89[_0x5305e3(0x452)]>0x2d0)_0x4d8dff+=0x384;if(_0x274d27>0x2bf20)_0x4d8dff+=0x190;if(_0x2794da['includes'](_0x5305e3(0x458)+_0x5305e3(0x4a0))||_0x2794da[_0x5305e3(0x287)]('grupo\x20pr'+'ivado')||_0x2794da[_0x5305e3(0x287)](_0x5305e3(0x597)+_0x5305e3(0x5de))||_0x2794da[_0x5305e3(0x287)](_0x5305e3(0x1e8)+_0x5305e3(0x235)))_0x4d8dff-=0x15e;if(_0x2794da['includes'](_0x5305e3(0x3a4))||_0x2794da[_0x5305e3(0x287)](_0x5305e3(0x40a)))_0x4d8dff-=0x50;_0x9a1096[_0x5305e3(0x2ad)]({'text':_0xab9d67,'number':_0x339348,'score':_0x4d8dff,'top':_0x54bf89[_0x5305e3(0x452)]});}),_0x9a1096['sort']((_0x4d2457,_0x2cf582)=>_0x4d2457[_0x49523c(0x5ad)]-_0x2cf582[_0x49523c(0x5ad)]||_0x4d2457['top']-_0x2cf582[_0x49523c(0x452)]||_0x2cf582['number']-_0x4d2457[_0x49523c(0x23b)]);if(_0x9a1096[0x0])return{'membersText':_0x9a1096[0x0][_0x49523c(0x54d)],'membersNumber':_0x9a1096[0x0]['number']};const _0xa4735d=platafyFbFindMemberLine(_0x5d4dd6[_0x49523c(0x5b3)+'t']||_0x5d4dd6[_0x49523c(0x3d0)+_0x49523c(0x473)]||document[_0x49523c(0x2d9)]['innerTex'+'t']||''),_0x35938e=platafyFbParseMemberCountNumber(_0xa4735d);return _0x35938e?{'membersText':_0xa4735d,'membersNumber':_0x35938e}:null;}function platafyFbFilterMembersItems(_0x2f54ec,_0x1e6007){const _0x1130f6=f4c_0x8b7765,_0x37a261=Math[_0x1130f6(0x1cf)](0x0,Number(_0x1e6007||0x0)||0x0);if(!_0x37a261)return _0x2f54ec;return _0x2f54ec[_0x1130f6(0x52b)](_0x4d22e3=>!Number(_0x4d22e3[_0x1130f6(0x3de)+_0x1130f6(0x324)]||0x0)||Number(_0x4d22e3['membersN'+_0x1130f6(0x324)]||0x0)>=_0x37a261);}async function platafyFbSaveMembersToExtractStorage(_0x5c02cf){const _0x103b08=f4c_0x8b7765,_0x34ec05=String(_0x5c02cf['storageK'+'ey']||_0x103b08(0x30a)+_0x103b08(0x4d5)+'msV1'),_0xe99918=await platafyFbStorageGet([_0x34ec05]),_0x3cce52=_0xe99918[_0x34ec05]&&typeof _0xe99918[_0x34ec05]===_0x103b08(0x3ce)&&!Array['isArray'](_0xe99918[_0x34ec05])?_0xe99918[_0x34ec05]:{};_0x3cce52[_0x103b08(0x3b7)]=platafyFbFilterMembersItems(_0x5c02cf[_0x103b08(0x25b)]||[],_0x5c02cf[_0x103b08(0x2a7)+'rs']||0x0),await platafyFbStorageSet({[_0x34ec05]:_0x3cce52});}function platafyFbCreateMembersOverlay(_0x4ba534,_0x34cd5f,_0x275e92=![],_0x4ea874=f4c_0x8b7765(0x3c7)){const _0x391baf=f4c_0x8b7765;document['getEleme'+_0x391baf(0x3db)](PLATAFY_FB_MEMBERS_OVERLAY_ID)?.[_0x391baf(0x2ba)]();const _0x15e7fe=document[_0x391baf(0x4b5)+_0x391baf(0x449)]('div');_0x15e7fe['id']=PLATAFY_FB_MEMBERS_OVERLAY_ID;const _0x14150a=Math[_0x391baf(0x345)](Number(_0x4ba534[_0x391baf(0x551)]||0x0)+0x1,(_0x4ba534[_0x391baf(0x25b)]||[])[_0x391baf(0x570)]),_0x54fbfd=(_0x4ba534['items']||[])[_0x391baf(0x570)],_0x595d68=_0x275e92?_0x391baf(0x224)+_0x391baf(0x347)+'ros':_0x391baf(0x224)+_0x391baf(0x347)+_0x391baf(0x1d1)+_0x14150a+'/'+_0x54fbfd;_0x15e7fe['innerHTM'+'L']=_0x391baf(0x3b1)+_0x391baf(0x40e)+'\x22font-we'+'ight:900'+_0x391baf(0x390)+_0x391baf(0x4bb)+_0x391baf(0x389)+'ottom:4p'+_0x391baf(0x275)+_0x595d68+(_0x391baf(0x434)+'\x20\x20\x20<div\x20'+_0x391baf(0x5dc)+_0x391baf(0x3f1)+_0x391baf(0x265)+_0x391baf(0x5c7)+_0x391baf(0x5bd)+'e-height'+':1.35;ma'+_0x391baf(0x5bc)+_0x391baf(0x327)+'\x22>')+(_0x34cd5f||_0x391baf(0x2ac)+'mbros...')+('</div>\x0a\x20'+_0x391baf(0x5f6)+_0x391baf(0x2db)+_0x391baf(0x3c6)+'type=\x22bu'+_0x391baf(0x359)+_0x391baf(0x3bd)+_0x391baf(0x468)+_0x391baf(0x1d8)+_0x391baf(0x24d)+_0x391baf(0x478)+_0x391baf(0x1f1)+_0x391baf(0x46e))+(_0x275e92?'#16a34a':'#ef4444')+(';color:#'+'fff;font'+'-weight:'+_0x391baf(0x4db)+_0x391baf(0x2e6)+'10px;cur'+_0x391baf(0x1c9)+_0x391baf(0x4c8))+(_0x275e92?'OK':_0x391baf(0x2f5))+('</button'+'>\x0a\x20\x20'),Object['assign'](_0x15e7fe['style'],{'position':_0x391baf(0x23d),'right':_0x391baf(0x32a),'bottom':'82px','zIndex':_0x391baf(0x58b)+'47','width':_0x391baf(0x4b7),'padding':'12px','borderRadius':_0x391baf(0x4eb),'background':_0x391baf(0x4e1)+_0x391baf(0x1ec)+'6)','color':_0x391baf(0x3ef),'fontFamily':_0x391baf(0x32d)+'ans-seri'+'f','boxShadow':_0x391baf(0x213)+'5px\x20rgba'+'(0,0,0,.'+'35)','border':_0x391baf(0x297)+_0x391baf(0x331)+_0x391baf(0x2f2)+_0x391baf(0x590)}),document[_0x391baf(0x2d9)][_0x391baf(0x3dc)+_0x391baf(0x1cd)](_0x15e7fe),_0x15e7fe[_0x391baf(0x2cb)+_0x391baf(0x5b1)](_0x391baf(0x577)+'-stop]')?.[_0x391baf(0x395)+_0x391baf(0x4b8)](_0x391baf(0x259),async()=>{const _0x3a3d45=_0x391baf;if(_0x275e92){_0x15e7fe[_0x3a3d45(0x2ba)]();return;}const _0x514d5c=await platafyFbStorageGet([PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]),_0x8f308c=_0x514d5c[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]||_0x4ba534;_0x8f308c[_0x3a3d45(0x286)]=!![],_0x8f308c['active']=![],await platafyFbStorageSet({[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]:_0x8f308c});const _0x561f79=_0x15e7fe[_0x3a3d45(0x2cb)+_0x3a3d45(0x5b1)](_0x3a3d45(0x577)+'-status]');if(_0x561f79)_0x561f79[_0x3a3d45(0x3d0)+'ent']=_0x3a3d45(0x34a)+'..\x20reabr'+_0x3a3d45(0x3c5)+'URA\x20para'+_0x3a3d45(0x4dd)+_0x3a3d45(0x60a)+_0x3a3d45(0x290);});}async function platafyFbStartMembersPageScan(_0x306f2){const _0x4551b3=f4c_0x8b7765;if(!window[_0x4551b3(0x489)][_0x4551b3(0x346)][_0x4551b3(0x287)](_0x4551b3(0x4fa)+'.com'))return{'success':![],'error':_0x4551b3(0x60b)+'\x20aba\x20do\x20'+_0x4551b3(0x419)+_0x4551b3(0x266)+'e\x20buscar'+'\x20membros'+'.'};const _0x427a7c=(Array[_0x4551b3(0x43a)](_0x306f2[_0x4551b3(0x25b)])?_0x306f2[_0x4551b3(0x25b)]:[])['map'](_0x8c0484=>({'name':platafyFbCompactText(_0x8c0484?.[_0x4551b3(0x30b)]||''),'url':platafyFbNormalizeGroupUrl(_0x8c0484?.[_0x4551b3(0x1e4)]||_0x8c0484?.[_0x4551b3(0x218)]||''),'membersText':platafyFbCompactText(_0x8c0484?.[_0x4551b3(0x3c2)+_0x4551b3(0x36c)]||''),'membersNumber':Number(_0x8c0484?.[_0x4551b3(0x3de)+_0x4551b3(0x324)]||0x0)||0x0}))[_0x4551b3(0x52b)](_0x332c97=>_0x332c97[_0x4551b3(0x30b)]&&_0x332c97[_0x4551b3(0x1e4)]);if(!_0x427a7c[_0x4551b3(0x570)])return{'success':![],'error':_0x4551b3(0x2ef)+'rupo\x20val'+'ido\x20rece'+'bido\x20par'+_0x4551b3(0x496)+_0x4551b3(0x2c9)+'.'};const _0x199006=_0x4551b3(0x481)+Date[_0x4551b3(0x47a)]()+'-'+Math['random']()['toString'](0x24)[_0x4551b3(0x1c6)](0x2,0x8);window[_0x4551b3(0x30b)]=_0x4551b3(0x580)+'mbersSca'+'n:'+_0x199006;const _0x1df468={'active':!![],'stop':![],'runId':_0x199006,'index':0x0,'found':0x0,'items':_0x427a7c,'minMembers':Math[_0x4551b3(0x1cf)](0x0,Number(_0x306f2['minMembe'+'rs']||0x0)||0x0),'storageKey':String(_0x306f2[_0x4551b3(0x3e3)+'ey']||_0x4551b3(0x30a)+_0x4551b3(0x4d5)+'msV1'),'originalUrl':window[_0x4551b3(0x489)][_0x4551b3(0x512)],'startedAt':Date['now']()};return await platafyFbStorageSet({[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]:_0x1df468}),await platafyFbSaveMembersToExtractStorage(_0x1df468),platafyFbCreateMembersOverlay(_0x1df468,'Iniciand'+'o\x20leitur'+_0x4551b3(0x5df)+_0x4551b3(0x514)+'po\x20por\x20g'+'rupo\x20aut'+_0x4551b3(0x3c8)+_0x4551b3(0x244)),window[_0x4551b3(0x56a)+'ut'](()=>{const _0x52aa54=_0x4551b3;window[_0x52aa54(0x489)]['href']=_0x427a7c[0x0][_0x52aa54(0x1e4)];},0x258),{'success':!![],'started':!![],'total':_0x427a7c[_0x4551b3(0x570)]};}async function platafyFbStopMembersPageScan(){const _0x162249=f4c_0x8b7765,_0x58dc42=await platafyFbStorageGet([PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]),_0x688155=_0x58dc42[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY];return _0x688155&&(_0x688155[_0x162249(0x286)]=!![],_0x688155[_0x162249(0x603)]=![],await platafyFbStorageSet({[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]:_0x688155})),{'success':!![]};}function f4c_0x3516(){const _0x1c9d52=['zg9YigrLigC','igDYDxbV','Bgy6ignLBNq','w2rHDgeTDgu','mJiPoYbWB2K','AxmTyNjHBMq','yw50oYbJB2W','lMzHlwjYyw4','oYbIB3jKzxi','BMuTAgvPz2G','q29UDgxdG8k6za','zsb7igjVCMq','oIaXChG7igi','l2DYB3vWCY8','rs1TywLSigu','kJ0Iy2HHBM4','CIbTzw1ICM8','igjHDguTCge','BhKTAw5Qzwm','idaGmxb4ihi','igj1C2nHigq','qxf1zwnLzg8','AhjLzG','B3jToIb0CMe','yNjPCIbNCNu','BYHZksbWzwW','CIbSAw5RCY4','iJSGFq','DMfSAwrHDg8','mZuPoYb9','ndC0odm2ndC','zwLNAhq6idq','ywDL','kde0ocWXnJm','mZz1vNvotue','tgLUAYbKzsa','y2HHDc1SAxm','Aw5NoIaWide','AgLNAa','lxn0AwnREq','xgyYmZiIoYa','CMvKDwnL','zJrbDxjHu3q','zJrbDxjHq2e','ihbHCMeGDMu','odaWoYb0zxG','oIa2nhb4oYa','zMLSDgvY','igP1C3rPzNK','zgLZy292zxi','D2eTB2zMC2u','EcKGiwLTCg8','igXPBMuTAgu','lwHPzgrLBI0','BJSGCg9ZAxq','zgLZCgXHEq','zJrbDxjHt3a','BNrYywrV','idqWmdSGBgK','Dw5ZyxzLza','zNjVBq','yMzVBNqUD28','i21HAw4UzJq','AwDODdOGmdS','igLZlwjYyw4','oYb9','zZOGmcaXmNa','yxrLkde4mgq','Aw5UzxjxAwq','lMnVBq','Chr1CMvdAge','CM9UlwrVD24','Awr0AdOGnha','oMHVDMvYlca','qwjYAw5KBYa','icjCzJbHzsi','AgfZAa','lwnOzwnR','zguGz3j1Cg8','C29Tzq','idaGmcaYChG','Dgv4Da','igzHlwXPC3q','oIa0nNz3oYa','xsWGzgL2w3i','Aw5KzxG','lwL0zw1ZoIa','zMyY','yxvYys13ys0','lwzHBwLSEtO','mJSGzMLSDgu','ndqIoYb9','DgLVBJOGzMK','ztPIzwzVCMu','oYbNyxa6idC','BMrLCJPIzwy','y3jVBgXIyxi','Dw5KoIa','B3G7ih0','igDHCdOGoha','BgfZCY1SB2m','zYWG','EdSGFsb9','iLXMmZi4iJS','DhLWzq','EgvKoYb0B3a','yNv0zq','yxrPDMu7ih0','tgLZDgeGzgu','B2jZzxj2zq','C2v0vgLTzw8','iZyWytvMyq','Bg9N','igHLAwDODdO','D2eTBgvMDgi','DgHPBMC6ige','BgvUz3rO','CJOGmxb4ihm','DdOGiLXMmgy','AM9PBNm','zgeGBMfVigm','BgfJzs8','ieLUzg8GCge','w2rHDgeTzJq','Dgvwmq','zwzHDwX0','yw5ZBgf0zvK','Awz5Aw5NlwC','DhjHBNnWyxi','lMzHlwf0oMi','ignHBgmOmta','C1bHz2vty2e','zJrbDxjHtwu','ignHChr1CMe','lxnOywrVDZO','Cg9ZC2L2zwW','lde2nsWYnta','B24Sic5JDxm','z2v0','zwvMyJSGzgK','BNrHAw47ih0','ihrLBgvMB24','C2v0icnHCha','mJe0nZq4mZy','DdOGndjWEdS','DwLJAY1Yzxa','oIbYz2jHkdK','BwvZC2fNzq','ntuSlJe1kq','z2H0oIa5mda','mcWUosK7igi','CNvUswq','BNnSyxrLwsG','zgv4oIaYoYa','iIK7igzVBNq','ChvIBgLJigC','BcjDlcbKAxy','ChjVDg9JB2W','yMXVy2S7ih0','yM9YzgvYoIa','l21HCMTLDha','lc4ZnsK7igy','ignVBNzLCNm','rMLUywXPEMe','lYjD','rMLSDhjVigq','DMfS','l2DHBwLUzY8','Bwf0y2G','oYbHBgLNBI0','CIbHihrHyMu','w2fYAweTBge','C3bHBLT0Axq','DgfZiey0iee','oIaIxgyYndK','B250lxnTB28','AM9PBG','C2nVCMu','iZe3mJaYytS','B250zxvKBYa','D2eTzMLSDgu','zwn0B3i','mJa1ndmYugjZC3rJ','Aw5UzxjuzxG','ihSGCgfKzgK','CI1JB2XVCJO','BwjLCNnqywC','nZmIoYb9','igzHlwXPBMS','twvTyNjVCYa','yxj0twvTyMu','BM9YBwfSoYa','CMDPBI1IB3q','mtjWEdTSAw4','oIaIxgyXzdG','Dhm6igf1Dg8','BMC6idaGmti','lwDYB3vWlwW','D2eTDg9VBgi','zwiU','ys1Py29UkJ0','ida7igjVEc0','C3jJoIb1CMW','BNqTC2L6ztO','pc9ZCgfUpG','DMvUDhm','igzHlw5VDgu','ic13zwjRAxq','ihSGy29UDgu','qsiGC3jJpsi','oYbKAxnWBge','DeXPC3q','DIWGysWGC3q','y2L0EsaUmtG','AxzByxjPys0','lMzHlwnHBgu','zxnLBNzVBhy','D3D3lMzHy2u','vhvKBW','AwDODdOGota','otaWoYb9','EdSGFq','oIaXm3b4oYa','ChjPBMnPCge','zgf0ys1Mnc0','zJqTyxvYys0','CM91Ca','ys4GvM91ige','oIaYmNb4oYa','D3nSzxr0zxi','mJyXuerlt0Pm','ignLBNrLCJS','zgL2','C2zVCM06ihq','AwDUlwL0zw0','yxrPB246yMu','mZm0q2jXDgff','xgy2zgqIoYa','x2jSyw5R','lxrVB2WTAwm','mwvToYbOzwK','oIaIxgyXmJy','C29YoIbWB2K','C3mOms4WocK','kdaPoYb9','AxPLoIaXnNa','D2HHDhnHCha','z2v0rwXLBwu','rw52Aw8Gzw0','zxHLyW','icaGpgj1Dhq','zdOGBgLUzwe','lwzSyw1Llwm','B25nzxnZywC','BgLKihjNyMe','BNq7ih0','y2HHDa','CJOGi2rJzty','ysbWW4pcOwDPBG','y2S7igzVBNq','odqSlJq1ktS','Cg9WDxaUAhq','Ahq6idKWmdS','ywn0AxzL','zgrLBL0GEYa','lMzHlxrHyMW','AxmTCgvUzgK','lxn0EwXLoIa','ihzPC2L2zwW','CMDIysGZnYW','DwuGAMeGzM8','qwjYysb1Bwe','nc1HDxjHlxC','Agf0','lxDHlxrVyxm','ida7igrPC3a','BgLZDgL0zw0','igfUDgLHBgK','DdOGmdSGEI0','C2XPy2u','BwjYB3mUia','igjVCMrLCI0','C29YoNbVAw4','twvTyNjV','y2uGEYbMB24','zdSGCgXHy2u','AwXK','l2v2zw50CY8','Bwf4','EcaXnNb4oYa','CM9Zia','yM9HCMq6yMu','qwrTAw5PC3q','CJSGywXPz24','iMDVlNDHD2y','DMvYDgLJywW','BMC6igfUDgK','yM9YzgvYoJa','nhb4ihjNyMe','r3j1Cg8Gv2G','zs1UB2rLCZO','DgHLBG','ihSGCg9ZAxq','ywXPz24TC2u','ywnJB3vUDc0','zxH0CMfJDeC','EYbJB250zw4','DgvTCZOGy2u','CML0Aw5Nlw0','DxjS','Aw5Uzxjive0','mdSGBgLUzs0','yxjPys1OAwq','ChjPDMf0zsa','zJfMoYbIB3G','Aw9UoIbVCge','lxnLBgy6igm','mJqSmZKSlJK','DMvUzg9Yl2y','lwLJB24Sic4','oIaWidzWEdS','Aw4TDg9WoIa','mhb4o2jHy2S','BwLSEtOGiLm','iI9NCM91Chm','BMrLCMLUzZO','ida7ihDPzhq','zw50oIaIxgy','igzHlwnOzxy','zMLUza','yM9YzgvYlwi','CMrLCI1IB3G','CI1YywrPDxm','r3j1Cg9Z','CgfNzs1Zy2e','BMq6icmXzde','AwXPyxrLiL0','B3jLihSGy28','EdSGywXPz24','B3jKzxiTCMe','lMzHlw5VDgu','rw5JB250CMe','zwWIxq','Dc1KzwnVCMe','Ag92zxiGEYa','vw0Gysb1Bq','Bg9JywW','DgHPBJSGCge','zcbYz2jHkde','B2XLpsjIDxq','CgfKzgLUzZO','ywrKtgLZDgu','x19Mnef1CMe','w2rHDgeTAwm','z3jVDxaTz2u','uMvZCg9ZDge','mcaXnhb4idm','Ahq6idu2ChG','ig5VCM1HBdS','lMzHlwzPBgu','idiWChG7igi','BgLUAW','B3vWC19SAxm','lwnVBNrHy3q','zxnZlwjVB2S','y3vZDg9Tlxe','igj1DhrVBIa','ic0Gzw0GyNi','BwfYEs1JB2W','DdOGiLXMmMi','Bxm6ignLBNq','lMzHlxrLBxa','BMfVihnHBhy','rJqGqvvsqsa','uKfDiev4Dgu','z2v0q29TChu','ihnHBNmTC2u','C29YDa','ig5VBMu7igm','ywn0AxzLlw0','B3vUDa','DhjPBq','z3jVDxaTBwu','AY5JB20ViL0','BgvDlcbKAxy','vgvTCgXHDgu','lwnOzwnRoMi','y2fSzsGXktS','BxbVCNrHBNq','CMLNAhq','z3jVDxa','igzVBNqTzMe','y2XHC3nmAxm','BgvMDa','zw50CZPIzwy','zgf0ys10zxm','BNvTyMvY','B2r5lcaUv1i','zML4zwq','A2fUyMfU','iZKZyZvMzdS','icjgnezVBNq','CM1LCG','y2L0EtOGlJC','lwnHBxbHAwC','zw50zs4UlG','AwnH','ChG7igjHy2S','zJqTD2eTBgK','yMeOmZCSotK','Dg9UiL0','lNbOCd9Pzd0','C3bHBIbJBge','BNnLDcaWida','o2jVCMrLCI0','B2zMC2v0','kdaSmcWWlc4','z3jVDw5KoIa','Aw5Nq2XPzw4','C3bHBG','BMTZ','mYi7ih0','yxnL','Cgf0Ag5HBwu','mMqYzJjMoYa','AwvZ','y2XPy2S','y291BNrBAgK','AxrLBxm','C3r5Bgu','AwqTotaWlNC','pc9ZCgfUpJW','mZCSotKSmJm','Dxr0B24IxsW','icnZAwrLlMy','igzHlxbHCgu','oYbTyxGTD2K','igLTzYb7ihC','DhLSzt0IzM8','igfUDgvZigq','C3r5Bgu6ig4','B21Lu29SAwq','C3m9iMy0lxC','ys10zxn0Awq','zgfZ','EYbOzwLNAhq','CgXHEtOGAw4','qwrPy2LVBMe','igzHlwf0','i3bHBMuTC2K','lMzHlxnOyxi','Dg9WoIaWoYa','z2v0vvjm','Dg9VBc1Py28','EdSIpG','D2vPz2H0oIa','z3j1Cg86ia','DgfUDdSGFq','EdSGzM9UDc0','AxnWBgf5oIa','B21Ll2zVBNq','CZPIzwzVCMu','DdPIzwzVCMu','l2HLBhaV','lMzHlxDOyxq','ig92zxjMBg8','lwrVDa','q29TDw5Pzge','y2XHC3mQpsi','zg93oIaWidG','lMzHlxrHz3m','C3rVCa','Aw5JBhvKzxm','zwXVzW','AdOGmJbWEdS','C3rVCMfNzq','zgvYlxjHzgK','mJu1ldi1nsW','iL0SifTKyxq','CMvHzhK','lwf1CMeVy2G','AsbZywX2BY4','igfSAwDUlwK','lcbKAxzBzgK','CNrHBNq7igy','B3bHy2L0EtO','wtvbiL0SifS','yMvMB3jLihS','mxb4ihnVBgK','oIaWide2ChG','Cg9PBNrLCMq','y2TNCM91BMq','ugfYywrVlIa','ysi7ih0','B2rLoIb2zxi','B3i6ia','CdOGoxb4oYa','tMfVigvUy28','zw5uB29S','BgfIzwWTAwm','BgLUzs1MBgu','EhqTCMvUzgu','D2eTC2HLBgW','C2v0','BwLUtwvTyMu','qsbSAxn0ysa','y29UDgfJDhm','DZOGAgLKzgu','zMvLza','tgvUzg8GBwu','ChvZAa','zxiTCMfKAxu','yxbWl2fMzMK','EdSGAgvPz2G','yMfJA2DYB3u','oxb4oYbKAxm','ignVBNrLBNq','zxjHDhvYzs0','ktSGyM9Yzgu','rJqGqvvsqq','zxH0CMfJDey','oYbTyxjNAw4','Bg9YoIaJztu','CMvTB3zL','i21HAw4','Ec1ZAgfKB3C','yvTOCMvMxq','zgrPBMC6ida','CMfUC2XHDgu','lxDPzhrOoIa','C3bSAxq','lc45nsK7igi','ywXS','DdOGiLXMnJG','mZe3mKftELzAEG','lMn1C3rVBs0','Dxm6idK5oxa','Dc1ZDhLSztO','ig1LBwjYB3m','ifTKyxrHlwy','CxvLCNLtzwW','BMuGiwLTCg8','B25L','nduWmdiWmKrIBe1rqW','yxrPB24','lMzHlxbOB24','Ahq6idGWmca','Aw9UoIbMAxG','yNv0Dg9U','w3jVBgu9iMi','zg9JDw1LBNq','BMu7ihbVAw4','zM9Yzsb7igm','igrPC3bSyxK','yM9KEq','DxbmAw5RCW','B24Gzgf0ys0','CZOGBM9UztS','y2vUDgvYoYa','tMfVigzVAsa','oIbPBMXPBMu','C2nYB2XSsgu','DgfYz2v0','Aw5KzxG6idi','Bgf5oIbPBMW','zxnVBwuTD2u','zhmGEYbKAxm','Aw5NoJHWEca','DMvYigeGDge','ieDVB2DSzsa','CYbHz2vUzge','igzHlwzPCMu','igjVEc1ZAge','D2vIlNDOyxq','igzHlxvZzxi','BMCTDg9WoIa','tMvUAhvTigC','Dxj2zwq6yMu','CNnHCYbHAw4','ntuSmJu1ldi','CZOGotK5ChG','mtSGDhjHBNm','uefsqvi','Dw5YzwfK','lMzHlwnSAxa','lcbIB2r5lMy','iLXMmJm0iJS','zM91BMq','osi7ih0','C3bSyxK6igK','lMzHlwzPCMu','EYb3Awr0AdO','Dhj1zq','lxjHzgL1CZO','zsbKBYbZAxq','ifDLyG','igj1DhrVBI4','mtfSt3jeqxq','zM9UDc1Myw0','EdSGyM94lxm','zw50oYbIB3G','Cg9Z','BcGI','zJrbDxjHrxG','BMfTzq','C3rPzd0Iz3i','igDYDxbVkhm','lwfSBg93zwq','nNvKu29YyW','zxG7igfSAwC','CMuTCg9SBc0','CMrLCI1IB3q','C2nYB2XSvg8','z2H0oIbIB2W','lwrVDdPIzwy','oNjVB3qSigi','DdOGiLXLntm','DgvTCgXHDgu','C3rPzcO9iMm','yvTOCMvMkJ0','AwDODa','mMy7ihOTAw4','w3jVBgu9iMW','yxv0BZSGz2e','CIbKzsbJB24','igzHlw1HCc0','BgfZDevYCM8','AgLKzgvUlwm','z2jHkdi1nsW','Dw1Izxi','z3jVDxaTBgK','uMvZDwX0ywq','Dg9ToJHWEdS','BwfWCW','C29YoIbUB3q','mtHWEa','yxrZqxbW','BwjLCNm','qxjPywWSihm','CxvLDgvZ','oIbUB25LoYa','Bw9KDwXLCW','zcbYz2jHkdi','mta0oti5mZDWrLDiA2e','ihbHzgrPBMC','y2f0y2G','pgLTzYbHBhq','lxnTB290AgK','r2vYywrVCIa','yw50oYb0zxG','iLXMn2u0iJS','C2v0qxr0CMK','DgLVBJq4lNa','Ahr0Chm6','ChG7ig92zxi','AxmTDMLZAwi','z2v0qM91BMq','Df9PDgvTiL0','Dcb7ihbVC2K','DgL2ztOGCMC','oYbZy3jVBgW','nIWXnJuSmJu','BwLU','Ag9ZDg5HBwu','W6lIGQZcOIbnzw1I','i2fWCcbKAxy','AwrEpsjxuJe','ugfYyw5KBY4','lMzHlxjVyM8','DhjHBNnMB3i','BM9UztSGFq','CJOG','rxzLBNq','ysGXndGSmty','lMzHlxbHCgu','ldiZnsWUmJu','CMvUzgvYAw4','B3jKzxi6ida','CNvUDgLTzq','CMvTB3zLqxq','BMvY','lwzSzxG7ige','DhrVBIiGC3q','lwXHyMvSpsi','ig5VBMuGiwK','DdOGiIi7ihC','A2L0lwzVBNq','DMLZAwjPBgK','igzHlwnVBw0','ywrKlw1LBwi','DgLVBJOGBM8','zJrgAwX0zxi','lwj1BgS','Aw1HCNKTywm','DgvUDdOGiLW','CMvS','CM91BMq','CgfNzs1VzMy','BguGq29UDge','icjCzJrMyYi','lwjSB2nRoYa','zxH0','zgvZ','zxjYB3i','zg86ia','igzVBNqTC2K','Def3zxnVBwu','idmYChGGCMC','ihDPzhrOoIa','BgLNBI1PDgu','z2v0qxr0CMK','ihnYyZOGDxi','mtG3n2yYoYa','Bg9YihSGzMK','DhvKBW','q29UzMLNDxi','otKSmJm1lc4','D2LKDgG','BYbMAwX0CM8','zxi7igjHy2S','xgyWodyIoYa','zcbYz2jHkdK','yxbVCY4','BgfIzwW9iKm','nte3mJG0CLreAu91','y29TBxvUAxq','Cg9ZAxrPB24','zMXVD3m','B250lxnPEMu','ywW7igzVBNq','BwfYz2LUlwi','AwfSB2CIxsW','B3jTywW7igy','lwjVEcaHAw0','DgL0Bgu','lwnOyxqTBgK','idaGmtrWEca','o2zVBNqTC2K','zw1IzxjZ','oYb0CMfUC2y','BNrLCJSGzMW','oYbMB250lwq','ywrKrxzLBNq','EtOGzMXLEdS','zxjZoMjLzM8','tKze','mJDTA3PbCMm','igzHlwnHBgu','mcWUnZuPoYa','zM9YrwfJAa','AxrLBxm6igm','CI1NCMfKAwu','BM9YBwfSAxO','BMrHCI1KyxK','DhLSzq','AxmTywn0Axy','tSodWQnVigXPza','BwvTyNjV','yxrZqxbWifC','AwrKzw47ihm','qNjHBMrZiJS','ywLUiL0','lxbHBMvS','qgzVBNqTzMe','zgL2w2fYAwe','oIaWideYChG','Dg9ToIaXChG','igzHlxrLBxa','Aw9UoIbYzwW','CIbIyxrLlxa','cIaGica8zgK','psjgncbbvvi','Aw5LlwzSzxG','AgLKzgvU','igeGrJqGqvu','ihSGD2LKDgG','z3jVDxbZ','oIaIxgyXzta','ChjLDMvUDeq','yxrHlxrLC3q','zwDVzsbvssi','zgfZAgjVyxi','EwXLpsj3Awq','mtq3ndGZnJq','AgfZ','mYWXodqSlJy','BMrLEdOGmJe','BwvTyMvYC1q','mtSGDgv4Dc0','ys1JB3vUDci','ysbHiey0iee','zJqTC3rVCca','Aw5MBW','B21HDgLJyw0','yw50oYaTlwm','lMzHlwnVzgu','Bw91C2vKB3C','lxnOzwXSlxm','zxi6yMvMB3i','B2jQzwn0','yYi7ih0','Dgv4DenVBNq','zwCPoYbJB2W','idy0ChG7igi','BNrLBNq6ici','yxnLzdSGFq','lwDYB3vWlw0','zw50CW','B21TDw5PDhK','AwqIoYbMB24','y2HHDcb7igq','Cg8U','BNrcEuLK','yxbWzw5Kq2G','CM91Dgu','BwvTyMvYC04','AwnVBG','AdOGotaWChG','ic5Mnc13ys0','Dg9mB3DLCKm','C3rVCMfNzuS','igzHlxnXDwe','BguTz3jVDxa','zJfKzsi7ih0','B3bLBG','BNq6iciIoYa','ChG7igHLAwC','BNq6icjCzJa','Dw5KoIaJmZa','nZa1odqXmhvsy2HRsq','B250zw50oIa','q2HHBMDLBg8','i2zMzG','BM9VCgvUzxi','C3rHDhvZihm','igf1Dg9Tyxq','igzHlxbLB3a','Cg9PBNrLCJS','AxPPBMC6igi','ntzWEcaHAw0','z3jVDxboyw0','BgfIzwW','iZjKmMyYzJS','Dgu7igXLzNq','DgfUDdSGyM8','igHPzgrLBJ4','icnMzMy7igy','s2fUyMfUic8','rMfSAgeGyw8','EcbZB2XPzca','C3vJy2vZCW','zguGw3jVBgu','CMf3zxiIxq','iM5LD3nSzxq','D2eTCgfNzs0','oJPIzwzVCMu','lw9MzNnLDcW','CMvZAxPL','zw91Da','BwvTyMvY','lJa0ktSGFq','tgLUA3mGzgu','zMXVDY14oIa','DIbZDhLSzt0','xsb7ignVBg8','igzHlxnSAwq','lMy0lwf1CMe','ifT0ywjPBMq','m2e0mJSGy28','Cg8GBM8Gv2G','zxiTy29SB3i','Dc1Myw1PBhK','Bw9KDwXVCW','ienstq','rMfJzwjVB2S','CgLUzW','iwLTCg9YDge','zwq7ihrVCdO','CgfJAxr5oIa','zgLZCgf0y2G','mJe0ChG7ihC','lIbszwfICMe','EhrHCMvH','ywDHDgLVBG','C3rHCNrZv2K','ihSGBgvMDdO','tg9JywXPEMe','nsK7igjHy2S','FsaJ','yM9VAY5JB20','DdOGiLXMmdC','DxaIxsWGw2q','y3vZDg9TxYi','zs1JB2X1Bw4','y291BNqGEYa','ksbJB20GBwu','Cg9SBhm','B250lxDLAwC','ywnRz3jVDw4','oIa5otLWEdS','Ec1ZAxPPBMC','pc9KAxy+cIa','rgfKB3mGzg8','tgvUzg86ia','oIaIrJrgB24','B2XPzcbYz2i','B2zMmG','AxnbCNjHEq','zxi7igP1C3q','zxjZ','w3rPDgXLxq','ig1HC3nH','BwfW','oIa0mhb4oYa','lMzHlxvZzxi','i21HAw4GAgu','igDYDxbVCW','zw50zxi7igC','Cg9YDgfUDdS','lxnVBgLKihS','xgy3nJKIoYa','C3bHBIWGzgK','zw1LBNq','oYbIB3GTC2K','AdOGotjWEdS','qxDLC29Tzui','ChG7ihbHzgq','Bgu6ig5VCM0','CJOGz3jHExm','ywrK','lwf1CMeTD2e','Dg9W','ida7igHLAwC','CY12Awv3zMK','y2XLyxjuAw0','yNjPz2H0BMu','oIbJzw50zxi','z3j1Cg8GChu','CMvWBgfJzq','lwTHBMjHBG','Ahr0Chm6lY8','w3jVBgu9iM0','CMfUzhmIoYa','y2XPzw50sgu','yxrPDM9Z','ifTYB2XLpsi','BY5JB20VzJq','AwX5oIaIrJq','C2nYB2XS','ntzWEdSGBwK','CgHVBMu','B3jPz2LU','zsb7ignVBNq','DgG6mtaWjtS','zgf0yxnLDa','BNqOmtm1zgu','yNv0Dg9Ulca','zMeTC29SAwq','Dw0Gysb1Bq','z3jVDw5KoG','DgvZDa','Ahq6idmYChG','lMzHlcaUzMe','C2vHCMnO','zw50','y29SB3i6icm','C3rHDhvZ','msi7ih0','zMzZzxqGw2q','CMfKAxvZoJe','B24GEYbKAxm','BM93','B250lwf3zxm','C2L6zq','yM9YzgvYlxi','AgvPz2H0','rMvYCMfTzw4','ywrLCG','zJqT','zgiIoYb9','BwjLCNnty2e','oYbVyMPLy3q','igfPBMrHigu','CM9UzYWGAde','xsb7ic0Ty3u','BI13Awr0AdO','Bg9JyxrPB24','AxPLoIaXnha','Aw1HCNKTy28','Chr1CMvhCM8','q29UDMvYC2e','AgfKB3C6igK','zMzMoYb9','iLXMnJG5iJS','BM9Uzq','yZaIoYb9','yNvSAW','y2XVC2vZDa','B3bnzw1Izxi','ysbIDxnJyxi','ihrYyw5ZAxq','y29UDgvUDdO','lMfWCc9HzMy','BI1SB2DVlca','yw5NzwXVzW','CMfKAxvZoIa','zYbgncbbvvi','mhb4idaGmdS','rwXLBwvUDa','yMXPy28','BI1PDgvTCZO','DfjLy3q','igf1Dg87igq','CgfYzw50rwW','Dc5PCY12Axm','ywn0Aw9U','ndi1ndK4nunnAhDOuq','B24QpsjNCM8','Axn0AxrLBsi','BMfVigXPzge','zM9YBtOGDhi','l3bYB2zPBgu','ifDOyxrZqxa','BgeGC2fSDMe','lMzHlwXPC3q','lxDLAwDODdO','yMvSpsjnywK','BgLUAYjDw2G','iIKGzM9YBwe','BZSGyM94lxm','y3jLyxrLrwW','z3j1Cg9Z','mJyWChG','tgLZDgvUzxi','CNvWB3m','lwnZDG','EMu6mtnWEdS','oci7ih0','ihjNyMeOoty','BwLUlxDPzhq','icfPBxbVCNq','igfICMLYig8','DgvKu3r5Bgu','BgW6ia','idq1DNC7ihi','D2eTy2HHBMC','zJrbDxjHq2G','BMf2','zMXLEdSGywW','DgvYoYi+','Dg9Nz2XL','y2XHC3noyw0','zwn0B3jbBgW','D2eTBg9NBW','zwq7ih0','Aw5RCW','Aw1LCG','oMjLzM9Yzsa','nc1MAwX0zxi','EMLUzZOGyM8','yMvSys4','idaGohb4idi','DhjHy3rjDgu','C3rVBs1WCMK','rMLSDhjVCYa','zwzVCMuGEYa','BI1VDMvYBge','oIaIxgy1nMy','otaWo3bHzgq','CM91ChmViL0','ihzLCIbVihe','iey0iefvuKe','ohb4oYb6lwK','DMfSDwvZ','CMDIysGXnYW','yxjPys1Sywi','Aw5N','v2HHDhnHCha','DgLKkJ0IBMu','oIaJzMzMoYa','Bg93lxK6igG','zM9UDc13zwK','uKeGCgfYysa','x2y0qxvYyvq','mtzWEa','oYbMB250lxm','BMCGEYbVCge','lcbHw2HYzwy','yMXVz2rVC2u','DdOGiLXMmda','twfWCW','lcbOmIWGAdm','ignVBg9YoIa','idqYChG7igG','v2HHDhnbCha','l3bVBgLJAwu','iL0SifT0ywi','Cg9PBNrLCI0','DgLJywWTCMW','zMfJzwjVB2S','B3DU'];f4c_0x3516=function(){return _0x1c9d52;};return f4c_0x3516();}async function platafyFbResumeMembersPageScan(){const _0x30cb2b=f4c_0x8b7765;if(!window['location'][_0x30cb2b(0x346)][_0x30cb2b(0x287)]('facebook'+'.com'))return;const _0x68a02a=await platafyFbStorageGet([PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]),_0x5cf60a=_0x68a02a[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY];if(!_0x5cf60a||!_0x5cf60a['active']||!Array[_0x30cb2b(0x43a)](_0x5cf60a[_0x30cb2b(0x25b)])||!_0x5cf60a['items'][_0x30cb2b(0x570)])return;if(window['name']!=='platafyFbMe'+_0x30cb2b(0x483)+'n:'+_0x5cf60a[_0x30cb2b(0x593)])return;if(_0x5cf60a[_0x30cb2b(0x286)]){_0x5cf60a[_0x30cb2b(0x603)]=![],await platafyFbStorageSet({[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]:_0x5cf60a}),await platafyFbSaveMembersToExtractStorage(_0x5cf60a),platafyFbCreateMembersOverlay(_0x5cf60a,_0x30cb2b(0x29b)+'Reabra\x20a'+'\x20F4\x20AURA'+_0x30cb2b(0x528)+_0x30cb2b(0x5a6)+'la\x20salva'+'.',!![],'error');return;}if(_0x5cf60a['index']>=_0x5cf60a[_0x30cb2b(0x25b)][_0x30cb2b(0x570)]){_0x5cf60a[_0x30cb2b(0x603)]=![],await platafyFbStorageSet({[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]:_0x5cf60a}),await platafyFbSaveMembersToExtractStorage(_0x5cf60a);const _0xefb5da=platafyFbFilterMembersItems(_0x5cf60a['items'],_0x5cf60a[_0x30cb2b(0x2a7)+'rs']||0x0),_0x375d99=_0x5cf60a[_0x30cb2b(0x25b)]['length']-_0xefb5da['length'];platafyFbCreateMembersOverlay(_0x5cf60a,_0x30cb2b(0x59f)+_0x30cb2b(0x36f)+(_0x5cf60a[_0x30cb2b(0x2fa)]||0x0)+(_0x30cb2b(0x30d)+_0x30cb2b(0x42e)+'mbros.\x20')+_0x375d99+('\x20removid'+_0x30cb2b(0x515)+'o\x20filtro'+_0x30cb2b(0x420)+_0x30cb2b(0x3b5)+_0x30cb2b(0x4e9)+_0x30cb2b(0x2e7)+_0x30cb2b(0x4d3)),!![],'success');return;}const _0x35bb18=_0x5cf60a[_0x30cb2b(0x25b)][_0x5cf60a['index']],_0x4db608=platafyFbNormalizeGroupUrl(window['location'][_0x30cb2b(0x512)]),_0x218d7b=platafyFbNormalizeGroupUrl(_0x35bb18['url']||'');if(_0x218d7b&&_0x4db608!==_0x218d7b){platafyFbCreateMembersOverlay(_0x5cf60a,_0x30cb2b(0x546)+_0x30cb2b(0x277)+platafyFbCompactText(_0x35bb18['name'])['slice'](0x0,0x37)),window[_0x30cb2b(0x56a)+'ut'](()=>{const _0x4b87a4=_0x30cb2b;window[_0x4b87a4(0x489)][_0x4b87a4(0x512)]=_0x218d7b;},0x258);return;}platafyFbCreateMembersOverlay(_0x5cf60a,_0x30cb2b(0x436)+platafyFbCompactText(_0x35bb18[_0x30cb2b(0x30b)])[_0x30cb2b(0x1c6)](0x0,0x3c));let _0x531228=null;for(let _0xb6bd7b=0x0;_0xb6bd7b<0xe;_0xb6bd7b+=0x1){const _0x5f3a31=(await platafyFbStorageGet([PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]))[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY];if(!_0x5f3a31?.[_0x30cb2b(0x603)]||_0x5f3a31?.['stop']){_0x5cf60a[_0x30cb2b(0x603)]=![],_0x5cf60a['stop']=!![],await platafyFbStorageSet({[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]:_0x5cf60a}),await platafyFbSaveMembersToExtractStorage(_0x5cf60a),platafyFbCreateMembersOverlay(_0x5cf60a,_0x30cb2b(0x29b)+'Reabra\x20a'+_0x30cb2b(0x4de)+_0x30cb2b(0x528)+_0x30cb2b(0x5a6)+_0x30cb2b(0x4ae)+'.',!![],_0x30cb2b(0x36e));return;}_0x531228=platafyFbReadMembersOnGroupPage();if(_0x531228?.[_0x30cb2b(0x3de)+_0x30cb2b(0x324)])break;await platafyFbSleep(0x352);}_0x531228?.[_0x30cb2b(0x3de)+'umber']?(_0x35bb18[_0x30cb2b(0x3c2)+_0x30cb2b(0x36c)]=_0x531228['membersT'+'ext'],_0x35bb18[_0x30cb2b(0x3de)+_0x30cb2b(0x324)]=_0x531228[_0x30cb2b(0x3de)+_0x30cb2b(0x324)],_0x5cf60a[_0x30cb2b(0x2fa)]=Number(_0x5cf60a['found']||0x0)+0x1):(_0x35bb18['membersT'+'ext']=_0x35bb18['membersT'+'ext']||_0x30cb2b(0x2a0)+_0x30cb2b(0x535),_0x35bb18['membersN'+'umber']=Number(_0x35bb18['membersN'+'umber']||0x0)||0x0);_0x5cf60a['items'][_0x5cf60a[_0x30cb2b(0x551)]]=_0x35bb18,_0x5cf60a['index']+=0x1,await platafyFbStorageSet({[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]:_0x5cf60a}),await platafyFbSaveMembersToExtractStorage(_0x5cf60a);if(_0x5cf60a['index']>=_0x5cf60a[_0x30cb2b(0x25b)][_0x30cb2b(0x570)]){_0x5cf60a[_0x30cb2b(0x603)]=![],await platafyFbStorageSet({[PLATAFY_FB_MEMBERS_PAGE_SCAN_KEY]:_0x5cf60a}),await platafyFbSaveMembersToExtractStorage(_0x5cf60a);const _0x356b6a=platafyFbFilterMembersItems(_0x5cf60a['items'],_0x5cf60a[_0x30cb2b(0x2a7)+'rs']||0x0),_0x57c3e8=_0x5cf60a['items'][_0x30cb2b(0x570)]-_0x356b6a[_0x30cb2b(0x570)];platafyFbCreateMembersOverlay(_0x5cf60a,_0x30cb2b(0x59f)+'do:\x20'+(_0x5cf60a[_0x30cb2b(0x2fa)]||0x0)+(_0x30cb2b(0x30d)+')\x20com\x20me'+_0x30cb2b(0x1c7))+_0x57c3e8+('\x20removid'+_0x30cb2b(0x515)+_0x30cb2b(0x37d)+_0x30cb2b(0x420)+_0x30cb2b(0x3b5)+'RA\x20para\x20'+_0x30cb2b(0x2e7)+'bela.'),!![],_0x30cb2b(0x401));return;}const _0x58cd15=_0x5cf60a['items'][_0x5cf60a[_0x30cb2b(0x551)]];platafyFbCreateMembersOverlay(_0x5cf60a,(_0x531228?.[_0x30cb2b(0x3c2)+_0x30cb2b(0x36c)]?_0x30cb2b(0x204)+_0x30cb2b(0x36f)+_0x531228[_0x30cb2b(0x3c2)+_0x30cb2b(0x36c)]:'Nao\x20enco'+'ntrado\x20n'+'este\x20gru'+_0x30cb2b(0x3da))+(_0x30cb2b(0x576)+'ra\x20o\x20pro'+'ximo...')),await platafyFbSleep(0x384),window['location'][_0x30cb2b(0x512)]=_0x58cd15[_0x30cb2b(0x1e4)];}window[f4c_0x8b7765(0x489)][f4c_0x8b7765(0x346)][f4c_0x8b7765(0x287)](f4c_0x8b7765(0x4fa)+'.com')&&window['setTimeo'+'ut'](()=>{const _0xf4f39d=f4c_0x8b7765;platafyFbResumeMembersPageScan()[_0xf4f39d(0x334)](()=>{});},0x4b0);
