document.addEventListener('DOMContentLoaded', () => {
    const windows = document.querySelectorAll('.window-content');
    const navButtons = document.querySelectorAll('.nav-btn');
    const rolUsuario = document.body.dataset.rol || '';

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (n) => '$' + parseFloat(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Notificaciones (verde=éxito, rojo=error, amarillo=alerta)
    function showAlert(mensaje, tipo) {
        let tipoNoti = 'alerta';
        if (tipo === 'success' || tipo === 'exito') tipoNoti = 'exito';
        else if (tipo === 'danger' || tipo === 'error') tipoNoti = 'error';

        let box = document.getElementById('notificacion-box');
        if (!box) {
            box = document.createElement('div');
            box.id = 'notificacion-box';
            document.body.appendChild(box);
        }
        box.className = `notificacion-badge noti-${tipoNoti}`;
        box.innerText = mensaje;
        box.style.display = 'block';
        setTimeout(() => { box.style.display = 'none'; }, 4200);
    }

    async function postJSON(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return response.json();
    }

    // Funciones de validación de RFC y Teléfono
    const regexRFC = /^([A-ZÑ&]{3,4})(\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])([A-Z\d]{3})$/;

    function validarTelefonoJS(valor) {
        let digitos = String(valor || '').replace(/\D+/g, '');
        if (digitos.length === 12 && digitos.startsWith('52')) {
            digitos = digitos.slice(2);
        }
        return /^\d{10}$/.test(digitos) && !/^(\d)\1{9}$/.test(digitos);
    }

    function validarRFCJS(valor) {
        const rfc = String(valor || '').trim().toUpperCase();
        const match = rfc.match(regexRFC);
        if (!match) return false;
        const anio = parseInt(match[2], 10);
        const mes = parseInt(match[3], 10);
        const dia = parseInt(match[4], 10);
        const anioActual2D = new Date().getFullYear() % 100;
        const anioCompleto = anio <= anioActual2D ? 2000 + anio : 1900 + anio;
        const fecha = new Date(anioCompleto, mes - 1, dia);
        return fecha.getFullYear() === anioCompleto && (fecha.getMonth() + 1) === mes && fecha.getDate() === dia;
    }

    // Navegación entre ventanas del panel
    function switchWindow(targetWindowId) {
        windows.forEach(win => win.classList.toggle('hidden', win.id !== targetWindowId));
        navButtons.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-target') === targetWindowId));

        if (targetWindowId === 'window-cotizaciones') { cargarPaquetesPredeterminados(); cargarCotizaciones(); }
        if (targetWindowId === 'window-contratos') cargarContratos();
        if (targetWindowId === 'window-eventos') cargarEventos();
        if (targetWindowId === 'window-pagos') cargarPagos();
        if (targetWindowId === 'window-recursos') consultarDisponibilidad();
        if (targetWindowId === 'window-historial') cargarHistorial();
    }

    navButtons.forEach(btn => {
        btn.addEventListener('click', () => switchWindow(btn.getAttribute('data-target')));
    });

    // Si hay ?paquete=... en la URL, abrir directamente la pestaña de Cotizaciones
    const paramsInicial = new URLSearchParams(window.location.search);
    if (paramsInicial.get('paquete') && document.getElementById('window-cotizaciones')) {
        switchWindow('window-cotizaciones');
    } else if (navButtons.length > 0) {
        switchWindow(navButtons[0].getAttribute('data-target'));
    }

    // ---------------------------------------------------------------
    // CLIENTES (RF-01) con validación de Teléfono y RFC
    // ---------------------------------------------------------------
    const formCliente = document.getElementById('form-cliente');
    if (formCliente) {
        const inputCliTel = document.getElementById('cli-contacto');
        const inputCliRfc = document.getElementById('cli-fiscales');
        const msgCliTel = document.getElementById('msg-cli-tel');
        const msgCliRfc = document.getElementById('msg-cli-rfc');

        inputCliTel.addEventListener('input', () => {
            if (!inputCliTel.value.trim()) { msgCliTel.textContent = ''; return; }
            if (validarTelefonoJS(inputCliTel.value)) {
                msgCliTel.textContent = 'Teléfono válido (10 dígitos).';
                msgCliTel.style.color = '#10b981';
            } else {
                msgCliTel.textContent = 'Ingrese un teléfono válido de 10 dígitos (ej. 272-123-4567).';
                msgCliTel.style.color = '#ef4444';
            }
        });

        inputCliRfc.addEventListener('input', () => {
            inputCliRfc.value = inputCliRfc.value.toUpperCase();
            if (!inputCliRfc.value.trim()) { msgCliRfc.textContent = ''; return; }
            if (validarRFCJS(inputCliRfc.value)) {
                msgCliRfc.textContent = inputCliRfc.value.length === 12
                    ? 'RFC válido (Persona Moral - 12 caracteres).'
                    : 'RFC válido (Persona Física - 13 caracteres).';
                msgCliRfc.style.color = '#10b981';
            } else {
                msgCliRfc.textContent = 'RFC inválido: 3-4 letras + fecha YYMMDD válida + 3 caracteres homoclave.';
                msgCliRfc.style.color = '#ef4444';
            }
        });

        formCliente.addEventListener('submit', async (e) => {
            e.preventDefault();
            const contacto = inputCliTel.value.trim();
            const rfc = inputCliRfc.value.trim().toUpperCase();

            if (!validarTelefonoJS(contacto)) {
                showAlert('Número de teléfono inválido. Debe tener exactamente 10 dígitos.', 'danger');
                inputCliTel.focus();
                return;
            }
            if (!validarRFCJS(rfc)) {
                showAlert('El RFC ingresado no es válido (12 caracteres Persona Moral o 13 Persona Física).', 'danger');
                inputCliRfc.focus();
                return;
            }

            const data = {
                nombre: document.getElementById('cli-nombre').value,
                contacto: contacto,
                direccion: document.getElementById('cli-direccion').value,
                datos_fiscales: rfc
            };
            try {
                const result = await postJSON('guardar_cliente.php', data);
                showAlert(result.message, result.success ? 'success' : 'danger');
                if (result.success) {
                    formCliente.reset();
                    msgCliTel.textContent = '';
                    msgCliRfc.textContent = '';
                    setTimeout(() => location.reload(), 900);
                }
            } catch (err) {
                showAlert('Error al conectar con el servidor.', 'danger');
            }
        });
    }

    // ---------------------------------------------------------------
    // PAQUETES PREDETERMINADOS DE AUDIO E ILUMINACIÓN
    // ---------------------------------------------------------------
    let paquetesCache = [];

    function aplicarPaqueteACotizacion(idPaquete) {
        if (!idPaquete) return;
        const paq = paquetesCache.find(p => String(p.id_paquete) === String(idPaquete));
        if (!paq) return;

        // Formulario Administrador
        const selectPaquete = document.getElementById('cot-paquete');
        const descInput = document.getElementById('cot-desc');
        const montoInput = document.getElementById('cot-monto');
        if (descInput && montoInput) {
            if (selectPaquete) selectPaquete.value = String(paq.id_paquete);
            descInput.value = `${paq.nombre}: ${paq.descripcion}`;
            montoInput.value = parseFloat(paq.costo).toFixed(2);

            const incluidos = String(paq.servicios_incluidos || '').split(',').map(s => s.trim());
            document.querySelectorAll('input[name="servicio"]').forEach(cb => {
                cb.checked = incluidos.includes(cb.value);
            });

            showAlert(`Paquete "${paq.nombre}" aplicado al formulario.`, 'success');
            document.getElementById('form-cotizacion')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Formulario Solicitud de Cotización del Cliente
        const cliSelectPaq = document.getElementById('sol-cot-paquete');
        const cliDescInput = document.getElementById('sol-cot-desc');
        const cliMontoInput = document.getElementById('sol-cot-monto');
        if (cliDescInput && cliMontoInput) {
            if (cliSelectPaq) cliSelectPaq.value = String(paq.id_paquete);
            cliDescInput.value = `${paq.nombre}: ${paq.descripcion}`;
            cliMontoInput.value = parseFloat(paq.costo).toFixed(2);

            const incluidos = String(paq.servicios_incluidos || '').split(',').map(s => s.trim());
            document.querySelectorAll('input[name="sol_servicio"]').forEach(cb => {
                cb.checked = incluidos.includes(cb.value);
            });

            showAlert(`Paquete "${paq.nombre}" seleccionado para tu solicitud.`, 'success');
            document.getElementById('form-solicitud-cotizacion')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function renderizarPaquetesGrid() {
        const grid = document.getElementById('grid-paquetes-cotizacion');
        if (!grid) return;

        const filtroCat = document.getElementById('filtro-categoria-paquete')?.value || 'Todos';
        const esAdmin = document.getElementById('form-cotizacion') !== null;
        const esClienteForm = document.getElementById('form-solicitud-cotizacion') !== null;
        const puedeSeleccionar = esAdmin || esClienteForm;

        const filtrados = paquetesCache.filter(p => filtroCat === 'Todos' || p.categoria === filtroCat);
        if (filtrados.length === 0) {
            grid.innerHTML = '<p style="color:#666;">No hay paquetes disponibles en esta categoría.</p>';
            return;
        }

        grid.innerHTML = '';
        filtrados.forEach(p => {
            const card = document.createElement('div');
            card.className = 'paquete-card';
            const textoBtn = esAdmin ? 'Usar en Cotización' : 'Seleccionar Paquete';
            card.innerHTML = `
                <div class="paquete-header">
                    <span class="paquete-cat">${esc(p.categoria)}</span>
                    <span class="paquete-precio">${money(p.costo)}</span>
                </div>
                <h4 class="paquete-titulo">${esc(p.nombre)}</h4>
                <p class="paquete-desc">${esc(p.descripcion)}</p>
                <div class="paquete-footer">
                    <small><strong>Incluye servicios:</strong> ${esc(p.servicios_incluidos)}</small>
                    ${puedeSeleccionar ? `<button type="button" class="btn-sm btn-aprobar btn-usar-paquete" data-id="${p.id_paquete}" style="margin-top:0.6rem; width:100%;">${textoBtn}</button>` : ''}
                </div>
            `;
            grid.appendChild(card);
        });

        if (puedeSeleccionar) {
            grid.querySelectorAll('.btn-usar-paquete').forEach(btn => {
                btn.addEventListener('click', () => aplicarPaqueteACotizacion(btn.dataset.id));
            });
        }
    }

    const PAQUETES_DEFAULT_LOCAL = [
        {
            id_paquete: 1,
            nombre: 'Paquete Audio Esencial (Hasta 80 personas)',
            categoria: 'Audio',
            descripcion: '2 Bocinas amplificadas de 15" en tripié, consola mezcladora de 8 canales, 2 micrófonos inalámbricos Shure, cableado completo y técnico operador en sitio.',
            servicios_incluidos: 'Audio',
            costo: '4500.00'
        },
        {
            id_paquete: 2,
            nombre: 'Paquete Audio Pro Concierto / Conferencia (Hasta 250 personas)',
            categoria: 'Audio',
            descripcion: 'Sistema Line Array JBL (4 módulos + 2 subwoofers de 18"), consola digital Yamaha QL5, 4 micrófonos inalámbricos, monitores de piso e ingeniero de audio en sala.',
            servicios_incluidos: 'Audio',
            costo: '12500.00'
        },
        {
            id_paquete: 3,
            nombre: 'Paquete Iluminación Ambiental & Fiesta',
            categoria: 'Iluminación',
            descripcion: '8 Par LED RGBW arquitectónicos, 4 cabezas móviles Beam 230W en estructura truss vertical, controlador DMX y máquina de humo.',
            servicios_incluidos: 'Iluminación',
            costo: '5200.00'
        },
        {
            id_paquete: 4,
            nombre: 'Paquete Iluminación Escénica Espectáculo',
            categoria: 'Iluminación',
            descripcion: 'Puente truss de 6 metros, 8 cabezas móviles Beam 230W, 12 Par LED RGBW, 2 máquinas de chispas frías, controlador DMX GrandMA2 y operador dedicado.',
            servicios_incluidos: 'Iluminación',
            costo: '11800.00'
        },
        {
            id_paquete: 5,
            nombre: 'Paquete Combo Audio + Iluminación Básico',
            categoria: 'Audio e Iluminación',
            descripcion: 'Audio para 100 personas (2 bafles activos + subwoofer 18" + 2 micrófonos inalámbricos) + Iluminación ambiental (6 Par LED RGBW + 2 cabezas móviles Beam + humo) con técnico operador.',
            servicios_incluidos: 'Audio,Iluminación',
            costo: '8500.00'
        },
        {
            id_paquete: 6,
            nombre: 'Paquete Combo Audio + Iluminación Gala / Gran Evento',
            categoria: 'Audio e Iluminación',
            descripcion: 'Sistema Line Array completo + consola digital + 4 micrófonos Shure + puente truss con 8 cabezas móviles Beam, 12 Par LED, chispas frías, transporte y 2 técnicos especializados.',
            servicios_incluidos: 'Audio,Iluminación',
            costo: '21500.00'
        }
    ];

    function poblarSelectPaquetes() {
        ['cot-paquete', 'sol-cot-paquete'].forEach(selId => {
            const selectPaquete = document.getElementById(selId);
            if (selectPaquete) {
                selectPaquete.innerHTML = '<option value="">-- Cotización personalizada (sin paquete base) --</option>';
                paquetesCache.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id_paquete;
                    opt.textContent = `[${p.categoria}] ${p.nombre} - ${money(p.costo)}`;
                    selectPaquete.appendChild(opt);
                });
            }
        });

        const params = new URLSearchParams(window.location.search);
        const paqueteUrl = params.get('paquete');
        if (paqueteUrl && !window.__paqueteUrlAplicado) {
            const encontrado = paquetesCache.find(p => p.nombre.toLowerCase() === paqueteUrl.toLowerCase());
            if (encontrado) {
                window.__paqueteUrlAplicado = true;
                aplicarPaqueteACotizacion(encontrado.id_paquete);
            }
        }
    }

    async function cargarPaquetesPredeterminados() {
        const grid = document.getElementById('grid-paquetes-cotizacion');
        if (!grid) return;

        try {
            const response = await fetch('obtener_paquetes.php', { cache: 'no-store' });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const res = await response.json();
            paquetesCache = (res && res.success && Array.isArray(res.paquetes) && res.paquetes.length > 0)
                ? res.paquetes
                : PAQUETES_DEFAULT_LOCAL;
        } catch (err) {
            paquetesCache = PAQUETES_DEFAULT_LOCAL;
        }

        poblarSelectPaquetes();
        renderizarPaquetesGrid();
    }

    document.getElementById('cot-paquete')?.addEventListener('change', (e) => aplicarPaqueteACotizacion(e.target.value));
    document.getElementById('sol-cot-paquete')?.addEventListener('change', (e) => aplicarPaqueteACotizacion(e.target.value));
    document.getElementById('filtro-categoria-paquete')?.addEventListener('change', renderizarPaquetesGrid);

    // Solicitud de cotización enviada directamente por el Cliente
    const formSolicitudCotizacion = document.getElementById('form-solicitud-cotizacion');
    if (formSolicitudCotizacion) {
        formSolicitudCotizacion.addEventListener('submit', async (e) => {
            e.preventDefault();
            const servicios = Array.from(document.querySelectorAll('input[name="sol_servicio"]:checked')).map(cb => cb.value);
            if (servicios.length === 0) {
                showAlert('Debe seleccionar al menos un servicio audiovisual.', 'danger');
                return;
            }
            const data = {
                nombre_evento: document.getElementById('sol-cot-evento').value,
                descripcion: document.getElementById('sol-cot-desc').value,
                servicios: servicios,
                monto: parseFloat(document.getElementById('sol-cot-monto').value) || 0,
                vigencia: document.getElementById('sol-cot-fecha').value
            };
            try {
                const result = await postJSON('guardar_cotizacion.php', data);
                showAlert(result.message, result.success ? 'success' : 'danger');
                if (result.success) {
                    formSolicitudCotizacion.reset();
                    cargarCotizaciones();
                }
            } catch (err) {
                showAlert('Error al enviar la solicitud de cotización.', 'danger');
            }
        });
    }

    // ---------------------------------------------------------------
    // COTIZACIONES (RF-02, RF-03)
    // ---------------------------------------------------------------
    const formCotizacion = document.getElementById('form-cotizacion');
    if (formCotizacion) {
        formCotizacion.addEventListener('submit', async (e) => {
            e.preventDefault();

            const servicios = Array.from(document.querySelectorAll('input[name="servicio"]:checked')).map(cb => cb.value);
            if (servicios.length === 0) {
                showAlert('Debe seleccionar al menos un servicio audiovisual.', 'danger');
                return;
            }

            const data = {
                id_cliente: document.getElementById('cot-cliente').value,
                nombre_evento: document.getElementById('cot-evento').value,
                descripcion: document.getElementById('cot-desc').value,
                servicios: servicios,
                monto: parseFloat(document.getElementById('cot-monto').value),
                vigencia: document.getElementById('cot-vigencia').value
            };

            try {
                const result = await postJSON('guardar_cotizacion.php', data);
                showAlert(result.message, result.success ? 'success' : 'danger');
                if (result.success) {
                    formCotizacion.reset();
                    cargarCotizaciones();
                }
            } catch (err) {
                showAlert('Error al conectar con el servidor.', 'danger');
            }
        });
    }

    async function cargarCotizaciones() {
        const tbody = document.getElementById('tabla-cotizaciones-body');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Cargando...</td></tr>';
        try {
            const data = await (await fetch('obtener_cotizaciones.php')).json();
            const cotizaciones = data.cotizaciones || [];

            if (cotizaciones.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay cotizaciones registradas.</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            cotizaciones.forEach(cot => {
                const tr = document.createElement('tr');
                let acciones = '<td>-</td>';

                if (cot.estado === 'Pendiente' && (rolUsuario === 'Cliente' || rolUsuario === 'Administrador')) {
                    acciones = `<td>
                        <button class="btn-sm btn-aprobar" data-id="${cot.id_cotizacion}">Aprobar</button>
                        <button class="btn-sm btn-rechazar" data-id="${cot.id_cotizacion}">Rechazar</button>
                    </td>`;
                } else if (cot.id_contrato) {
                    acciones = `<td><a class="btn-sm" href="ver_contrato.php?id_contrato=${cot.id_contrato}" target="_blank">Contrato #${cot.id_contrato}</a></td>`;
                } else if (cot.estado === 'Aprobada' && rolUsuario === 'Administrador') {
                    acciones = `<td><span style="font-size:0.8rem; color:#059669; font-weight:600;">Lista para contrato</span></td>`;
                }

                tr.innerHTML = `
                    <td>#${cot.id_cotizacion}</td>
                    <td>${esc(cot.cliente)}</td>
                    <td>${esc(cot.evento || '')}</td>
                    <td>${esc(cot.servicios || '')}</td>
                    <td>${money(cot.monto_total)}</td>
                    <td>${esc(cot.vigencia)}</td>
                    <td><span class="badge badge-${esc(cot.estado.toLowerCase())}">${esc(cot.estado)}</span></td>
                    ${acciones}
                `;
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.btn-aprobar, .btn-rechazar').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const accion = btn.classList.contains('btn-aprobar') ? 'Aprobar' : 'Rechazar';
                    const result = await postJSON('aprobar_rechazar_cotizacion.php', { id_cotizacion: btn.dataset.id, accion });
                    showAlert(result.message, result.success ? 'success' : 'danger');
                    if (result.success) {
                        if (rolUsuario === 'Administrador' && accion === 'Aprobar') {
                            setTimeout(() => location.reload(), 800);
                        } else {
                            cargarCotizaciones();
                        }
                    }
                });
            });
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Error al consultar la base de datos.</td></tr>';
        }
    }

    // ---------------------------------------------------------------
    // CONTRATOS (RF-04) + CANCELACIÓN Y REPROGRAMACIÓN
    // ---------------------------------------------------------------
    const formContrato = document.getElementById('form-contrato');
    if (formContrato) {
        formContrato.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = {
                id_cotizacion: document.getElementById('con-cotizacion').value,
                fecha_evento: document.getElementById('con-fecha').value,
                horario: document.getElementById('con-horario').value,
                tipo_evento: document.getElementById('con-tipo-evento').value,
                ubicacion: document.getElementById('con-ubicacion').value,
                condiciones: document.getElementById('con-condiciones').value
            };
            const result = await postJSON('generar_contrato.php', data);
            showAlert(result.message, result.success ? 'success' : 'danger');
            if (result.success) {
                formContrato.reset();
                if (result.id_contrato) window.open('ver_contrato.php?id_contrato=' + result.id_contrato, '_blank');
                setTimeout(() => location.reload(), 900);
            }
        });
    }

    let contratosCache = [];
    let modalCtx = null;

    async function cargarContratos() {
        const tbody = document.getElementById('tabla-contratos-body');
        if (!tbody) return;
        const esAdmin = rolUsuario === 'Administrador';
        const cols = esAdmin ? 7 : 6;
        tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;">Cargando...</td></tr>`;
        try {
            const data = await (await fetch('obtener_contratos.php')).json();
            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center; color:red;">${esc(data.message)}</td></tr>`;
                return;
            }
            contratosCache = data.contratos || [];
            if (contratosCache.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;">No hay contratos registrados.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            contratosCache.forEach(c => {
                const estado = c.estatus === 'Cancelado' ? 'Cancelado' : c.estado_evento;
                let acciones = `<a class="btn-sm" href="ver_contrato.php?id_contrato=${c.id_contrato}" target="_blank">Ver</a> `;
                if (c.puede_modificar) acciones += `<button class="btn-sm btn-fecha" data-id="${c.id_contrato}">Cambiar fecha</button> `;
                if (c.puede_cancelar) acciones += `<button class="btn-sm btn-rechazar btn-cancelar-contrato" data-id="${c.id_contrato}">Cancelar</button>`;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>#${c.id_contrato}</td>
                    ${esAdmin ? `<td>${esc(c.cliente)}</td>` : ''}
                    <td>${esc(c.nombre_evento)}</td>
                    <td>${esc(c.fecha_evento)}</td>
                    <td>${money(c.total_pagado)} / ${money(c.monto_total)}</td>
                    <td><span class="badge badge-${esc(estado.toLowerCase())}">${esc(estado)}</span></td>
                    <td>${acciones}</td>`;
                tbody.appendChild(tr);
            });
            tbody.querySelectorAll('.btn-cancelar-contrato').forEach(b => b.addEventListener('click', () => abrirModal('cancelar', b.dataset.id)));
            tbody.querySelectorAll('.btn-fecha').forEach(b => b.addEventListener('click', () => abrirModal('fecha', b.dataset.id)));
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center; color:red;">Error al consultar los contratos.</td></tr>`;
        }
    }

    const modal = document.getElementById('modal-contrato');
    function cerrarModal() { if (modal) modal.classList.add('hidden'); modalCtx = null; }

    function abrirModal(accion, idContrato) {
        const c = contratosCache.find(x => String(x.id_contrato) === String(idContrato));
        if (!c || !modal) return;
        modalCtx = { accion, contrato: c };
        document.getElementById('modal-motivo').value = '';
        const resumen = document.getElementById('modal-resumen');
        const grupoFecha = document.getElementById('modal-grupo-fecha');
        const btn = document.getElementById('modal-confirmar');

        if (accion === 'cancelar') {
            const k = c.cancelacion;
            document.getElementById('modal-titulo').textContent = `Cancelar contrato #${c.id_contrato}`;
            document.getElementById('modal-label-motivo').textContent = 'Motivo de la cancelación:';
            grupoFecha.classList.add('hidden');
            btn.textContent = 'Cancelar contrato';
            btn.className = 'btn-sm btn-rechazar';
            resumen.innerHTML = `
                <p>Evento <strong>${esc(c.nombre_evento)}</strong> del <strong>${esc(c.fecha_evento)}</strong>
                   (${k.dias} día${k.dias === 1 ? '' : 's'} de anticipación).</p>
                <table class="tabla">
                    <tr><td>Descuento por cancelación (${k.porcentaje}% del total)</td><td>${money(k.penalizacion)}</td></tr>
                    <tr><td>Total pagado</td><td>${money(c.total_pagado)}</td></tr>
                    <tr><td>Descuento retenido</td><td>${money(k.retenido)}</td></tr>
                    <tr><td><strong>Reembolso al cliente</strong></td><td><strong>${money(k.reembolso)}</strong></td></tr>
                    ${k.adeudo > 0 ? `<tr><td>Saldo pendiente a cargo del cliente</td><td>${money(k.adeudo)}</td></tr>` : ''}
                </table>
                <p style="color:#c0392b;">Esta acción no se puede deshacer.</p>`;
        } else {
            document.getElementById('modal-titulo').textContent = `Cambiar fecha del contrato #${c.id_contrato}`;
            document.getElementById('modal-label-motivo').textContent = 'Motivo del cambio (opcional):';
            grupoFecha.classList.remove('hidden');
            const f = document.getElementById('modal-fecha');
            const manana = new Date(); manana.setDate(manana.getDate() + 1);
            f.min = manana.toISOString().slice(0, 10);
            f.value = '';
            btn.textContent = 'Guardar nueva fecha';
            btn.className = 'btn-sm btn-aprobar';
            resumen.innerHTML = `<p>Fecha actual: <strong>${esc(c.fecha_evento)}</strong>. El evento quedará como <em>Reprogramado</em> y se validará la disponibilidad de los recursos ya asignados.</p>`
                + (rolUsuario === 'Cliente' ? `<p style="font-size:.85rem;">Los clientes pueden cambiar la fecha con al menos ${c.min_dias_cliente} días de anticipación.</p>` : '');
        }
        modal.classList.remove('hidden');
    }

    if (modal) {
        document.getElementById('modal-cerrar').addEventListener('click', cerrarModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModal(); });
        document.getElementById('modal-confirmar').addEventListener('click', async () => {
            if (!modalCtx) return;
            const { accion, contrato } = modalCtx;
            const motivo = document.getElementById('modal-motivo').value.trim();
            let result;
            try {
                if (accion === 'cancelar') {
                    if (!motivo) { showAlert('Indique el motivo de la cancelación.', 'danger'); return; }
                    result = await postJSON('cancelar_contrato.php', { id_contrato: contrato.id_contrato, motivo });
                } else {
                    const nueva = document.getElementById('modal-fecha').value;
                    if (!nueva) { showAlert('Seleccione la nueva fecha.', 'danger'); return; }
                    result = await postJSON('modificar_fecha.php', { id_evento: contrato.id_evento, nueva_fecha: nueva, motivo });
                }
            } catch (err) {
                showAlert('Error al conectar con el servidor.', 'danger');
                return;
            }
            showAlert(result.message, result.success ? 'success' : 'danger');
            if (result.success) { cerrarModal(); cargarContratos(); }
        });
    }

    // ---------------------------------------------------------------
    // EVENTOS (RF-05, RF-06, RF-11)
    // ---------------------------------------------------------------
    let eventoFinalizarId = null;
    const modalFinalizar = document.getElementById('modal-finalizar-evento');

    async function cargarEventos() {
        const tbody = document.getElementById('tabla-eventos-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Cargando eventos...</td></tr>';
        try {
            const data = await (await fetch('obtener_eventos.php')).json();
            const lista = data.eventos || [];
            if (lista.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay eventos programados aún.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            lista.forEach(ev => {
                let acciones = '-';
                if (ev.estado === 'Programado' || ev.estado === 'Reprogramado') {
                    acciones = `<button class="btn-sm btn-confirmar" data-id="${ev.id_evento}">Confirmar (50%)</button>`;
                } else if (ev.estado === 'Confirmado') {
                    acciones = `<button class="btn-sm btn-finalizar" data-id="${ev.id_evento}" data-nombre="${esc(ev.nombre_evento)}">Finalizar</button>`;
                }

                const badgePago = ev.anticipo_cubierto
                    ? `<span style="color:#059669; font-weight:600;">${money(ev.total_pagado)}</span> / ${money(ev.monto_total)}`
                    : `<span style="color:#d97706; font-weight:600;">${money(ev.total_pagado)}</span> / ${money(ev.monto_total)}`;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>#${ev.id_evento}</td>
                    <td><strong>${esc(ev.cliente)}</strong><br><small style="color:#64748b;">${esc(ev.nombre_evento)}</small></td>
                    <td>${esc(ev.fecha)}<br><small style="color:#64748b;">${esc(String(ev.horario || '').slice(0,5))} hrs</small></td>
                    <td>${esc(ev.ubicacion)}<br><small style="color:#64748b;">${esc(ev.tipo_evento)}</small></td>
                    <td>${badgePago}</td>
                    <td style="max-width:220px; font-size:0.83rem;">${esc(ev.recursos_asignados)}</td>
                    <td><span class="badge badge-${esc(ev.estado.toLowerCase())}">${esc(ev.estado)}</span></td>
                    <td>${acciones}</td>
                `;
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.btn-confirmar').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const result = await postJSON('confirmar_evento.php', { id_evento: btn.dataset.id });
                    showAlert(result.message, result.success ? 'success' : 'danger');
                    if (result.success) cargarEventos();
                });
            });

            tbody.querySelectorAll('.btn-finalizar').forEach(btn => {
                btn.addEventListener('click', () => {
                    eventoFinalizarId = btn.dataset.id;
                    document.getElementById('modal-fin-titulo').textContent = `Finalizar Evento #${eventoFinalizarId} - ${btn.dataset.nombre}`;
                    document.getElementById('fin-resultado').value = 'Evento realizado con éxito y sin incidentes.';
                    document.getElementById('fin-seguimiento').value = '';
                    modalFinalizar?.classList.remove('hidden');
                });
            });
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Error al consultar eventos.</td></tr>';
        }
    }

    if (modalFinalizar) {
        document.getElementById('btn-fin-cerrar').addEventListener('click', () => {
            modalFinalizar.classList.add('hidden');
            eventoFinalizarId = null;
        });
        document.getElementById('btn-fin-confirmar').addEventListener('click', async () => {
            if (!eventoFinalizarId) return;
            const resultado = document.getElementById('fin-resultado').value.trim();
            const seguimiento = document.getElementById('fin-seguimiento').value.trim();
            if (!resultado) {
                showAlert('Indique el resultado operativo del evento.', 'danger');
                return;
            }
            const result = await postJSON('finalizar_evento.php', {
                id_evento: eventoFinalizarId,
                resultado,
                seguimiento
            });
            showAlert(result.message, result.success ? 'success' : 'danger');
            if (result.success) {
                modalFinalizar.classList.add('hidden');
                eventoFinalizarId = null;
                cargarEventos();
            }
        });
    }

    // ---------------------------------------------------------------
    // PAGOS (RF-07)
    // ---------------------------------------------------------------
    const formPago = document.getElementById('form-pago');
    if (formPago) {
        formPago.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = {
                id_evento: document.getElementById('pago-evento').value,
                tipo: document.getElementById('pago-tipo').value,
                monto: parseFloat(document.getElementById('pago-monto').value),
                metodo: document.getElementById('pago-metodo').value,
                referencia: document.getElementById('pago-referencia').value,
                fecha: document.getElementById('pago-fecha').value
            };
            const result = await postJSON('registrar_pago.php', data);
            showAlert(result.message, result.success ? 'success' : 'danger');
            if (result.success) {
                formPago.reset();
                cargarPagos();
            }
        });
    }

    async function cargarPagos() {
        const tbody = document.getElementById('tabla-pagos-body');
        if (!tbody) return;
        const esAdmin = rolUsuario === 'Administrador';
        const cols = esAdmin ? 8 : 7;
        tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;">Cargando pagos...</td></tr>`;
        try {
            const data = await (await fetch('obtener_pagos.php')).json();
            const pagos = data.pagos || [];
            if (pagos.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;">Aún no hay pagos registrados.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            pagos.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>#${p.id_pago}</td>
                    <td>Evento #${p.id_evento} - ${esc(p.nombre_evento)}</td>
                    ${esAdmin ? `<td>${esc(p.cliente)}</td>` : ''}
                    <td><span class="badge badge-aprobada">${esc(p.tipo)}</span></td>
                    <td>${esc(p.metodo)}</td>
                    <td>${esc(p.referencia || '-')}</td>
                    <td>${esc(p.fecha)}</td>
                    <td><strong>${money(p.monto)}</strong></td>
                `;
                tbody.appendChild(tr);
            });
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center; color:red;">Error al consultar los pagos.</td></tr>`;
        }
    }

    // ---------------------------------------------------------------
    // RECURSOS Y DISPONIBILIDAD (RF-08, RF-09)
    // ---------------------------------------------------------------
    let recursosCache = [];
    const btnDisponibilidad = document.getElementById('btn-consultar-disponibilidad');
    const filtroTipoRecurso = document.getElementById('filtro-tipo-recurso');
    const filtroEstadoRecurso = document.getElementById('filtro-estado-recurso');

    function renderizarTablaRecursos() {
        const tbody = document.getElementById('tabla-disponibilidad-body');
        if (!tbody) return;

        const tablaDisp = tbody.closest('table');
        const esAdmin = tablaDisp && tablaDisp.dataset.rol === 'Administrador';

        const tipoSel = filtroTipoRecurso ? filtroTipoRecurso.value : 'Todos';
        const estadoSel = filtroEstadoRecurso ? filtroEstadoRecurso.value : 'Todos';

        const filtrados = recursosCache.filter(r => {
            const okTipo = (tipoSel === 'Todos') || (r.tipo === tipoSel);
            const okEstado = (estadoSel === 'Todos') || (r.estado_disponibilidad === estadoSel);
            return okTipo && okEstado;
        });

        tbody.innerHTML = '';
        if (filtrados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay recursos que coincidan con el filtro seleccionado.</td></tr>';
            return;
        }

        filtrados.forEach(r => {
            const estDisp = r.estado_disponibilidad;
            const estOperativo = r.estado_recurso || 'Disponible';
            let claseEstado = 'badge-rechazada';
            let textoEstado = 'No disponible (Ocupado)';
            let detalle = `Evento #${esc(r.id_evento)} - ${esc(r.evento_asignado || 'Reservado')}`;

            if (estDisp === 'Disponible') {
                claseEstado = 'badge-aprobada';
                textoEstado = 'Disponible para rentar';
                detalle = '<span style="color:#059669; font-weight:600;">Listo para asignar</span>';
            } else if (estDisp === 'En reparación') {
                claseEstado = 'badge-reparacion';
                textoEstado = 'En reparación';
                detalle = '<span style="color:#b45309;">Fuera de servicio (En taller)</span>';
            } else if (estDisp === 'Dañado') {
                claseEstado = 'badge-danado';
                textoEstado = 'Dañado';
                detalle = '<span style="color:#dc2626;">No apto para renta</span>';
            }

            const btnEliminar = esAdmin
                ? `<button type="button" class="btn-sm btn-rechazar btn-eliminar-recurso" data-id="${r.id_recurso}" data-nombre="${esc(r.nombre)}" title="Eliminar recurso">Eliminar</button>`
                : '';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>#${r.id_recurso}</td>
                <td>${esc(r.nombre)}</td>
                <td>${esc(r.tipo)}</td>
                <td><span class="badge ${claseEstado}">${textoEstado}</span></td>
                <td>${detalle}</td>
                <td>
                    <div class="acciones-recurso">
                        <select class="select-estado-recurso" data-id="${r.id_recurso}" aria-label="Estado del recurso">
                            <option value="Disponible" ${estOperativo === 'Disponible' ? 'selected' : ''}>Disponible</option>
                            <option value="En reparación" ${estOperativo === 'En reparación' ? 'selected' : ''}>En reparación</option>
                            <option value="Dañado" ${estOperativo === 'Dañado' ? 'selected' : ''}>Dañado</option>
                        </select>
                        ${btnEliminar}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.select-estado-recurso').forEach(sel => {
            sel.addEventListener('change', async () => {
                const idRecurso = sel.dataset.id;
                const nuevoEstado = sel.value;
                try {
                    const res = await postJSON('actualizar_estado_recurso.php', {
                        id_recurso: idRecurso,
                        estado: nuevoEstado
                    });
                    showAlert(res.message, res.success ? 'success' : 'danger');
                    if (res.success) {
                        consultarDisponibilidad();
                    }
                } catch (err) {
                    showAlert('Error al actualizar el estado del recurso.', 'danger');
                }
            });
        });

        if (esAdmin) {
            tbody.querySelectorAll('.btn-eliminar-recurso').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const idRecurso = btn.dataset.id;
                    const nombreRec = btn.dataset.nombre;
                    if (!confirm(`¿Seguro que deseas eliminar "${nombreRec}" (#${idRecurso}) del inventario? Esta acción no se puede deshacer.`)) {
                        return;
                    }
                    try {
                        const res = await postJSON('eliminar_recurso.php', { id_recurso: idRecurso });
                        showAlert(res.message, res.success ? 'success' : 'danger');
                        if (res.success) {
                            const optSelect = document.querySelector(`#rec-recurso option[value="${idRecurso}"]`);
                            if (optSelect) optSelect.remove();
                            consultarDisponibilidad();
                        }
                    } catch (err) {
                        showAlert('Error al eliminar el recurso.', 'danger');
                    }
                });
            });
        }
    }

    async function consultarDisponibilidad() {
        const inputFecha = document.getElementById('disp-fecha');
        const tbody = document.getElementById('tabla-disponibilidad-body');
        const tbodyTipos = document.getElementById('tabla-resumen-tipos-body');
        if (!inputFecha || !tbody) return;

        const fecha = inputFecha.value || new Date().toISOString().slice(0, 10);
        inputFecha.value = fecha;

        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Consultando inventario y disponibilidad...</td></tr>';
        try {
            const data = await (await fetch('validar_disponibilidad.php?fecha=' + encodeURIComponent(fecha))).json();
            if (!data.success) {
                showAlert(data.message || 'Error al consultar recursos.', 'danger');
                return;
            }

            const rg = data.resumen_general || { total: 0, disponibles: 0, ocupados: 0 };
            document.getElementById('stat-total').textContent = rg.total;
            document.getElementById('stat-disponibles').textContent = rg.disponibles;
            document.getElementById('stat-ocupados').textContent = rg.ocupados;

            if (tbodyTipos) {
                const tipos = data.resumen_por_tipo || [];
                tbodyTipos.innerHTML = '';
                if (tipos.length === 0) {
                    tbodyTipos.innerHTML = '<tr><td colspan="4" style="text-align:center;">Sin recursos registrados.</td></tr>';
                } else {
                    tipos.forEach(t => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong>${esc(t.tipo)}</strong></td>
                            <td>${t.total} unidades</td>
                            <td><span class="badge badge-aprobada">${t.disponibles} disponibles</span></td>
                            <td><span class="badge badge-rechazada">${t.ocupados} no disponibles</span></td>
                        `;
                        tbodyTipos.appendChild(tr);
                    });
                }
            }

            recursosCache = data.recursos || [];
            renderizarTablaRecursos();
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:red;">Error al consultar la disponibilidad de recursos.</td></tr>';
        }
    }

    btnDisponibilidad?.addEventListener('click', consultarDisponibilidad);
    filtroTipoRecurso?.addEventListener('change', renderizarTablaRecursos);
    filtroEstadoRecurso?.addEventListener('change', renderizarTablaRecursos);

    const formRecurso = document.getElementById('form-recurso');
    if (formRecurso) {
        const eventoSelect = document.getElementById('rec-evento');
        eventoSelect.addEventListener('change', () => {
            const fechaEvento = eventoSelect.options[eventoSelect.selectedIndex]?.dataset.fecha;
            const inputFecha = document.getElementById('disp-fecha');
            if (fechaEvento && inputFecha) {
                inputFecha.value = fechaEvento;
                consultarDisponibilidad();
            }
        });

        formRecurso.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fecha = eventoSelect.options[eventoSelect.selectedIndex]?.dataset.fecha;
            const data = {
                id_evento: eventoSelect.value,
                id_recurso: document.getElementById('rec-recurso').value,
                fecha: fecha
            };
            const result = await postJSON('asignar_recurso.php', data);
            showAlert(result.message, result.success ? 'success' : 'danger');
            if (result.success) {
                consultarDisponibilidad();
            }
        });
    }

    const formNuevoRecurso = document.getElementById('form-nuevo-recurso');
    if (formNuevoRecurso) {
        formNuevoRecurso.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = {
                nombre: document.getElementById('nuevo-rec-nombre').value,
                tipo: document.getElementById('nuevo-rec-tipo').value,
                cantidad: parseInt(document.getElementById('nuevo-rec-cantidad').value, 10)
            };
            try {
                const result = await postJSON('guardar_recurso.php', data);
                showAlert(result.message, result.success ? 'success' : 'danger');
                if (result.success) {
                    formNuevoRecurso.reset();
                    setTimeout(() => location.reload(), 900);
                }
            } catch (err) {
                showAlert('Error al agregar recurso al inventario.', 'danger');
            }
        });
    }

    // ---------------------------------------------------------------
    // HISTORIAL (RF-11)
    // ---------------------------------------------------------------
    async function cargarHistorial() {
        const tbody = document.getElementById('tabla-historial-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Cargando...</td></tr>';
        try {
            const data = await (await fetch('obtener_historial.php')).json();
            const historial = data.historial || [];
            if (historial.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Aún no hay eventos finalizados.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            historial.forEach(h => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>#${h.id_evento}</td>
                    <td>${esc(h.cliente)}</td>
                    <td>${esc(h.fecha)}</td>
                    <td>${esc(h.tipo_evento)}</td>
                    <td>${esc(h.servicios_brindados)}</td>
                    <td>${esc(h.resultado)}</td>
                    <td>${esc(h.seguimiento_cliente || '-')}</td>
                `;
                tbody.appendChild(tr);
            });
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:red;">Error al consultar el historial.</td></tr>';
        }
    }
});
