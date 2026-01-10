<?php
// ==============================================================================
// SISTEMA DE REPORTE DE NOVEDADES - CENTURIÓN (CUSTODIA DE CAMIONES)
// Refactorización: Senior PHP Dev - Versión Live Card & Smart Share
// Fecha: 26/12/2025
// Actualización: Corrección (Vacaciones solo en Braskem)
// ==============================================================================

date_default_timezone_set('America/Mexico_City');
$fecha_servidor = date('d/m/Y');
$hora_servidor = date('H:i:s a');

// Definición de Archivos de Datos
$archivo_db = 'database_centurion.json';
$archivo_csv = 'historial_centurion.csv';

// ------------------------------------------------------------------------------
// 1. LÓGICA DEL SERVIDOR (BACKEND)
// ------------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);

    // A) Guardar Estado (JSON)
    if (isset($data['action']) && $data['action'] === 'save_db') {
        file_put_contents($archivo_db, json_encode($data['db_payload'], JSON_PRETTY_PRINT));
        echo json_encode(["status" => "ok", "msg" => "Estado guardado"]);
        exit;
    }

    // B) Guardar Historial (CSV)
    if (isset($data['action']) && $data['action'] === 'log_history') {
        $log = $data['payload'];
        $es_nuevo = !file_exists($archivo_csv);
        $fp = fopen($archivo_csv, 'a');
        
        // Encabezados actualizados (Sin Vacaciones en Silao)
        if ($es_nuevo) {
            fputcsv($fp, [
                'Fecha', 'Hora', 'Supervisor', 'Turno', 
                'Braskem_Activos', 'Braskem_Descanso', 'Braskem_Incapacitados', 'Braskem_Comision', 'Braskem_Vacaciones',
                'Silao_Activos', 'Silao_Descanso',
                'Estatus_Unidades', 'Plan_Carga'
            ]);
        }
        
        // Fila de datos actualizada
        fputcsv($fp, [
            date('d/m/Y'), date('h:i:s a'), $log['supervisor'], $log['turno'],
            $log['personal']['braskem']['activos'], 
            $log['personal']['braskem']['descanso'],
            $log['personal']['braskem']['incapacitados'],
            $log['personal']['braskem']['comision'],
            $log['personal']['braskem']['vacaciones'],
            $log['personal']['silao']['activos'], 
            $log['personal']['silao']['descanso'],
            $log['unidades_string'], $log['plan_carga_string']
        ]);
        fclose($fp);
        echo json_encode(["status" => "ok", "msg" => "Historial actualizado"]);
        exit;
    }

    // C) Leer Estado (JSON) al cargar
    if (isset($data['action']) && $data['action'] === 'get_db') {
        if (file_exists($archivo_db)) { echo file_get_contents($archivo_db); } else { echo json_encode([]); }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Centurión - Custodia</title>
    <!-- Librería para generar imagen de la tarjeta -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* ==========================================================================
           ESTILOS ORIGINALES + CARD INTEGRADA
           ========================================================================== */
        :root {
            --primary: #004aad; --secondary: #002a63; --accent: #e63946;
            --bg: #f4f6f9; --text: #333; --white: #ffffff; --border: #ddd;
        }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 850px; margin: 0 auto; background: var(--white); padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        header { text-align: center; border-bottom: 2px solid var(--primary); padding-bottom: 20px; margin-bottom: 25px; }
        header h1 { color: var(--primary); margin: 0; font-size: 1.8rem; }
        .section-title { background: var(--secondary); color: var(--white); padding: 10px 15px; border-radius: 6px; margin: 25px 0 15px; font-weight: bold;}
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; color: var(--secondary); }
        select, input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; font-size: 1rem;}
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
        .checkbox-item { display: flex; align-items: center; font-size: 0.85rem; background: white; padding: 8px; border-radius: 5px; border: 1px solid #ddd; }
        .actions { margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 20px; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; gap: 8px;}
        .btn-primary { background-color: #25D366; color: white; } /* WhatsApp Green */
        .btn-primary:hover { background-color: #128C7E; }
        .btn-save { background-color: var(--primary); color: white; }
        .btn-save:hover { background-color: var(--secondary); }
        .status-bar { margin-top: 10px; padding: 10px; background: #e9ecef; border-radius: 5px; font-size: 0.8rem; text-align: center; }

        /* --- ESTILOS DE LA TARJETA VISUAL (INLINE) --- */
        .preview-container {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 3px dashed #ccc;
        }
        .preview-title {
            text-align: center;
            color: #777;
            margin-bottom: 20px;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }

        /* Diseño de la tarjeta idéntico para que se vea bien en screenshot */
        .whatsapp-card {
            background: white;
            width: 100%;
            max-width: 480px; /* Ancho ideal para móviles */
            margin: 0 auto;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border: 1px solid #e1e1e1;
            position: relative; /* Para html2canvas */
        }
        .card-header { background: var(--primary); color: white; padding: 20px; text-align: center; }
        .card-header h2 { margin: 0; font-size: 1.4rem; text-transform: uppercase; letter-spacing: 1px; }
        .card-meta { background: #f4f6f9; padding: 15px; border-bottom: 2px solid #eee; font-size: 0.9rem; color: #555; }
        .card-meta div { margin-bottom: 5px; }
        .card-body { padding: 20px; background: #fff; }
        .card-section { margin-bottom: 20px; }
        .card-section-title { color: var(--primary); font-weight: bold; border-bottom: 2px solid var(--primary); padding-bottom: 5px; margin-bottom: 10px; display: flex; align-items: center; }
        .card-data-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        .card-data-row span.val { font-weight: bold; color: var(--secondary); }
        
        .unit-list { display: flex; flex-direction: column; gap: 5px; }
        
        .logistica-box { background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 5px solid #ffc107; font-weight: bold; color: #856404; text-align: center;}

        @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🛡️ SISTEMA CENTURIÓN</h1>
        <p>Coordinación de Seguridad - Reporte Operativo</p>
    </header>

    <!-- FORMULARIO DE CAPTURA -->
    <form id="reportForm">
        <div class="grid-2">
            <div class="form-group">
                <label>👤 Supervisor</label>
                <select id="supervisor">
                    <option value="Oscar Leandro Fiscal Temich">Oscar Leandro Fiscal Temich</option>
                    <option value="Joaquín Contreras De Los Santos">Joaquín Contreras De Los Santos</option>
                </select>
            </div>
            <div class="form-group">
                <label>🕒 Turno</label>
                <select id="turno">
                    <option value="DIURNO">☀️ DIURNO</option>
                    <option value="NOCTURNO">🌙 NOCTURNO</option>
                </select>
            </div>
        </div>

        <div class="section-title">👥 Fuerza de Tarea (Personal)</div>
        <div class="grid-2">
            <!-- Sede Braskem Modificada -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-top: 3px solid var(--primary);">
                <h4 style="margin:0 0 10px 0; color:var(--primary);">🏭 Sede Braskem</h4>
                <div class="form-group"><label>Activos</label><input type="number" id="b_act" value="0" min="0"></div>
                <div class="form-group"><label>Descanso</label><input type="number" id="b_desc" value="0" min="0"></div>
                <div class="form-group"><label>Incapacitados</label><input type="number" id="b_incap" value="0" min="0" style="border-color: #ffc107;"></div>
                <div class="form-group"><label>Comisión</label><input type="number" id="b_com" value="0" min="0" style="border-color: #17a2b8;"></div>
                <!-- Vacaciones: Solo en Braskem -->
                <div class="form-group"><label>Vacaciones</label><input type="number" id="b_vac" value="0" min="0" style="border-color: #28a745;"></div>
            </div>
            
            <!-- Sede Silao Modificada (Sin Vacaciones) -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-top: 3px solid var(--secondary);">
                <h4 style="margin:0 0 10px 0; color:var(--secondary);">🏢 Sede Silao</h4>
                <div class="form-group"><label>Activos</label><input type="number" id="s_act" value="0" min="0"></div>
                <div class="form-group"><label>Descanso</label><input type="number" id="s_desc" value="0" min="0"></div>
            </div>
        </div>

        <div class="section-title">🚓 Parque Vehicular (Check = Operativa)</div>
        <div class="checkbox-grid" id="units_container">
            <!-- Hardcoded para control estricto -->
            <div class="checkbox-item"><input type="checkbox" id="u_gp023"><label for="u_gp023">GP023 JÚPITER</label></div>
            <div class="checkbox-item"><input type="checkbox" id="u_gp024"><label for="u_gp024">GP024 PEGASO</label></div>
            <div class="checkbox-item"><input type="checkbox" id="u_gp025"><label for="u_gp025">GP025 VENUS</label></div>
            <div class="checkbox-item"><input type="checkbox" id="u_gp026"><label for="u_gp026">GP026 POLARIS</label></div>
            <div class="checkbox-item"><input type="checkbox" id="u_gp032"><label for="u_gp032">GP032 SABLE</label></div>
            <div class="checkbox-item"><input type="checkbox" id="u_gp033"><label for="u_gp033">GP033 ARGON</label></div>
            <div class="checkbox-item"><input type="checkbox" id="u_gp037"><label for="u_gp037">GP037 NOBLE</label></div>
            <div class="checkbox-item"><input type="checkbox" id="u_partner1"><label for="u_partner1">PARTNER XT-2781-B</label></div>
            <div class="checkbox-item"><input type="checkbox" id="u_partner2"><label for="u_partner2">PARTNER XT-2782-B</label></div>
        </div>

        <div class="section-title">📋 Plan de Carga (Logística)</div>
        <div class="grid-2">
            <div class="form-group">
                <label>Programadas (#)</label>
                <input type="number" id="l_prog" value="0" min="0">
            </div>
            <div class="form-group" style="display: flex; align-items: center;">
                <div class="checkbox-item" style="width: 100%; justify-content: center; background: #fff3cd; border-color: #ffeeba;">
                    <input type="checkbox" id="l_reprog">
                    <label for="l_reprog">⚠️ ¿Hay Reprogramadas? <b>Sí</b></label>
                </div>
            </div>
        </div>

        <div class="actions">
            <button type="button" class="btn btn-save" onclick="saveData(false)">💾 Guardar Datos</button>
            <button type="button" class="btn btn-primary" onclick="shareImage()">
                <span style="font-size:1.2rem">📱</span> Compartir en WhatsApp
            </button>
        </div>
        <div class="status-bar" id="statusMsg">Sistema listo. Hora servidor: <?php echo $hora_servidor; ?></div>
    </form>

    <!-- ============================================================================
         TARJETA VISUAL EN TIEMPO REAL (Screenshot Automático)
         ============================================================================ -->
    <div class="preview-container">
        <div class="preview-title">▼ Vista Previa en Tiempo Real (Screenshot Automático) ▼</div>
        
        <div class="whatsapp-card" id="card_capture_target">
            <div class="card-header">
                <h2>🛡️ Reporte Centurión</h2>
                <p style="margin:5px 0 0 0; font-size:0.9rem;">Coordinación de Seguridad</p>
            </div>
            <div class="card-meta">
                <div>📅 <strong>Fecha:</strong> <span id="card_fecha"><?php echo $fecha_servidor; ?></span></div>
                <div>👤 <strong>Supervisor:</strong> <span id="card_sup">--</span></div>
                <div>🕒 <strong>Turno:</strong> <span id="card_turno">--</span></div>
            </div>
            <div class="card-body">
                <!-- Sección Personal -->
                <div class="card-section">
                    <div class="card-section-title"><span>👥</span> Estado de Fuerza</div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <!-- Braskem ampliado en la tarjeta -->
                        <div>
                            <strong style="color:var(--primary)">🏭 Braskem</strong>
                            <div class="card-data-row">Activos: <span class="val" id="card_b_act">0</span></div>
                            <div class="card-data-row">Descanso: <span class="val" id="card_b_desc">0</span></div>
                            <div class="card-data-row">Incapacidad: <span class="val" id="card_b_incap">0</span></div>
                            <div class="card-data-row">Comisión: <span class="val" id="card_b_com">0</span></div>
                            <div class="card-data-row">Vacaciones: <span class="val" id="card_b_vac">0</span></div>
                        </div>
                        <div>
                            <strong style="color:var(--secondary)">🏢 Silao</strong>
                            <div class="card-data-row">Activos: <span class="val" id="card_s_act">0</span></div>
                            <div class="card-data-row">Descanso: <span class="val" id="card_s_desc">0</span></div>
                            <!-- Sin Vacaciones en Tarjeta -->
                        </div>
                    </div>
                </div>
                
                <!-- Sección Unidades (RESUMEN) -->
                <div class="card-section">
                    <div class="card-section-title"><span>🚓</span> Parque Vehicular</div>
                    <div class="unit-list" id="card_units_list">
                    </div>
                </div>

                <!-- Sección Logística -->
                <div class="card-section">
                    <div class="card-section-title"><span>📋</span> Plan de Carga</div>
                    <div class="logistica-box" id="card_logistica_txt"></div>
                </div>
            </div>
            <div style="text-align:center; padding:10px; background:#f4f4f4; color:#999; font-size:0.7rem;">
                Generado por Sistema Centurión
            </div>
        </div>
    </div>

</div>

<script>
    const unitMap = {
        'u_gp023': 'GP023 JÚPITER', 'u_gp024': 'GP024 PEGASO', 'u_gp025': 'GP025 VENUS',
        'u_gp026': 'GP026 POLARIS', 'u_gp032': 'GP032 SABLE', 'u_gp033': 'GP033 ARGON',
        'u_gp037': 'GP037 NOBLE', 'u_partner1': 'PARTNER XT-2781-B', 'u_partner2': 'PARTNER XT-2782-B'
    };

    // 1. Cargar Datos Iniciales
    document.addEventListener('DOMContentLoaded', () => {
        fetch(window.location.href, { method: 'POST', body: JSON.stringify({ action: 'get_db' }) })
        .then(r => r.json()).then(data => {
            if(Object.keys(data).length > 0) {
                document.getElementById('supervisor').value = data.supervisor || "";
                document.getElementById('turno').value = data.turno || "DIURNO";
                if(data.personal) {
                    // Braskem
                    document.getElementById('b_act').value = data.personal.braskem.activos;
                    document.getElementById('b_desc').value = data.personal.braskem.descanso;
                    document.getElementById('b_incap').value = data.personal.braskem.incapacitados || 0;
                    document.getElementById('b_com').value = data.personal.braskem.comision || 0;
                    document.getElementById('b_vac').value = data.personal.braskem.vacaciones || 0;
                    
                    // Silao
                    document.getElementById('s_act').value = data.personal.silao.activos;
                    document.getElementById('s_desc').value = data.personal.silao.descanso;
                    // Sin Vacaciones
                }
                if(data.unidades) {
                    for (const [id, checked] of Object.entries(data.unidades)) { 
                        const el = document.getElementById(id); if(el) el.checked = checked; 
                    }
                }
                if(data.logistica) {
                    document.getElementById('l_prog').value = data.logistica.programadas;
                    document.getElementById('l_reprog').checked = data.logistica.reprogramadas_flag;
                }
                updateVisualCard(); // Actualizar tarjeta al cargar
            }
        });

        // LISTENERS DE REACTIVIDAD (Actualización en tiempo real)
        document.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', updateVisualCard);
            el.addEventListener('change', updateVisualCard);
        });
    });

    // 2. Función de Actualización Visual (Live Preview)
    function updateVisualCard() {
        // Mapeo directo de inputs a tarjeta
        document.getElementById('card_sup').textContent = document.getElementById('supervisor').value;
        document.getElementById('card_turno').textContent = document.getElementById('turno').value;
        
        // Braskem Info
        document.getElementById('card_b_act').textContent = document.getElementById('b_act').value;
        document.getElementById('card_b_desc').textContent = document.getElementById('b_desc').value;
        document.getElementById('card_b_incap').textContent = document.getElementById('b_incap').value;
        document.getElementById('card_b_com').textContent = document.getElementById('b_com').value;
        document.getElementById('card_b_vac').textContent = document.getElementById('b_vac').value;
        
        // Silao Info
        document.getElementById('card_s_act').textContent = document.getElementById('s_act').value;
        document.getElementById('card_s_desc').textContent = document.getElementById('s_desc').value;
        // Sin vacaciones

        // Lógica de Unidades (AHORA ES UN RESUMEN)
        let activeCount = 0;
        let inactiveCount = 0;

        for (const [id, name] of Object.entries(unitMap)) {
            const isChecked = document.getElementById(id).checked;
            if (isChecked) {
                activeCount++;
            } else {
                inactiveCount++;
            }
        }

        const unitsContainer = document.getElementById('card_units_list');
        // Renderizado del resumen ejecutivo
        unitsContainer.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #f0f0f0; background:#f9fff9;">
                <span style="font-weight:600; color:#155724;">✅ Operativas:</span> 
                <strong style="font-size:1.1rem; color:#155724;">${activeCount}</strong>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#fff5f5;">
                <span style="font-weight:600; color:#721c24;">❌ Fuera de servicio:</span> 
                <strong style="font-size:1.1rem; color:#721c24;">${inactiveCount}</strong>
            </div>
        `;

        // Lógica de Logística
        const prog = document.getElementById('l_prog').value;
        const isReprog = document.getElementById('l_reprog').checked;
        document.getElementById('card_logistica_txt').textContent = isReprog 
            ? `${prog} Unidades Programadas + Reprogramadas.` 
            : `${prog} Unidades Programadas.`;
    }

    // 3. Guardar Data (Backend)
    function saveData(silent = false) {
        let unidadesStatus = {}; let unidadesTxtArr = [];
        for (const [id, name] of Object.entries(unitMap)) {
            const isChecked = document.getElementById(id).checked;
            unidadesStatus[id] = isChecked;
            unidadesTxtArr.push(name + (isChecked ? " (ON)" : " (OFF)"));
        }

        const payloadDB = {
            supervisor: document.getElementById('supervisor').value,
            turno: document.getElementById('turno').value,
            personal: {
                braskem: { 
                    activos: document.getElementById('b_act').value, 
                    descanso: document.getElementById('b_desc').value,
                    incapacitados: document.getElementById('b_incap').value,
                    comision: document.getElementById('b_com').value,
                    vacaciones: document.getElementById('b_vac').value
                },
                silao: { 
                    activos: document.getElementById('s_act').value, 
                    descanso: document.getElementById('s_desc').value
                    // Sin Vacaciones
                }
            },
            unidades: unidadesStatus,
            logistica: { programadas: document.getElementById('l_prog').value, reprogramadas_flag: document.getElementById('l_reprog').checked }
        };

        return fetch(window.location.href, { method: 'POST', body: JSON.stringify({ action: 'save_db', db_payload: payloadDB }) })
        .then(r => r.json()).then(() => {
            if(!silent) {
                document.getElementById('statusMsg').innerText = "✅ Datos guardados. Actualizando histórico...";
                const planTxt = payloadDB.logistica.reprogramadas_flag ? `${payloadDB.logistica.programadas} (+Reprog)` : `${payloadDB.logistica.programadas}`;
                fetch(window.location.href, { method: 'POST', body: JSON.stringify({ action: 'log_history', payload: { ...payloadDB, unidades_string: unidadesTxtArr.join('|'), plan_carga_string: planTxt } }) })
                .then(()=> document.getElementById('statusMsg').innerText = "✅ Histórico actualizado.");
            }
        });
    }

    // 4. Compartir Imagen (Screenshot + Share)
    function shareImage() {
        saveData(true); // Guardar antes de compartir
        const btn = document.querySelector('.btn-primary');
        const originalText = btn.innerHTML;
        btn.innerHTML = "📸 Generando imagen...";

        const cardElement = document.getElementById('card_capture_target');
        
        html2canvas(cardElement, { scale: 2, useCORS: true }).then(canvas => {
            canvas.toBlob(blob => {
                const fileName = `Reporte_Centurion_${new Date().getTime()}.png`;
                const file = new File([blob], fileName, { type: "image/png" });

                if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                    navigator.share({
                        files: [file],
                        title: 'Reporte Centurión',
                        text: 'Adjunto reporte de turno.'
                    }).catch(console.error);
                } 
                else {
                    try {
                        const item = new ClipboardItem({ "image/png": blob });
                        navigator.clipboard.write([item]).then(() => {
                            alert("📸 ¡Imagen copiada al portapapeles! \n\nVe a WhatsApp Web y presiona Ctrl + V para pegarla.");
                        }).catch(err => { throw err; });
                    } catch (err) {
                        const link = document.createElement('a');
                        link.download = fileName;
                        link.href = canvas.toDataURL("image/png");
                        link.click();
                        alert("⬇️ Imagen descargada. Adjúntala manualmente en WhatsApp.");
                    }
                }
                btn.innerHTML = originalText;
            }, 'image/png');
        });
    }
</script>
</body>
</html>