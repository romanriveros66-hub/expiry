/**
 * Sistema de Alerta de Vencimientos - Expiry
 * Lógica SPA, Seguridad OWASP/VulnScope, Carga Rápida y Semáforo Dinámico
 */

const App = (() => {
  // Estado de la aplicación
  const state = {
    user: null,
    config: {
      nombre_negocio: 'Minimarket & Despensa Express',
      moneda_simbolo: 'Gs.',
      dias_alerta_amarillo: 30,
      dias_alerta_rojo: 7
    },
    csrfToken: '',
    authToken: localStorage.getItem('expiry_auth_token') || '',
    useRemoteApi: localStorage.getItem('expiry_use_remote_api') === 'true',
    remoteApiBase: 'https://expiry-gules.vercel.app',
    filtroColor: 'ALL',
    busqueda: '',
    lotes: [],
    resumen: {},
    pinPendingAction: null // { accion, loteId, extraData }
  };

  // 1. Fetch Seguro con CSRF, Bearer Token y CORS para localhost <-> https://expiry-gules.vercel.app/
  async function safeFetchJson(url, options = {}) {
    const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    
    // Si estamos en localhost y useRemoteApi está activo, apuntar a https://expiry-gules.vercel.app
    let targetUrl = url;
    if (isLocalhost && state.useRemoteApi && !url.startsWith('http')) {
      targetUrl = state.remoteApiBase.replace(/\/$/, '') + '/' + url.replace(/^\//, '');
    }

    const headers = options.headers || {};
    if (state.csrfToken) {
      headers['X-CSRF-Token'] = state.csrfToken;
    }
    if (state.authToken) {
      headers['Authorization'] = 'Bearer ' + state.authToken;
      headers['X-Auth-Token'] = state.authToken;
    }

    try {
      const fetchOptions = {
        ...options,
        headers,
        credentials: 'include' // Para enviar cookies de sesión entre orígenes
      };
      const response = await fetch(targetUrl, fetchOptions);
      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseErr) {
        console.error('Non-JSON response from server:', text);
        throw new Error('Respuesta del servidor (' + response.status + '): ' + (text.replace(/<[^>]*>?/gm, '').substring(0, 100) || 'Error'));
      }

      if (data && data.csrf_token) {
        state.csrfToken = data.csrf_token;
      }
      if (data && data.token) {
        state.authToken = data.token;
        localStorage.setItem('expiry_auth_token', data.token);
      }

      if (!response.ok && data && data.code === 'UNAUTHORIZED') {
        state.user = null;
        showLandingView();
        showToast('Sesión finalizada. Inicie sesión nuevamente.', 'error');
        throw new Error(data.message || 'No autorizado');
      }

      return data;
    } catch (err) {
      console.error('Fetch Error:', err);
      throw err;
    }
  }

  // 2. Sistema de Notificaciones Toast
  function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-triangle';

    toast.innerHTML = `<i class="fas ${icon}"></i><span>${escapeHTML(message)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }

  function escapeHTML(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatMoney(amount) {
    const num = parseFloat(amount) || 0;
    return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(num) + ' ' + (state.config.moneda_simbolo || 'Gs.');
  }

  // 3. Inicialización de la Aplicación
  async function init() {
    renderApiSwitchBadge();
    setupEventListeners();
    await checkAuthStatus();
    setupSimulator();
  }

  async function checkAuthStatus() {
    try {
      const res = await safeFetchJson('api/auth.php?action=check');
      if (res.status === 'success') {
        state.csrfToken = res.csrf_token;
        if (res.config) state.config = res.config;
        if (res.authenticated && res.user) {
          state.user = res.user;
          showAppView();
          await reloadDashboard();
        } else {
          showLandingView();
        }
      }
    } catch (e) {
      showLandingView();
    }
  }

  // 4. Cambio de Vistas (Landing vs App)
  function showLandingView() {
    document.getElementById('viewLanding').classList.remove('hidden');
    document.getElementById('viewApp').classList.add('hidden');
  }

  function showAppView() {
    document.getElementById('viewLanding').classList.add('hidden');
    document.getElementById('viewApp').classList.remove('hidden');
    renderUserBar();
  }

  function renderUserBar() {
    const brandName = document.getElementById('appBrandName');
    const userName = document.getElementById('appUserName');
    const roleBadge = document.getElementById('appRoleBadge');

    if (brandName) brandName.textContent = state.config.nombre_negocio || 'Minimarket & Despensa';
    if (userName && state.user) userName.textContent = state.user.nombre || state.user.usuario;
    if (roleBadge && state.user) {
      roleBadge.textContent = state.user.rol === 'admin' ? 'Dueño / Admin' : 'Cajero / Empleado';
      roleBadge.className = `user-role-badge ${state.user.rol === 'admin' ? 'role-admin' : 'role-empleado'}`;
    }
  }

  // 5. Carga y Renderizado del Semáforo y KPIs
  async function reloadDashboard() {
    try {
      let url = `api/semaforo.php?color=${encodeURIComponent(state.filtroColor)}`;
      if (state.busqueda) {
        url += `&search=${encodeURIComponent(state.busqueda)}`;
      }

      const res = await safeFetchJson(url);
      if (res.status === 'success') {
        state.resumen = res.resumen;
        state.lotes = res.lotes;
        renderKPIs(res.resumen);
        renderSemaforo(res.lotes);
        populateCategorySelect(res.categorias);
      }
    } catch (e) {
      console.error('Error cargando semáforo:', e);
    }
  }

  function getProductIcon(nombre, categoria) {
    const text = (nombre + ' ' + (categoria || '')).toLowerCase();
    if (text.includes('leche')) return '🥛';
    if (text.includes('yogur')) return '🥣';
    if (text.includes('gallet') || text.includes('oreo')) return '🍪';
    if (text.includes('pan')) return '🍞';
    if (text.includes('queso') || text.includes('fiambre') || text.includes('salchicha')) return '🧀';
    if (text.includes('gaseosa') || text.includes('coca') || text.includes('jugo')) return '🥤';
    if (text.includes('atun') || text.includes('enlatado')) return '🥫';
    return '📦';
  }

  // Formatea 'YYYY-MM-DD' o timestamp al estándar estructurado 'DD/MM/AAAA'
  function formatDateDMY(dateStr) {
    if (!dateStr) return '-';
    const clean = String(dateStr).split(' ')[0];
    const parts = clean.split('-');
    if (parts.length === 3) {
      const year = parts[0];
      const month = parts[1].padStart(2, '0');
      const day = parts[2].padStart(2, '0');
      return `${day}/${month}/${year}`;
    }
    return dateStr;
  }

  // Genera datos estructurados y textos explicativos en español para el semáforo
  function getVencimientoDetalle(lote) {
    const dias = parseInt(lote.dias_restantes, 10);
    const fechaEstructurada = formatDateDMY(lote.fecha_vencimiento);

    if (isNaN(dias)) {
      return {
        fecha: fechaEstructurada,
        badgeText: lote.color_semaforo || 'VERDE',
        badgeClass: 'badge-pill-green',
        subTexto: 'Vigente',
        subClass: 'desc-verde'
      };
    }

    if (dias < 0) {
      const absDias = Math.abs(dias);
      return {
        fecha: fechaEstructurada,
        badgeText: 'VENCIDO',
        badgeClass: 'badge-pill-red',
        subTexto: `Venció hace ${absDias} día${absDias === 1 ? '' : 's'}`,
        subClass: 'desc-vencido'
      };
    } else if (dias === 0) {
      return {
        fecha: fechaEstructurada,
        badgeText: 'ROJO',
        badgeClass: 'badge-pill-red',
        subTexto: '¡Vence hoy!',
        subClass: 'desc-rojo'
      };
    } else if (lote.color_semaforo === 'ROJO' || dias <= 7) {
      return {
        fecha: fechaEstructurada,
        badgeText: 'ROJO',
        badgeClass: 'badge-pill-red',
        subTexto: `Vence en ${dias} día${dias === 1 ? '' : 's'}`,
        subClass: 'desc-rojo'
      };
    } else if (lote.color_semaforo === 'AMARILLO' || dias <= 30) {
      return {
        fecha: fechaEstructurada,
        badgeText: 'AMARILLO',
        badgeClass: 'badge-pill-orange',
        subTexto: `Alerta: ${dias} días restantes`,
        subClass: 'desc-amarillo'
      };
    } else {
      return {
        fecha: fechaEstructurada,
        badgeText: 'VERDE',
        badgeClass: 'badge-pill-green',
        subTexto: `Vigente (${dias} días)`,
        subClass: 'desc-verde'
      };
    }
  }

  function renderKPIs(kpis) {
    const kpiTotal = document.getElementById('kpiTotalCount');
    const kpiRojo = document.getElementById('kpiRojoCount');
    const kpiVencido = document.getElementById('kpiVencidoCount');

    if (kpiTotal) kpiTotal.textContent = kpis.total_lotes_activos || kpis.total_productos_en_seguimiento || '0';
    if (kpiRojo) kpiRojo.textContent = kpis.total_lotes_rojos || '0';
    if (kpiVencido) kpiVencido.textContent = kpis.total_lotes_vencidos || '0';
  }

  function renderSemaforo(lotes) {
    const tbody = document.getElementById('semaforoTableBody');
    const mobileContainer = document.getElementById('semaforoMobileCards');
    const emptyState = document.getElementById('semaforoEmptyState');

    if (!tbody || !mobileContainer) return;

    if (!lotes || lotes.length === 0) {
      tbody.innerHTML = '';
      mobileContainer.innerHTML = '';
      if (emptyState) emptyState.classList.remove('hidden');
      return;
    }

    if (emptyState) emptyState.classList.add('hidden');

    // Tabla de Escritorio (Fidedigna a la imagen de referencia)
    tbody.innerHTML = lotes.map(lote => {
      const iconEmoji = getProductIcon(lote.producto_nombre, lote.categoria_nombre);
      const det = getVencimientoDetalle(lote);
      const fechaCargaEstructurada = formatDateDMY(lote.fecha_ingreso || new Date().toISOString().slice(0, 10));
      const montoTotal = parseFloat(lote.dinero_en_riesgo) > 0 
        ? formatMoney(lote.dinero_en_riesgo) 
        : (parseFloat(lote.precio_costo) > 0 ? formatMoney(lote.precio_costo * (lote.cantidad_actual || 1)) : '');
      const stockInfo = `${lote.cantidad_actual || 1} un.`;

      return `
        <tr>
          <td>
            <div class="product-cell-flex">
              <span class="product-item-img">${iconEmoji}</span>
              <div>
                <span class="product-name-bold">${escapeHTML(lote.producto_nombre)}</span>
                <span class="product-sub-code">
                  ${escapeHTML(lote.codigo_barra || lote.codigo_lote || '')}
                  ${montoTotal ? ` &bull; <strong style="color: #0f172a;">${montoTotal}</strong> (${stockInfo})` : ` &bull; ${stockInfo}`}
                </span>
              </div>
            </div>
          </td>
          <td class="cell-fecha-carga">
            ${fechaCargaEstructurada}
          </td>
          <td>
            <span class="badge-pill-status ${det.badgeClass}">${det.badgeText}</span>
          </td>
          <td>
            <div class="date-venc-box">
              <span class="date-venc-primary">${det.fecha}</span>
              <span class="date-venc-desc ${det.subClass}">${escapeHTML(det.subTexto)}</span>
            </div>
          </td>
          <td style="text-align: center;">
            <div class="table-action-btn-group" style="justify-content: center;">
              <button class="btn-action-square btn-action-edit" onclick="App.triggerEditarFecha(${lote.lote_id}, '${lote.fecha_vencimiento}', '${escapeHTML(lote.producto_nombre)}')" title="Editar Fecha de Vencimiento (PIN Admin)">
                <i class="fas fa-pencil-alt"></i>
              </button>
              <button class="btn-action-square btn-action-delete" onclick="App.triggerEliminarLote(${lote.lote_id}, '${escapeHTML(lote.producto_nombre)}')" title="Eliminar Lote (PIN Admin)">
                <i class="fas fa-trash-alt"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    // Tarjetas para Dispositivos Móviles (100% Adaptado a Celular)
    mobileContainer.innerHTML = lotes.map(lote => {
      const iconEmoji = getProductIcon(lote.producto_nombre, lote.categoria_nombre);
      const det = getVencimientoDetalle(lote);
      const fechaCargaEstructurada = formatDateDMY(lote.fecha_ingreso || new Date().toISOString().slice(0, 10));
      const montoTotal = parseFloat(lote.dinero_en_riesgo) > 0 
        ? formatMoney(lote.dinero_en_riesgo) 
        : (parseFloat(lote.precio_costo) > 0 ? formatMoney(lote.precio_costo * (lote.cantidad_actual || 1)) : '');
      const stockInfo = `${lote.cantidad_actual || 1} un.`;

      return `
        <div class="mobile-lote-card">
          <div class="mobile-lote-card-header">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span class="product-item-img" style="font-size: 1.1rem;">${iconEmoji}</span>
              <div>
                <strong style="font-size: 0.95rem; color: var(--text-title);">${escapeHTML(lote.producto_nombre)}</strong>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                  ${escapeHTML(lote.codigo_barra || '')}
                  ${montoTotal ? ` &bull; <strong style="color: #0f172a;">${montoTotal}</strong> (${stockInfo})` : ''}
                </div>
              </div>
            </div>
            <span class="badge-pill-status ${det.badgeClass}">${det.badgeText}</span>
          </div>
          <div class="mobile-card-dates-box">
            <div class="mobile-date-row">
              <span class="mobile-date-label">Fecha de carga:</span>
              <span class="mobile-date-value">${fechaCargaEstructurada}</span>
            </div>
            <div class="mobile-date-row">
              <span class="mobile-date-label">Fecha de vencimiento:</span>
              <div>
                <span class="mobile-date-value">${det.fecha}</span>
                <span class="date-venc-desc ${det.subClass}" style="margin-left: 4px;">(${escapeHTML(det.subTexto)})</span>
              </div>
            </div>
          </div>
          <div class="mobile-lote-actions">
            <button class="btn-action-square btn-action-edit" onclick="App.triggerEditarFecha(${lote.lote_id}, '${lote.fecha_vencimiento}', '${escapeHTML(lote.producto_nombre)}')">
              <i class="fas fa-pencil-alt"></i>
            </button>
            <button class="btn-action-square btn-action-delete" onclick="App.triggerEliminarLote(${lote.lote_id}, '${escapeHTML(lote.producto_nombre)}')">
              <i class="fas fa-trash-alt"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  function populateCategorySelect(cats) {
    const select = document.getElementById('quickCategoria');
    if (!select || !cats) return;
    const currentVal = select.value;
    select.innerHTML = '<option value="">Seleccione Categoría...</option>' + cats.map(c => 
      `<option value="${c.id}">${escapeHTML(c.nombre)}</option>`
    ).join('');
    if (currentVal) select.value = currentVal;
  }

  // 6. Carga Rápida de Mercadería
  async function handleQuickEntrySubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    submitBtn.disabled = true;

    try {
      const res = await safeFetchJson('api/carga_rapida.php', {
        method: 'POST',
        body: formData
      });

      if (res.status === 'success') {
        showToast(res.message, 'success');
        form.reset();
        const hint = document.getElementById('quickVencimientoFeedback');
        if (hint) {
          hint.textContent = 'Formato estructurado: DD/MM/AAAA';
          hint.className = 'expiry-preview-hint';
        }
        document.getElementById('quickCodigoBarra').focus();
        await reloadDashboard();
      } else {
        showToast(res.message || 'Error al guardar', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error de conexión con el servidor', 'error');
    } finally {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  }

  // 7. Búsqueda Instantánea de Código de Barra
  let barcodeTimeout = null;
  function handleBarcodeInput(val) {
    clearTimeout(barcodeTimeout);
    if (!val || val.length < 3) return;

    barcodeTimeout = setTimeout(async () => {
      try {
        const res = await safeFetchJson(`api/productos.php?term=${encodeURIComponent(val)}`);
        if (res.status === 'success' && res.encontrado && res.producto) {
          const prod = res.producto;
          document.getElementById('quickNombre').value = prod.nombre;
          if (prod.categoria_id) document.getElementById('quickCategoria').value = prod.categoria_id;
          if (prod.precio_costo) {
            const precioInput = document.getElementById('quickPrecioCosto');
            if (precioInput) precioInput.value = prod.precio_costo;
          }
          showToast(`Producto detectado: ${prod.nombre}`, 'info');
        }
      } catch (e) {}
    }, 350);
  }

  // Botones Rápidos de Fecha (+3d, +7d, +15d, etc.)
  function setQuickDays(days) {
    const dateInput = document.getElementById('quickVencimiento');
    if (!dateInput) return;
    const target = new Date();
    target.setDate(target.getDate() + days);
    const yyyy = target.getFullYear();
    const mm = String(target.getMonth() + 1).padStart(2, '0');
    const dd = String(target.getDate()).padStart(2, '0');
    dateInput.value = `${yyyy}-${mm}-${dd}`;
    showToast(`Fecha fijada para dentro de ${days} días`, 'info');
  }

  // 8. Flujo de Seguridad con PIN de Administrador (Keypad Numérico)
  let enteredPin = '';

  function openPinModal(actionData) {
    state.pinPendingAction = actionData;
    enteredPin = '';
    updatePinDots();
    document.getElementById('pinActionLabel').textContent = actionData.titulo || 'Autorización de Administrador';
    document.getElementById('pinModal').classList.remove('hidden');
  }

  function closePinModal() {
    document.getElementById('pinModal').classList.add('hidden');
    state.pinPendingAction = null;
    enteredPin = '';
  }

  function enterPinDigit(digit) {
    if (enteredPin.length < 6) {
      enteredPin += digit;
      updatePinDots();
      if (enteredPin.length >= 4) {
        // Auto-verificar cuando llega a 4 o dar submit
      }
    }
  }

  function deletePinDigit() {
    if (enteredPin.length > 0) {
      enteredPin = enteredPin.slice(0, -1);
      updatePinDots();
    }
  }

  function updatePinDots() {
    const dots = document.querySelectorAll('#pinModal .pin-dot');
    dots.forEach((dot, index) => {
      if (index < enteredPin.length) {
        dot.classList.add('filled');
      } else {
        dot.classList.remove('filled');
      }
    });
  }

  async function submitPinAction() {
    if (enteredPin.length < 4) {
      showToast('Por favor ingrese al menos 4 dígitos del PIN', 'error');
      shakePinModal();
      return;
    }

    if (!state.pinPendingAction) return;

    const action = state.pinPendingAction;
    const formData = new FormData();
    formData.append('accion', action.accion);
    formData.append('lote_id', action.loteId);
    formData.append('admin_pin', enteredPin);
    if (action.motivo) formData.append('motivo', action.motivo);
    if (action.nuevaFecha) formData.append('nueva_fecha', action.nuevaFecha);

    try {
      const res = await safeFetchJson('api/acciones_lote.php', {
        method: 'POST',
        body: formData
      });

      if (res.status === 'success') {
        showToast(res.message, 'success');
        closePinModal();
        await reloadDashboard();
      } else {
        showToast(res.message || 'PIN Incorrecto', 'error');
        shakePinModal();
        enteredPin = '';
        updatePinDots();
      }
    } catch (e) {
      showToast('PIN Incorrecto o Error de Autorización', 'error');
      shakePinModal();
      enteredPin = '';
      updatePinDots();
    }
  }

  function shakePinModal() {
    const card = document.querySelector('#pinModal .modal-card');
    if (card) {
      card.classList.add('shake');
      setTimeout(() => card.classList.remove('shake'), 450);
    }
  }

  // Triggers de Acciones
  function triggerEliminarLote(loteId, nombre) {
    openPinModal({
      accion: 'eliminar_lote',
      loteId: loteId,
      titulo: `Eliminar lote de: ${nombre}`,
      motivo: 'Eliminación autorizada con PIN'
    });
  }

  // Cálculo estructurado de metadatos de expiración y estado del semáforo
  function calculateExpiryMetadata(isoDateStr) {
    if (!isoDateStr) return null;
    const parts = isoDateStr.split('-');
    if (parts.length !== 3) return null;
    
    const formatted = `${parts[2]}/${parts[1]}/${parts[0]}`;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    const diffDays = Math.round((target - today) / (1000 * 60 * 60 * 24));
    
    let texto = '';
    let cls = 'hint-green';
    let colorSemaforo = 'VERDE';

    if (diffDays < 0) {
      const abs = Math.abs(diffDays);
      texto = `Venció hace ${abs} día${abs === 1 ? '' : 's'} (Estado: VENCIDO)`;
      cls = 'hint-red';
      colorSemaforo = 'VENCIDO';
    } else if (diffDays === 0) {
      texto = '¡Vence hoy! (Estado: ROJO 🔴)';
      cls = 'hint-red';
      colorSemaforo = 'ROJO';
    } else if (diffDays <= 7) {
      texto = `Vence en ${diffDays} día${diffDays === 1 ? '' : 's'} (Estado: ROJO 🔴)`;
      cls = 'hint-red';
      colorSemaforo = 'ROJO';
    } else if (diffDays <= 30) {
      texto = `Alerta: ${diffDays} días restantes (Estado: AMARILLO 🟡)`;
      cls = 'hint-orange';
      colorSemaforo = 'AMARILLO';
    } else {
      texto = `Vigente: ${diffDays} días restantes (Estado: VERDE 🟢)`;
      cls = 'hint-green';
      colorSemaforo = 'VERDE';
    }

    return { formatted, diffDays, texto, cls, colorSemaforo };
  }

  function setQuickExpiryDays(days) {
    const target = new Date();
    target.setDate(target.getDate() + parseInt(days, 10));
    const yyyy = target.getFullYear();
    const mm = String(target.getMonth() + 1).padStart(2, '0');
    const dd = String(target.getDate()).padStart(2, '0');
    const iso = `${yyyy}-${mm}-${dd}`;
    const input = document.getElementById('quickVencimiento');
    if (input) {
      input.value = iso;
      onExpiryInputChange(iso);
    }
  }

  function onExpiryInputChange(isoDateStr) {
    const hint = document.getElementById('quickVencimientoFeedback');
    if (!hint) return;
    const meta = calculateExpiryMetadata(isoDateStr);
    if (!meta) {
      hint.textContent = 'Estructura: DD/MM/AAAA';
      hint.className = 'expiry-preview-hint';
      return;
    }
    hint.innerHTML = `<strong>Estructurado:</strong> ${meta.formatted} &bull; ${escapeHTML(meta.texto)}`;
    hint.className = `expiry-preview-hint ${meta.cls}`;
  }

  function setModalExpiryDays(days) {
    const target = new Date();
    target.setDate(target.getDate() + parseInt(days, 10));
    const yyyy = target.getFullYear();
    const mm = String(target.getMonth() + 1).padStart(2, '0');
    const dd = String(target.getDate()).padStart(2, '0');
    const iso = `${yyyy}-${mm}-${dd}`;
    const input = document.getElementById('modalEditNuevaFecha');
    if (input) {
      input.value = iso;
      onModalExpiryInputChange(iso);
    }
  }

  function onModalExpiryInputChange(isoDateStr) {
    const hint = document.getElementById('modalEditExpiryFeedback');
    if (!hint) return;
    const meta = calculateExpiryMetadata(isoDateStr);
    if (!meta) {
      hint.textContent = 'Estructura: DD/MM/AAAA';
      hint.className = 'expiry-preview-hint';
      return;
    }
    hint.innerHTML = `<strong>Estructurado:</strong> ${meta.formatted} &bull; ${escapeHTML(meta.texto)}`;
    hint.className = `expiry-preview-hint ${meta.cls}`;
  }

  // Modal estructurado de Modificación de Fecha de Vencimiento
  let editLoteContext = null;

  function triggerEditarFecha(loteId, fechaActual, nombre) {
    editLoteContext = { loteId, fechaActual, nombre };
    const modal = document.getElementById('editExpiryModal');
    if (!modal) return;

    document.getElementById('modalEditProductoNombre').textContent = nombre;
    document.getElementById('modalEditFechaActualDisplay').textContent = formatDateDMY(fechaActual);
    
    const inputFecha = document.getElementById('modalEditNuevaFecha');
    inputFecha.value = fechaActual;
    onModalExpiryInputChange(fechaActual);
    
    modal.classList.remove('hidden');
  }

  function closeEditExpiryModal() {
    const modal = document.getElementById('editExpiryModal');
    if (modal) modal.classList.add('hidden');
    editLoteContext = null;
  }

  function submitEditExpiryForm(e) {
    e.preventDefault();
    if (!editLoteContext) return;

    const nuevaFecha = document.getElementById('modalEditNuevaFecha').value;
    const motivo = document.getElementById('modalEditMotivo').value.trim() || 'Ajuste de fecha por revisión física';

    if (!nuevaFecha) {
      showToast('Seleccione una fecha de vencimiento válida.', 'error');
      return;
    }

    const loteId = editLoteContext.loteId;
    const nombre = editLoteContext.nombre;

    closeEditExpiryModal();

    openPinModal({
      accion: 'modificar_fecha',
      loteId: loteId,
      nuevaFecha: nuevaFecha,
      titulo: `Cambiar vencimiento de "${nombre}" a ${formatDateDMY(nuevaFecha)}`,
      motivo: motivo
    });
  }

  async function triggerOfertaRelampago(loteId, nombre) {
    if (!confirm(`¿Activar Oferta Relámpago para "${nombre}"?\nSe aplicará 30% de descuento para rotar el producto y recuperar el capital antes de que venza.`)) {
      return;
    }

    const formData = new FormData();
    formData.append('accion', 'oferta_relampago');
    formData.append('lote_id', loteId);
    formData.append('descuento', '30');

    try {
      const res = await safeFetchJson('api/acciones_lote.php', {
        method: 'POST',
        body: formData
      });
      if (res.status === 'success') {
        showToast(res.message, 'success');
        await reloadDashboard();
      } else {
        showToast(res.message, 'error');
      }
    } catch (e) {
      showToast('Error al activar oferta', 'error');
    }
  }

  // 9. Manejo de Autenticación y Modales
  function openAuthModal(tab = 'login') {
    switchAuthTab(tab);
    document.getElementById('authModal').classList.remove('hidden');
  }

  function closeAuthModal() {
    document.getElementById('authModal').classList.add('hidden');
  }

  function switchAuthTab(tab) {
    const tabLogin = document.getElementById('tabBtnLogin');
    const tabRegister = document.getElementById('tabBtnRegister');
    const formLogin = document.getElementById('formLogin');
    const formRegister = document.getElementById('formRegister');

    if (tab === 'login') {
      tabLogin.classList.add('active');
      tabRegister.classList.remove('active');
      formLogin.classList.remove('hidden');
      formRegister.classList.add('hidden');
    } else {
      tabRegister.classList.add('active');
      tabLogin.classList.remove('active');
      formRegister.classList.remove('hidden');
      formLogin.classList.add('hidden');
    }
  }

  async function handleLoginSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
      const res = await safeFetchJson('api/auth.php?action=login', {
        method: 'POST',
        body: formData
      });
      if (res.status === 'success') {
        state.user = res.user;
        if (res.token) {
          state.authToken = res.token;
          localStorage.setItem('expiry_auth_token', res.token);
        }
        showToast(res.message, 'success');
        closeAuthModal();
        showAppView();
        await reloadDashboard();
      } else {
        showToast(res.message, 'error');
      }
    } catch (err) {
      showToast('Credenciales incorrectas.', 'error');
    }
  }

  async function handleRegisterSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    try {
      const res = await safeFetchJson('api/auth.php?action=register', {
        method: 'POST',
        body: formData
      });
      if (res.status === 'success') {
        state.user = res.user;
        if (res.token) {
          state.authToken = res.token;
          localStorage.setItem('expiry_auth_token', res.token);
        }
        showToast(res.message, 'success');
        closeAuthModal();
        showAppView();
        await reloadDashboard();
      } else {
        showToast(res.message, 'error');
      }
    } catch (err) {
      showToast('Error al registrar cuenta.', 'error');
    }
  }

  async function loginAsDemo(rol) {
    const formData = new FormData();
    formData.append('rol', rol);
    try {
      const res = await safeFetchJson('api/auth.php?action=demo_login', {
        method: 'POST',
        body: formData
      });
      if (res.status === 'success') {
        state.user = res.user;
        if (res.token) {
          state.authToken = res.token;
          localStorage.setItem('expiry_auth_token', res.token);
        }
        showToast(res.message, 'success');
        closeAuthModal();
        showAppView();
        await reloadDashboard();
      }
    } catch (e) {
      showToast('Error en acceso demo', 'error');
    }
  }

  async function logout() {
    try {
      await safeFetchJson('api/auth.php?action=logout');
    } catch (e) {}
    state.user = null;
    state.authToken = '';
    localStorage.removeItem('expiry_auth_token');
    showToast('Sesión cerrada correctamente.', 'info');
    showLandingView();
  }

  // 10. Modal de Auditoría de Seguridad & VulnScope Checklist
  async function openVulnScopeModal() {
    document.getElementById('vulnscopeModal').classList.remove('hidden');
    try {
      const res = await safeFetchJson('api/auditoria.php');
      if (res.status === 'success') {
        const auditTbody = document.getElementById('auditLogTableBody');
        if (auditTbody && res.auditorias) {
          auditTbody.innerHTML = res.auditorias.map(a => `
            <tr>
              <td><span style="font-size:0.75rem; color:var(--text-muted);">${escapeHTML(a.fecha_hora)}</span></td>
              <td><strong>${escapeHTML(a.accion)}</strong></td>
              <td>${escapeHTML(a.motivo || '-')}</td>
              <td>
                <span class="badge ${a.autorizado == 1 ? 'badge-verde' : 'badge-rojo'}">
                  ${a.autorizado == 1 ? 'PIN APROBADO' : 'PIN DENEGADO'}
                </span>
              </td>
              <td>${escapeHTML(a.ip_address)}</td>
            </tr>
          `).join('');
        }
      }
    } catch (e) {}
  }

  function closeVulnScopeModal() {
    document.getElementById('vulnscopeModal').classList.add('hidden');
  }

  // 10.B Notificaciones por Correo Electrónico (Dual: Gmail SMTP / Resend API)
  function switchReportTab(tabName) {
    const tabs = ['destinatarios', 'motor', 'frecuencia', 'contenido'];
    tabs.forEach(t => {
      const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
      const pane = document.getElementById('tabPane' + t.charAt(0).toUpperCase() + t.slice(1));
      if (btn) btn.classList.toggle('active', t === tabName);
      if (pane) pane.classList.toggle('active', t === tabName);
    });
  }

  function selectEngine(engine) {
    const isSmtp = engine === 'SMTP';
    const radioSmtp = document.getElementById('metodoSMTP');
    const radioResend = document.getElementById('metodoResend');
    const cardSmtp = document.getElementById('cardEngineSMTP');
    const cardResend = document.getElementById('cardEngineResend');
    const panelSmtp = document.getElementById('panelConfigSMTP');
    const panelResend = document.getElementById('panelConfigResend');
    const pill = document.getElementById('notifPillEngine');
    const statusText = document.getElementById('notifEngineStatusText');

    if (radioSmtp) radioSmtp.checked = isSmtp;
    if (radioResend) radioResend.checked = !isSmtp;

    if (cardSmtp) cardSmtp.classList.toggle('active', isSmtp);
    if (cardResend) cardResend.classList.toggle('active', !isSmtp);

    if (panelSmtp) panelSmtp.classList.toggle('hidden', !isSmtp);
    if (panelResend) panelResend.classList.toggle('hidden', isSmtp);

    if (pill) {
      pill.textContent = isSmtp ? 'Gmail SMTP Activo' : 'Resend API Activo';
    }
    if (statusText) {
      statusText.textContent = isSmtp 
        ? 'Motor Activo: Gmail / SMTP (Entrega a Todo el Equipo)' 
        : 'Motor Activo: Resend Cloud (Sandbox personal)';
    }
  }

  function toggleSmtpPassVisibility() {
    const input = document.getElementById('smtpPass');
    const icon = document.getElementById('iconEyePass');
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      if (icon) icon.className = 'fas fa-eye-slash';
    } else {
      input.type = 'password';
      if (icon) icon.className = 'fas fa-eye';
    }
  }

  function toggleGuiaGmail() {
    const content = document.getElementById('guiaGmailContent');
    const chevron = document.getElementById('guideChevronIcon');
    if (content) content.classList.toggle('hidden');
    if (chevron) chevron.classList.toggle('rotated');
  }

  async function probarConexionSMTP() {
    const btn = document.getElementById('btnTestSmtpAction');
    const host = document.getElementById('smtpHost') ? document.getElementById('smtpHost').value.trim() : 'smtp.gmail.com';
    const port = document.getElementById('smtpPort') ? document.getElementById('smtpPort').value.trim() : '465';
    const sec = document.getElementById('smtpSeguridad') ? document.getElementById('smtpSeguridad').value : 'ssl';
    const user = document.getElementById('smtpUser') ? document.getElementById('smtpUser').value.trim() : '';
    const pass = document.getElementById('smtpPass') ? document.getElementById('smtpPass').value.trim() : '';

    if (!user) {
      showToast('Ingresá tu correo Gmail emisor', 'error');
      if (document.getElementById('smtpUser')) document.getElementById('smtpUser').focus();
      return;
    }

    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probando conexión SMTP...';
      btn.disabled = true;
    }

    const formData = new FormData();
    formData.append('smtp_host', host);
    formData.append('smtp_port', port);
    formData.append('smtp_seguridad', sec);
    formData.append('smtp_user', user);
    if (pass) formData.append('smtp_pass', pass);

    try {
      const res = await safeFetchJson('api/notificaciones.php?action=test_smtp', {
        method: 'POST',
        body: formData
      });
      if (res.status === 'success') {
        showToast(res.message, 'success');
        const badge = document.getElementById('smtpPassSavedBadge');
        if (badge) badge.classList.remove('hidden');
      } else {
        showToast(res.message || 'Error autenticando SMTP', 'error');
      }
    } catch (err) {
      showToast('Error conectando con el servidor SMTP', 'error');
    } finally {
      if (btn) {
        btn.innerHTML = origHtml;
        btn.disabled = false;
      }
    }
  }

  // Renderizado reactivo de la lista de contactos
  function renderContactosList(contactos) {
    const container = document.getElementById('contactosListContainer');
    const badgeCount = document.getElementById('contactosCountBadge');
    if (!container) return;

    if (badgeCount) {
      const total = contactos ? contactos.length : 0;
      badgeCount.textContent = `${total} contacto${total === 1 ? '' : 's'}`;
    }

    if (!contactos || contactos.length === 0) {
      container.innerHTML = `
        <div class="contactos-empty-state">
          <i class="fas fa-user-friends" style="font-size: 1.5rem; color: #94a3b8; margin-bottom: 6px; display: block;"></i>
          No tenés contactos cargados.<br>
          Hacé clic en <strong>"+ Agregar Contacto"</strong> para registrar a tu equipo.
        </div>
      `;
      return;
    }

    // Sincronizar inputs ocultos
    const principal = contactos.find(c => c.tipo_destinatario === 'PRINCIPAL');
    const ccActivos = contactos.filter(c => c.tipo_destinatario === 'CC' && c.activo == 1).map(c => c.email);
    
    const hiddenUserEmail = document.getElementById('notifUserEmail');
    const hiddenEmailsCopia = document.getElementById('notifEmailsCopia');
    if (hiddenUserEmail && principal) hiddenUserEmail.value = principal.email;
    if (hiddenEmailsCopia) hiddenEmailsCopia.value = ccActivos.join(', ');

    container.innerHTML = contactos.map(c => {
      const initial = (c.nombre || 'U').charAt(0).toUpperCase();
      const isPrincipal = c.tipo_destinatario === 'PRINCIPAL';
      const badgeTipo = isPrincipal 
        ? '<span class="badge-tag-principal"><i class="fas fa-crown"></i> Principal</span>' 
        : '<span class="badge-tag-cc">Copia (CC)</span>';
      const avatarClass = isPrincipal ? 'contacto-avatar principal' : 'contacto-avatar';
      const inactivoClass = c.activo != 1 ? 'inactivo' : '';
      const isChecked = c.activo == 1 ? 'checked' : '';

      return `
        <div class="contacto-card-item ${inactivoClass}">
          <div class="contacto-card-left">
            <div class="${avatarClass}">${initial}</div>
            <div class="contacto-info">
              <div class="contacto-name-row">
                <strong class="contacto-name">${escapeHTML(c.nombre)}</strong>
                <span class="contacto-cargo-badge">${escapeHTML(c.cargo || 'Colaborador')}</span>
              </div>
              <span class="contacto-email-text">${escapeHTML(c.email)}</span>
            </div>
          </div>
          <div class="contacto-card-right">
            ${badgeTipo}
            <label class="switch-toggle" style="transform: scale(0.72);" title="${c.activo == 1 ? 'Recepción de alertas activa' : 'Recepción pausada'}">
              <input type="checkbox" ${isChecked} onchange="App.toggleContacto(${c.id})">
              <span class="switch-slider"></span>
            </label>
            <button type="button" class="btn-del-contact" title="Eliminar contacto" onclick="App.eliminarContacto(${c.id}, '${escapeHTML(c.nombre)}')">
              <i class="fas fa-trash-alt"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  // Desplegar / Ocultar formulario obligatorio de nuevo contacto
  function toggleFormNuevoContacto(forceState) {
    const box = document.getElementById('boxNuevoContacto');
    if (!box) return;

    if (forceState !== undefined) {
      if (forceState) {
        box.classList.remove('hidden');
      } else {
        box.classList.add('hidden');
      }
    } else {
      box.classList.toggle('hidden');
    }

    if (!box.classList.contains('hidden')) {
      // Limpiar campos y dar foco al nombre
      document.getElementById('addContactoNombre').value = '';
      document.getElementById('addContactoEmail').value = '';
      document.getElementById('addContactoCargo').selectedIndex = 0;
      document.getElementById('addContactoTipo').value = 'CC';
      document.getElementById('addContactoNombre').focus();
    }
  }

  // Guardar contacto con formulario obligatorio
  async function guardarNuevoContacto() {
    const nombreInput = document.getElementById('addContactoNombre');
    const emailInput = document.getElementById('addContactoEmail');
    const cargoInput = document.getElementById('addContactoCargo');
    const tipoInput = document.getElementById('addContactoTipo');
    const submitBtn = document.getElementById('btnSubmitNewContact');

    const nombre = nombreInput ? nombreInput.value.trim() : '';
    const email = emailInput ? emailInput.value.trim() : '';
    const cargo = cargoInput ? cargoInput.value.trim() : '';
    const tipo = tipoInput ? tipoInput.value : 'CC';

    // Validación obligatoria de formulario
    if (!nombre || nombre.length < 2) {
      showToast('El Nombre completo es un campo obligatorio (mínimo 2 letras)', 'error');
      if (nombreInput) nombreInput.focus();
      return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRegex.test(email)) {
      showToast('El Correo electrónico es obligatorio y debe tener formato válido', 'error');
      if (emailInput) emailInput.focus();
      return;
    }

    if (!cargo) {
      showToast('Por favor seleccioná el Cargo o Función del contacto (campo obligatorio)', 'error');
      if (cargoInput) cargoInput.focus();
      return;
    }

    const originalText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando Contacto...';
      submitBtn.disabled = true;
    }

    const formData = new FormData();
    formData.append('nombre', nombre);
    formData.append('email', email);
    formData.append('cargo', cargo);
    formData.append('tipo_destinatario', tipo);

    try {
      const res = await safeFetchJson('api/notificaciones.php?action=add_contacto', {
        method: 'POST',
        body: formData
      });

      if (res.status === 'success') {
        showToast(res.message, 'success');
        renderContactosList(res.contactos);
        toggleFormNuevoContacto(false);
      } else {
        showToast(res.message || 'Error al guardar contacto', 'error');
      }
    } catch (err) {
      showToast('Error de conexión al guardar contacto', 'error');
    } finally {
      if (submitBtn) {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      }
    }
  }

  // Eliminar contacto
  async function eliminarContacto(id, nombre) {
    if (!confirm(`¿Estás seguro de quitar a "${nombre}" de la lista de destinatarios de alertas?`)) {
      return;
    }

    const formData = new FormData();
    formData.append('id', id);

    try {
      const res = await safeFetchJson('api/notificaciones.php?action=delete_contacto', {
        method: 'POST',
        body: formData
      });

      if (res.status === 'success') {
        showToast(res.message, 'success');
        renderContactosList(res.contactos);
      } else {
        showToast(res.message || 'Error al eliminar contacto', 'error');
      }
    } catch (err) {
      showToast('Error de conexión al eliminar contacto', 'error');
    }
  }

  // Activar o pausar recepción de un contacto
  async function toggleContacto(id) {
    const formData = new FormData();
    formData.append('id', id);

    try {
      const res = await safeFetchJson('api/notificaciones.php?action=toggle_contacto', {
        method: 'POST',
        body: formData
      });

      if (res.status === 'success') {
        renderContactosList(res.contactos);
      } else {
        showToast(res.message || 'Error al actualizar contacto', 'error');
      }
    } catch (err) {
      showToast('Error de conexión al cambiar estado del contacto', 'error');
    }
  }

  async function openNotificacionesModal() {
    const modal = document.getElementById('notificacionesModal');
    if (!modal) return;
    modal.classList.remove('hidden');

    // Iniciar siempre en la pestaña 1
    switchReportTab('destinatarios');
    toggleFormNuevoContacto(false);

    try {
      const res = await safeFetchJson('api/notificaciones.php?action=get_config');
      if (res.status === 'success') {
        const cfg = res.config;
        const emailInput = document.getElementById('notifUserEmail');
        const ccInput = document.getElementById('notifEmailsCopia');
        const activoCheckbox = document.getElementById('notifActivoCheckbox');
        const horaInput = document.getElementById('notifHoraReporte');

        if (emailInput) emailInput.value = cfg.email_notificaciones || cfg.user_email || '';
        if (ccInput) ccInput.value = cfg.emails_copia || '';
        if (activoCheckbox) activoCheckbox.checked = cfg.notificar_email === 1;
        if (horaInput && cfg.hora_reporte) horaInput.value = cfg.hora_reporte;

        // Configurar Motor de Envío (SMTP o Resend)
        const engineActive = (cfg.email_metodo || 'SMTP').toUpperCase();
        selectEngine(engineActive);

        // Campos SMTP
        const smtpUserInput = document.getElementById('smtpUser');
        const smtpHostInput = document.getElementById('smtpHost');
        const smtpPortInput = document.getElementById('smtpPort');
        const smtpSecInput = document.getElementById('smtpSeguridad');
        const smtpPassBadge = document.getElementById('smtpPassSavedBadge');
        const senderLabel = document.getElementById('notifSender');

        if (smtpUserInput) smtpUserInput.value = cfg.smtp_user || cfg.email_notificaciones || cfg.user_email || '';
        if (smtpHostInput) smtpHostInput.value = cfg.smtp_host || 'smtp.gmail.com';
        if (smtpPortInput) smtpPortInput.value = cfg.smtp_port || 465;
        if (smtpSecInput) smtpSecInput.value = cfg.smtp_seguridad || 'ssl';
        if (smtpPassBadge) smtpPassBadge.classList.toggle('hidden', !cfg.smtp_pass_configured);

        // Campos Resend
        const apiKeyInput = document.getElementById('notifApiKeyInput');
        const senderInput = document.getElementById('notifSenderInput');
        if (apiKeyInput && cfg.resend_api_key) apiKeyInput.value = cfg.resend_api_key;
        if (senderInput && cfg.email_remitente) senderInput.value = cfg.email_remitente;

        if (senderLabel) {
          senderLabel.textContent = engineActive === 'SMTP' ? (cfg.smtp_user || cfg.email_notificaciones || 'Gmail') : (cfg.email_remitente || 'onboarding@resend.dev');
        }

        // Frecuencia seleccionada
        const freqVal = cfg.frecuencia_reporte || 'DIARIO';
        const freqRadio = document.querySelector(`input[name="frecuencia_reporte"][value="${freqVal}"]`);
        if (freqRadio) freqRadio.checked = true;

        // Filtros de qué incluir y estados visuales
        const toggleMap = [
          { id: 'chkIncVencidos', cardId: 'cardToggleVencidos', val: cfg.incluir_vencidos },
          { id: 'chkIncRojos', cardId: 'cardToggleRojos', val: cfg.incluir_rojos },
          { id: 'chkIncAmarillos', cardId: 'cardToggleAmarillos', val: cfg.incluir_amarillos },
          { id: 'chkIncFinanciero', cardId: 'cardToggleFinanciero', val: cfg.incluir_financiero },
          { id: 'chkIncSugerencias', cardId: 'cardToggleSugerencias', val: cfg.incluir_sugerencias }
        ];

        toggleMap.forEach(item => {
          const el = document.getElementById(item.id);
          const card = document.getElementById(item.cardId) || el?.closest('.report-toggle-card');
          if (el) {
            el.checked = item.val !== 0;
            if (card) {
              card.classList.toggle('active-toggle', el.checked);
              card.classList.toggle('inactive-toggle', !el.checked);
            }
          }
        });

        // Configurar remitente y su cargo en pestaña 4 (Qué Mandar)
        const remitenteNombreInput = document.getElementById('notifRemitenteNombre');
        const remitenteCargoSelect = document.getElementById('notifRemitenteCargo');
        if (remitenteNombreInput && !remitenteNombreInput.value) {
          remitenteNombreInput.value = cfg.remitente_nombre || cfg.user_nombre || state.user?.nombre || 'Administrador Propietario';
        }
        if (remitenteCargoSelect && cfg.remitente_cargo) {
          remitenteCargoSelect.value = cfg.remitente_cargo;
        }

        // Renderizar lista interactiva de contactos en Pestaña 1
        renderContactosList(res.contactos || []);

        // Renderizar chips de emisores rápidos en Pestaña 4
        renderQuickSenders(res.contactos || [], cfg.user_nombre || state.user?.nombre);
      }
    } catch (e) {
      console.error('Error cargando configuración de notificaciones:', e);
    }
  }

  function closeNotificacionesModal() {
    const modal = document.getElementById('notificacionesModal');
    if (modal) modal.classList.add('hidden');
  }

  // Controlador reactivo al alternar cualquiera de las 5 opciones de "Qué Mandar"
  async function onFiltroToggleChange(input) {
    if (!input) return;
    const card = input.closest('.report-toggle-card');
    if (card) {
      card.classList.toggle('active-toggle', input.checked);
      card.classList.toggle('inactive-toggle', !input.checked);
    }

    const toggles = [
      { id: 'chkIncVencidos', name: 'Mercadería Ya Vencida' },
      { id: 'chkIncRojos', name: 'Semáforo Rojo' },
      { id: 'chkIncAmarillos', name: 'Semáforo Amarillo' },
      { id: 'chkIncFinanciero', name: 'Balance Financiero' },
      { id: 'chkIncSugerencias', name: 'Recomendaciones Comerciales' }
    ];

    const activos = toggles.filter(t => {
      const el = document.getElementById(t.id);
      return el && el.checked;
    });

    if (activos.length === 0) {
      showToast('⚠️ Desmarcaste todas las opciones. El correo se enviará vacío.', 'warning');
    } else if (activos.length === 1) {
      showToast(`🎯 Filtro exclusivo activo: El correo solo enviará "${activos[0].name}".`, 'info');
    } else {
      showToast(`✔ Filtro actualizado: ${activos.length} bloques seleccionados para el reporte.`, 'success');
    }

    // Auto-guardado en background para persistir de inmediato en BD
    try {
      const formData = new FormData();
      formData.append('incluir_vencidos', document.getElementById('chkIncVencidos')?.checked ? '1' : '0');
      formData.append('incluir_rojos', document.getElementById('chkIncRojos')?.checked ? '1' : '0');
      formData.append('incluir_amarillos', document.getElementById('chkIncAmarillos')?.checked ? '1' : '0');
      formData.append('incluir_financiero', document.getElementById('chkIncFinanciero')?.checked ? '1' : '0');
      formData.append('incluir_sugerencias', document.getElementById('chkIncSugerencias')?.checked ? '1' : '0');

      await safeFetchJson('api/notificaciones.php?action=save_filtros', {
        method: 'POST',
        body: formData
      });
    } catch (e) {
      // Ignorar fallo de sincronización silenciosa
    }
  }

  function renderQuickSenders(contactos, currentUserNombre) {
    const container = document.getElementById('quickSendersChips');
    if (!container) return;
    container.innerHTML = '';

    const list = [];
    const mainUser = currentUserNombre || state.user?.nombre || 'Administrador Propietario';
    list.push({ nombre: mainUser, cargo: 'Dueño / Administrador', icon: '👑' });

    if (Array.isArray(contactos)) {
      contactos.forEach(c => {
        if (c.activo && c.nombre) {
          if (!list.some(item => item.nombre.toLowerCase() === c.nombre.toLowerCase())) {
            list.push({
              nombre: c.nombre,
              cargo: c.cargo || 'Repositor de Góndola',
              icon: c.tipo_destinatario === 'PRINCIPAL' ? '👑' : '👤'
            });
          }
        }
      });
    }

    list.forEach(item => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'quick-chip-btn';
      btn.innerHTML = `<span>${item.icon}</span> <strong>${escapeHtml(item.nombre)}</strong> <small>(${escapeHtml(item.cargo)})</small>`;
      btn.onclick = () => {
        seleccionarRemitente(item.nombre, item.cargo);
      };
      container.appendChild(btn);
    });
  }

  function seleccionarRemitente(nombre, cargo) {
    const nombreInput = document.getElementById('notifRemitenteNombre');
    const cargoSelect = document.getElementById('notifRemitenteCargo');
    if (nombreInput) nombreInput.value = nombre;
    if (cargoSelect) {
      let matched = false;
      for (let i = 0; i < cargoSelect.options.length; i++) {
        const opt = cargoSelect.options[i];
        if (opt.value.toLowerCase() === cargo.toLowerCase() || opt.text.toLowerCase().includes(cargo.toLowerCase())) {
          cargoSelect.selectedIndex = i;
          matched = true;
          break;
        }
      }
      if (!matched && cargoSelect.options.length > 0) {
        cargoSelect.selectedIndex = 0;
      }
    }
    showToast(`👤 Emisor asignado: ${nombre} (${cargo})`, 'info');
  }

  async function guardarConfiguracionEmail(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    // Garantizar que los 5 toggles de Qué Mandar se envíen de forma explícita
    formData.set('filtros_explicit', '1');
    formData.set('incluir_vencidos', document.getElementById('chkIncVencidos')?.checked ? '1' : '0');
    formData.set('incluir_rojos', document.getElementById('chkIncRojos')?.checked ? '1' : '0');
    formData.set('incluir_amarillos', document.getElementById('chkIncAmarillos')?.checked ? '1' : '0');
    formData.set('incluir_financiero', document.getElementById('chkIncFinanciero')?.checked ? '1' : '0');
    formData.set('incluir_sugerencias', document.getElementById('chkIncSugerencias')?.checked ? '1' : '0');

    const remitenteNombre = document.getElementById('notifRemitenteNombre')?.value.trim();
    const remitenteCargo = document.getElementById('notifRemitenteCargo')?.value.trim();
    if (remitenteNombre) formData.set('remitente_nombre', remitenteNombre);
    if (remitenteCargo) formData.set('remitente_cargo', remitenteCargo);

    const submitBtn = document.getElementById('btnSaveConfigEmail') || form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando Configuración...';
    submitBtn.disabled = true;

    try {
      const res = await safeFetchJson('api/notificaciones.php?action=save_config', {
        method: 'POST',
        body: formData
      });

      if (res.status === 'success') {
        showToast(res.message, 'success');
        if (state.user) {
          state.user.email = document.getElementById('notifUserEmail').value;
        }
        const passVal = document.getElementById('smtpPass') ? document.getElementById('smtpPass').value.trim() : '';
        if (passVal) {
          const badge = document.getElementById('smtpPassSavedBadge');
          if (badge) badge.classList.remove('hidden');
        }
      } else {
        showToast(res.message || 'Error al guardar configuración', 'error');
      }
    } catch (err) {
      showToast('Error al conectar con el servidor', 'error');
    } finally {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }
  }

  async function enviarEmailPrueba() {
    const btn = document.getElementById('btnSendTest');
    const emailInput = document.getElementById('notifUserEmail');
    const email = emailInput ? emailInput.value.trim() : '';
    const cc = document.getElementById('notifEmailsCopia') ? document.getElementById('notifEmailsCopia').value.trim() : '';

    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probando Envío a Todos...';
      btn.disabled = true;
    }

    const formData = new FormData();
    if (email) {
      formData.append('email', email);
      formData.append('email_destino', email);
    }
    if (cc) formData.append('emails_copia', cc);

    // Adjuntar método de envío activo
    const form = document.getElementById('formEmailConfig');
    if (form) {
      const methodRadio = form.querySelector('input[name="email_metodo"]:checked');
      if (methodRadio) formData.append('email_metodo', methodRadio.value);
    }

    // Remitente y Cargo en el Negocio
    const remitenteNombre = document.getElementById('notifRemitenteNombre')?.value.trim();
    const remitenteCargo = document.getElementById('notifRemitenteCargo')?.value.trim();
    if (remitenteNombre) formData.append('remitente_nombre', remitenteNombre);
    if (remitenteCargo) formData.append('remitente_cargo', remitenteCargo);

    try {
      const res = await safeFetchJson('api/notificaciones.php?action=send_test', {
        method: 'POST',
        body: formData
      });

      if (res.status === 'success') {
        showToast(res.message, 'success');
      } else {
        showToast(res.message || 'Error al enviar prueba', 'error');
      }
    } catch (err) {
      showToast('Error de conexión al enviar prueba', 'error');
    } finally {
      if (btn) {
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    }
  }

  async function enviarReporteEmailRapido() {
    const btn = document.getElementById('btnSendReport');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Despachando a Todos...';
      btn.disabled = true;
    }

    // Tomar todos los valores actuales del formulario
    const form = document.getElementById('formEmailConfig');
    const formData = form ? new FormData(form) : new FormData();

    const emailInput = document.getElementById('notifUserEmail');
    if (emailInput && emailInput.value) {
      formData.set('email', emailInput.value.trim());
      formData.set('email_destino', emailInput.value.trim());
    }
    const ccInput = document.getElementById('notifEmailsCopia');
    if (ccInput && ccInput.value) {
      formData.set('emails_copia', ccInput.value.trim());
    }

    // Indicador explícito de filtros para enviar SOLO lo seleccionado
    formData.set('filtros_explicit', '1');
    formData.set('incluir_vencidos', document.getElementById('chkIncVencidos')?.checked ? '1' : '0');
    formData.set('incluir_rojos', document.getElementById('chkIncRojos')?.checked ? '1' : '0');
    formData.set('incluir_amarillos', document.getElementById('chkIncAmarillos')?.checked ? '1' : '0');
    formData.set('incluir_financiero', document.getElementById('chkIncFinanciero')?.checked ? '1' : '0');
    formData.set('incluir_sugerencias', document.getElementById('chkIncSugerencias')?.checked ? '1' : '0');

    // Remitente y su Cargo o Rol
    const remitenteNombre = document.getElementById('notifRemitenteNombre')?.value.trim();
    const remitenteCargo = document.getElementById('notifRemitenteCargo')?.value.trim();
    if (remitenteNombre) formData.set('remitente_nombre', remitenteNombre);
    if (remitenteCargo) formData.set('remitente_cargo', remitenteCargo);

    try {
      const res = await safeFetchJson('api/notificaciones.php?action=send_report', {
        method: 'POST',
        body: formData
      });

      if (res.status === 'success') {
        showToast(res.message, 'success');
      } else {
        showToast(res.message || 'Error enviando reporte', 'error');
        openNotificacionesModal();
      }
    } catch (err) {
      showToast('Error al despachar alerta', 'error');
    } finally {
      if (btn) {
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    }
  }

  // 11. Simulador de la Landing Page
  function setupSimulator() {
    const slider = document.getElementById('simDaysSlider');
    const label = document.getElementById('simDaysLabel');
    const colorBadge = document.getElementById('simColorBadge');
    const advice = document.getElementById('simAdvice');

    if (!slider) return;

    slider.addEventListener('input', (e) => {
      const days = parseInt(e.target.value);
      label.textContent = days + (days === 1 ? ' día' : ' días');

      if (days <= 0) {
        colorBadge.textContent = '⛔ VENCIDO';
        colorBadge.className = 'badge badge-vencido';
        advice.textContent = 'Mercadería vencida: retirar de góndola de inmediato para evitar quejas o multas sanitarias.';
      } else if (days <= 7) {
        colorBadge.textContent = '🔴 ROJO CRÍTICO';
        colorBadge.className = 'badge badge-rojo';
        advice.textContent = 'Vence en menos de 7 días: poner cartel de oferta relámpago 2x1 o liquidar al costo para no perder el dinero.';
      } else if (days <= 30) {
        colorBadge.textContent = '🟡 AMARILLO ALERTA';
        colorBadge.className = 'badge badge-amarillo';
        advice.textContent = 'Alerta preventiva: ubicar al frente de la góndola o heladera (rotación FIFO) para que salga primero.';
      } else {
        colorBadge.textContent = '🟢 VERDE SEGURO';
        colorBadge.className = 'badge badge-verde';
        advice.textContent = 'Stock con tiempo de vida útil óptimo y seguro.';
      }
    });
  }

  // 12. Listeners Globales
  function setupEventListeners() {
    // Teclado para PIN modal
    window.addEventListener('keydown', (e) => {
      const pinModal = document.getElementById('pinModal');
      if (pinModal && !pinModal.classList.contains('hidden')) {
        if (/^[0-9]$/.test(e.key)) {
          enterPinDigit(e.key);
        } else if (e.key === 'Backspace') {
          deletePinDigit();
        } else if (e.key === 'Enter') {
          submitPinAction();
        } else if (e.key === 'Escape') {
          closePinModal();
        }
      }
    });

    // Filtros de color
    const filterTabs = document.querySelectorAll('.filter-tab');
    filterTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        filterTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        state.filtroColor = tab.dataset.color || 'ALL';
        reloadDashboard();
      });
    });

    // Búsqueda con debounce
    const searchInput = document.getElementById('tableSearchInput');
    if (searchInput) {
      let st = null;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(st);
        st = setTimeout(() => {
          state.busqueda = e.target.value.trim();
          reloadDashboard();
        }, 300);
      });
    }

    // Formulario de carga rápida
    const quickForm = document.getElementById('quickEntryForm');
    if (quickForm) {
      quickForm.addEventListener('submit', handleQuickEntrySubmit);
    }

    // Input de código de barra
    const barcodeInput = document.getElementById('quickCodigoBarra');
    if (barcodeInput) {
      barcodeInput.addEventListener('input', (e) => handleBarcodeInput(e.target.value.trim()));
    }

    // Formularios de login y registro
    const formLogin = document.getElementById('formLogin');
    if (formLogin) formLogin.addEventListener('submit', handleLoginSubmit);

    const formRegister = document.getElementById('formRegister');
    if (formRegister) formRegister.addEventListener('submit', handleRegisterSubmit);
  }

  // 17. Simulador Interactivo de la Landing Page
  function setupSimulator() {
    const slider = document.getElementById('simDaysSlider');
    const label = document.getElementById('simDaysLabel');
    const badge = document.getElementById('simColorBadge');
    const adviceText = document.getElementById('simAdviceText');

    if (!slider || !label || !badge) return;

    function updateSim() {
      const days = parseInt(slider.value, 10);
      label.textContent = days === 0 ? '0 días (Hoy / Merma)' : `${days} días`;

      if (days <= 7) {
        badge.className = 'sim-result-badge badge-rojo-glow';
        badge.textContent = '🔴 ROJO CRÍTICO';
        if (adviceText) {
          adviceText.textContent = days === 0
            ? 'Retiro Inmediato: El lote ya alcanzó su fecha de vencimiento. Retirar de góndola de forma urgente para evitar sanciones.'
            : `Zona Crítica: Vencimiento en ${days} días. Recomendación: Activar Oferta Relámpago (-35% o 2x1) para recuperar costo en 48hs.`;
        }
      } else if (days <= 30) {
        badge.className = 'sim-result-badge badge-amarillo-glow';
        badge.textContent = '🟡 AMARILLO PREVENTIVO';
        if (adviceText) {
          adviceText.textContent = `Zona Preventiva: Vencimiento en ${days} días. Recomendación: Rotación FIFO (mover lote al frente de la heladera o góndola).`;
        }
      } else {
        badge.className = 'sim-result-badge badge-verde-glow';
        badge.textContent = '🟢 VERDE ÓPTIMO';
        if (adviceText) {
          adviceText.textContent = `Zona Segura: ${days} días de vida útil remanente. Stock exhibido con tranquilidad financiera. No requiere acción.`;
        }
      }
    }

    slider.addEventListener('input', updateSim);
    updateSim();
  }

  // 18. Manejo del Modal Informativo y Legal (Términos, Privacidad, Soporte)
  function openLegalModal(tab = 'terminos') {
    switchLegalTab(tab);
    const modal = document.getElementById('legalModal');
    if (modal) modal.classList.remove('hidden');
  }

  function closeLegalModal() {
    const modal = document.getElementById('legalModal');
    if (modal) modal.classList.add('hidden');
  }

  function switchLegalTab(tab) {
    const btnTerminos = document.getElementById('legalTabBtnTerminos');
    const btnPrivacidad = document.getElementById('legalTabBtnPrivacidad');
    const btnSoporte = document.getElementById('legalTabBtnSoporte');

    const paneTerminos = document.getElementById('legalPaneTerminos');
    const panePrivacidad = document.getElementById('legalPanePrivacidad');
    const paneSoporte = document.getElementById('legalPaneSoporte');

    if (btnTerminos) btnTerminos.classList.remove('active');
    if (btnPrivacidad) btnPrivacidad.classList.remove('active');
    if (btnSoporte) btnSoporte.classList.remove('active');

    if (paneTerminos) paneTerminos.classList.add('hidden');
    if (panePrivacidad) panePrivacidad.classList.add('hidden');
    if (paneSoporte) paneSoporte.classList.add('hidden');

    if (tab === 'terminos') {
      if (btnTerminos) btnTerminos.classList.add('active');
      if (paneTerminos) paneTerminos.classList.remove('hidden');
    } else if (tab === 'privacidad') {
      if (btnPrivacidad) btnPrivacidad.classList.add('active');
      if (panePrivacidad) panePrivacidad.classList.remove('hidden');
    } else if (tab === 'soporte') {
      if (btnSoporte) btnSoporte.classList.add('active');
      if (paneSoporte) paneSoporte.classList.remove('hidden');
    }
  }

  // API Pública expuesta
  return {
    init,
    openAuthModal,
    closeAuthModal,
    switchAuthTab,
    loginAsDemo,
    logout,
    openVulnScopeModal,
    closeVulnScopeModal,
    setupSimulator,
    openLegalModal,
    closeLegalModal,
    switchLegalTab,
    setQuickDays,
    openPinModal,
    closePinModal,
    enterPinDigit,
    deletePinDigit,
    submitPinAction,
    triggerEliminarLote,
    triggerEditarFecha,
    closeEditExpiryModal,
    submitEditExpiryForm,
    setQuickExpiryDays,
    onExpiryInputChange,
    setModalExpiryDays,
    onModalExpiryInputChange,
    triggerOfertaRelampago,
    openNotificacionesModal,
    closeNotificacionesModal,
    switchReportTab,
    toggleFormNuevoContacto,
    guardarNuevoContacto,
    eliminarContacto,
    toggleContacto,
    guardarConfiguracionEmail,
    enviarEmailPrueba,
    enviarReporteEmailRapido,
    selectEngine,
    toggleSmtpPassVisibility,
    toggleGuiaGmail,
    probarConexionSMTP,
    onFiltroToggleChange,
    seleccionarRemitente,
    showAppView,
    showLandingView,
    toggleRemoteApi,
    setApiTarget,
    renderApiSwitchBadge
  };
})();

// Switch y Badge interactivo de API para localhost <-> https://expiry-gules.vercel.app/
function toggleRemoteApi() {
  state.useRemoteApi = !state.useRemoteApi;
  localStorage.setItem('expiry_use_remote_api', state.useRemoteApi ? 'true' : 'false');
  renderApiSwitchBadge();
  showToast(
    state.useRemoteApi
      ? '☁️ Conectado a la API en la nube: ' + state.remoteApiBase
      : '💻 Conectado a la API local (localhost)',
    'info'
  );
  checkAuthStatus();
}

function setApiTarget(target) {
  state.useRemoteApi = (target === 'remote');
  localStorage.setItem('expiry_use_remote_api', state.useRemoteApi ? 'true' : 'false');
  renderApiSwitchBadge();
  checkAuthStatus();
}

function renderApiSwitchBadge() {
  const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
  let badge = document.getElementById('apiSwitchBadge');
  if (!isLocalhost) {
    if (badge) badge.remove();
    return;
  }

  if (!badge) {
    badge = document.createElement('div');
    badge.id = 'apiSwitchBadge';
    badge.style.position = 'fixed';
    badge.style.bottom = '18px';
    badge.style.left = '18px';
    badge.style.zIndex = '99999';
    badge.style.cursor = 'pointer';
    badge.style.padding = '8px 14px';
    badge.style.borderRadius = '50px';
    badge.style.fontSize = '12px';
    badge.style.fontWeight = '700';
    badge.style.display = 'flex';
    badge.style.alignItems = 'center';
    badge.style.gap = '8px';
    badge.style.boxShadow = '0 4px 14px rgba(0,0,0,0.2)';
    badge.style.transition = 'all 0.25s ease';
    badge.onclick = () => App.toggleRemoteApi();
    document.body.appendChild(badge);
  }

  if (state.useRemoteApi) {
    badge.style.background = '#0284c7';
    badge.style.color = '#ffffff';
    badge.innerHTML = '<i class="fas fa-cloud"></i> <span>API: Vercel Cloud (expiry-gules)</span> <i class="fas fa-sync-alt" style="font-size:10px;opacity:0.8;"></i>';
    badge.title = 'Haga clic para cambiar a la API local de localhost';
  } else {
    badge.style.background = '#1e293b';
    badge.style.color = '#f8fafc';
    badge.innerHTML = '<i class="fas fa-laptop-code"></i> <span>API: Localhost (XAMPP)</span> <i class="fas fa-sync-alt" style="font-size:10px;opacity:0.8;"></i>';
    badge.title = 'Haga clic para conectar su localhost a https://expiry-gules.vercel.app';
  }
}

// Inicialización de la aplicación
document.addEventListener('DOMContentLoaded', App.init);

// Delegador seguro universal de eventos para cumplimiento estricto de Content Security Policy (sin 'unsafe-inline')
document.addEventListener('click', (e) => {
  const el = e.target.closest('[onclick]');
  if (!el) return;
  const code = el.getAttribute('onclick');
  if (!code) return;

  // 1. Despacho dinámico de métodos App.metodo(args)
  const appMatch = code.match(/^App\.([a-zA-Z0-9_]+)\((.*)\)$/);
  if (appMatch) {
    const fn = appMatch[1];
    const rawArgs = appMatch[2].trim();
    let args = [];
    if (rawArgs.length > 0) {
      args = rawArgs.split(',').map(a => {
        a = a.trim();
        if ((a.startsWith("'") && a.endsWith("'")) || (a.startsWith('"') && a.endsWith('"'))) {
          return a.slice(1, -1);
        }
        if (a === 'true') return true;
        if (a === 'false') return false;
        if (!isNaN(Number(a))) return Number(a);
        return a;
      });
    }
    if (typeof App !== 'undefined' && typeof App[fn] === 'function') {
      e.preventDefault();
      App[fn].apply(App, args);
      return;
    }
  }

  // 2. Colapsar/expandir FAQ (this.classList.toggle('open'))
  if (code.includes("classList.toggle('open')")) {
    el.classList.toggle('open');
    return;
  }

  // 3. Recarga segura de página (location.reload())
  if (code.includes("location.reload()")) {
    e.preventDefault();
    location.reload();
    return;
  }

  // 4. Enfoque de formulario rápido
  if (code.includes("quickNombre")) {
    document.getElementById('quickNombre')?.focus();
    return;
  }

  // 5. Clicks en tabs semáforo
  const tabMatch = code.match(/querySelector\('([^']+)'\)\?\.click\(\)/);
  if (tabMatch) {
    document.querySelector(tabMatch[1])?.click();
    return;
  }
}, false);
