<?php
// 1. Lógica del Servidor (PHP)
// ------------------------------------------------------------------
date_default_timezone_set('America/Mexico_City');
$fecha_servidor = date('d/m/Y');
$hora_servidor = date('H:i:s');

// Archivos
$archivo_db = 'database_braskem.json';
$archivo_csv = 'historial_braskem.csv';

// --- LOGICA DE DESCARGA (NUEVO) ---
if (isset($_GET['action']) && $_GET['action'] === 'download_csv') {
    if (file_exists($archivo_csv)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Historial_Braskem_' . date('Y-m-d') . '.csv"');
        readfile($archivo_csv);
        exit;
    } else {
        echo "No hay historial para descargar aún.";
        exit;
    }
}

// A) Manejo de Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);

    if (isset($data['action']) && $data['action'] === 'save_db') {
        file_put_contents($archivo_db, json_encode($data['db_payload']));
        echo json_encode(["status" => "ok"]);
        exit;
    }

    if (isset($data['action']) && $data['action'] === 'log_history') {
        $logData = $data['payload'];
        $fp = fopen($archivo_csv, 'a');
        // Añadimos BOM para que Excel reconozca acentos automáticamente
        if (filesize($archivo_csv) == 0) {
            fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
            // Si es nuevo, ponemos encabezados
            fputcsv($fp, ["Fecha","Hora","Turno","Supervisor","Tipo Servicio","Presentes","Descansos","Vacaciones","Permisos","Incapacidad","IMSS","Onomastico","Faltas","Observaciones","Estatus Patrullas"]);
        }
        
        $csvLine = [
            date('d/m/Y'),
            date('h:i:s a'),
            $logData['turno'],
            $logData['supervisor'],
            $logData['tipo_servicio'],
            $logData['cantidades']['presentes'],
            $logData['cantidades']['descansos'],
            $logData['cantidades']['vacaciones'],
            $logData['cantidades']['permisos'],
            $logData['cantidades']['incapacidad'],
            $logData['cantidades']['imss'],
            $logData['cantidades']['onomastico'],
            $logData['cantidades']['faltas'],
            $logData['observaciones'],
            $logData['patrullas_log']
        ];
        fputcsv($fp, $csvLine);
        fclose($fp);
        echo json_encode(["status" => "logged"]);
        exit;
    }

    if (isset($data['action']) && $data['action'] === 'clear_history') {
        $headers = ["Fecha","Hora","Turno","Supervisor","Tipo Servicio","Presentes","Descansos","Vacaciones","Permisos","Incapacidad","IMSS","Onomastico","Faltas","Observaciones","Estatus Patrullas"];
        $fp = fopen($archivo_csv, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fp, $headers);
        fclose($fp);
        echo json_encode(["status" => "cleared"]);
        exit;
    }
}

$db_content = file_exists($archivo_db) ? file_get_contents($archivo_db) : '{}';
if (trim($db_content) === '') $db_content = '{}';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reporte Braskem Idesa</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

    <style>
        :root {
            --color-bg: #f0f2f5;
            --color-card: #ffffff;
            --color-text: #333333;
            --radius: 12px;
            --theme-intra-bg: #1a3c6e;
            --theme-intra-accent: #e63946;
            --theme-extra-bg: #d35400; 
            --theme-extra-accent: #2c3e50;
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--color-bg);
            margin: 0;
            padding: 10px;
            color: var(--color-text);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .control-panel {
            background: var(--color-card);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 500px;
        }
        h2 { color: #1a3c6e; margin: 0 0 15px 0; font-size: 1.2rem; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; } 
        label { display: block; font-weight: bold; font-size: 0.9rem; color: #333; margin-bottom: 5px; }
        select, textarea {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; background: #fff; -webkit-appearance: none; 
        }
        .row { display: flex; gap: 10px; }
        .col { flex: 1; }
        .counter-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px; }
        .counter-item { background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; }
        .counter-header { font-size: 0.75rem; text-align: center; margin-bottom: 5px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .stepper { display: flex; justify-content: space-between; align-items: center; }
        .btn-step { width: 32px; height: 32px; border-radius: 50%; border: none; background: #e0e0e0; font-weight: bold; font-size: 1.1rem; color: #555; display: flex; align-items: center; justify-content: center; cursor: pointer; touch-action: manipulation; }
        .stepper input { width: 35px; text-align: center; border: none; background: transparent; font-size: 1.1rem; font-weight: bold; color: #333; }
        .patrol-section { margin-top: 20px; background: #eef2f6; padding: 15px; border-radius: 8px; }
        .patrol-group { display: none; }
        .patrol-group.active { display: block; }
        .checkbox-row { display: flex; align-items: center; background: white; padding: 10px; margin-bottom: 8px; border-radius: 6px; border: 1px solid #ddd; cursor: pointer; }
        .checkbox-row input { width: 20px; height: 20px; margin-right: 12px; accent-color: #1a3c6e; }
        
        /* BOTONES */
        .actions { margin-top: 25px; display: flex; flex-direction: column; gap: 12px; }
        
        button.btn-base {
            padding: 15px; border: none; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; transition: transform 0.1s;
        }
        button.btn-base:active { transform: scale(0.98); }

        button.btn-whatsapp { background-color: #25d366; color: white; }
        button.btn-save { background-color: #1a3c6e; color: white; }
        
        /* Sección de Administración de Archivos */
        .file-admin {
            margin-top: 30px; border-top: 2px dashed #ddd; padding-top: 20px;
        }
        .file-title { font-size: 0.8rem; text-transform: uppercase; color: #888; font-weight: bold; text-align: center; margin-bottom: 10px; }
        .admin-row { display: flex; gap: 10px; }

        button.btn-download { background-color: #007bff; color: white; flex: 1; font-size: 0.9rem; padding: 12px; }
        button.btn-danger { background-color: #fff; color: #dc3545; border: 2px solid #dc3545; flex: 1; font-size: 0.9rem; padding: 12px; }
        
        .preview-wrapper { width: 100%; max-width: 500px; }
        #capture-card { background: white; border: 1px solid #e0e0e0; }
        .card-header { padding: 20px 15px; text-align: center; color: white; transition: background 0.3s; position: relative; overflow: hidden; }
        .card-header h1 { margin: 0; font-size: 1.4rem; text-transform: uppercase; letter-spacing: 1px; }
        .card-header p { margin: 4px 0 0; font-size: 0.9rem; opacity: 0.9; }
        .theme-intra .card-header { background: var(--theme-intra-bg); border-bottom: 5px solid var(--theme-intra-accent); }
        .theme-extra .card-header { background: var(--theme-extra-bg); border-bottom: 5px solid var(--theme-extra-accent); }
        .card-body { padding: 15px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table td { padding: 4px 0; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .td-label { font-weight: bold; color: #666; width: 35%; }
        .td-val { font-weight: bold; color: #333; text-align: right; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 15px; }
        .stat-box { background: #f4f6f8; border: 1px solid #e1e4e8; padding: 8px; text-align: center; border-radius: 6px; display: flex; flex-direction: column; justify-content: center; min-height: 70px; }
        .stat-number { font-size: 1.4rem; font-weight: 900; color: #333; line-height: 1; margin-bottom: 4px; }
        .stat-desc { font-size: 0.6rem; text-transform: uppercase; color: #666; font-weight: bold; letter-spacing: 0.5px; line-height: 1.1; }
        .sb-green { border-left: 4px solid #28a745; }
        .sb-gray { border-left: 4px solid #6c757d; }
        .sb-blue { border-left: 4px solid #17a2b8; }
        .sb-orange { border-left: 4px solid #fd7e14; }
        .sb-purple { border-left: 4px solid #6f42c1; } 
        .sb-teal { border-left: 4px solid #20c997; }   
        .sb-pink { border-left: 4px solid #e83e8c; }   
        .sb-red { border-left: 4px solid #dc3545; }    
        .v-list { margin-top: 10px; border: 1px solid #eee; }
        .v-header { background: #eee; font-size: 0.75rem; font-weight: bold; padding: 5px; text-align: center; color: #555; }
        .v-item { display: flex; justify-content: space-between; padding: 6px 10px; border-bottom: 1px solid #f9f9f9; font-size: 0.8rem; }
        .st-ok { color: #28a745; font-weight: 800; }
        .st-bad { color: #dc3545; font-weight: 800; }
        .obs-container { margin-top: 12px; background: #fff8e1; padding: 10px; border: 1px solid #ffeeba; font-size: 0.8rem; color: #856404; display: none; }
        .card-footer { background: #f8f9fa; text-align: center; padding: 8px; font-size: 0.65rem; color: #999; border-top: 1px solid #eee; }
        #toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 10px 20px; border-radius: 20px; font-size: 0.9rem; opacity: 0; transition: opacity 0.3s; pointer-events: none; z-index: 100; }
    </style>
</head>
<body>

    <div id="toast">Guardado</div>

    <div class="control-panel">
        <h2>🛠️ Datos de Turno</h2>
        
        <!-- 1. SERVICIO -->
        <div class="form-group">
            <label>1. Servicio:</label>
            <select id="tipoServicio" onchange="handleServiceChange()">
                <option value="Intramuros">Intramuros</option>
                <option value="Extramuros">Extramuros</option>
            </select>
        </div>

        <!-- 2. SUPERVISOR -->
        <div class="form-group">
            <label>2. Supervisor:</label>
            <select id="supervisor" onchange="changeSupervisor()">
                <!-- JS -->
            </select>
        </div>

        <!-- 3. TURNO -->
        <div class="form-group">
            <label>3. Turno:</label>
            <select id="turno" onchange="updatePreview()">
                <option value="DIURNO">DIURNO</option>
                <option value="NOCTURNO">NOCTURNO</option>
            </select>
        </div>

        <div class="counter-grid">
            <div class="counter-item"><div class="counter-header">✅ Presentes</div><div class="stepper"><button class="btn-step" onclick="adj('c_presentes', -1)">-</button><input type="number" id="c_presentes" value="0" readonly><button class="btn-step" onclick="adj('c_presentes', 1)">+</button></div></div>
            <div class="counter-item"><div class="counter-header">💤 Descansos</div><div class="stepper"><button class="btn-step" onclick="adj('c_descansos', -1)">-</button><input type="number" id="c_descansos" value="0" readonly><button class="btn-step" onclick="adj('c_descansos', 1)">+</button></div></div>
            <div class="counter-item"><div class="counter-header">🏖️ Vacaciones</div><div class="stepper"><button class="btn-step" onclick="adj('c_vacaciones', -1)">-</button><input type="number" id="c_vacaciones" value="0" readonly><button class="btn-step" onclick="adj('c_vacaciones', 1)">+</button></div></div>
            <div class="counter-item"><div class="counter-header">📄 Permisos</div><div class="stepper"><button class="btn-step" onclick="adj('c_permisos', -1)">-</button><input type="number" id="c_permisos" value="0" readonly><button class="btn-step" onclick="adj('c_permisos', 1)">+</button></div></div>
            <div class="counter-item"><div class="counter-header">🏥 Incapacidad</div><div class="stepper"><button class="btn-step" onclick="adj('c_incapacidad', -1)">-</button><input type="number" id="c_incapacidad" value="0" readonly><button class="btn-step" onclick="adj('c_incapacidad', 1)">+</button></div></div>
            <div class="counter-item"><div class="counter-header">💊 Asist. IMSS</div><div class="stepper"><button class="btn-step" onclick="adj('c_imss', -1)">-</button><input type="number" id="c_imss" value="0" readonly><button class="btn-step" onclick="adj('c_imss', 1)">+</button></div></div>
            <div class="counter-item"><div class="counter-header">🎂 Onomástico</div><div class="stepper"><button class="btn-step" onclick="adj('c_onomastico', -1)">-</button><input type="number" id="c_onomastico" value="0" readonly><button class="btn-step" onclick="adj('c_onomastico', 1)">+</button></div></div>
            <div class="counter-item"><div class="counter-header">❌ Faltas</div><div class="stepper"><button class="btn-step" onclick="adj('c_faltas', -1)">-</button><input type="number" id="c_faltas" value="0" readonly><button class="btn-step" onclick="adj('c_faltas', 1)">+</button></div></div>
        </div>

        <div class="patrol-section">
            <label>🚓 Unidades Operativas</label>
            <div id="group-intramuros" class="patrol-group"><label class="checkbox-row"><input type="checkbox" id="chk_gp018" onchange="updatePreview()"><span>GP018 - HILUX BLANCA</span></label><label class="checkbox-row"><input type="checkbox" id="chk_gp041" onchange="updatePreview()"><span>GP041 - HILUX NEGRA</span></label></div>
            <div id="group-extramuros" class="patrol-group"><label class="checkbox-row"><input type="checkbox" id="chk_gp027" onchange="updatePreview()"><span>GP027 - MITSUBISHI L200</span></label><label class="checkbox-row"><input type="checkbox" id="chk_gp038" onchange="updatePreview()"><span>GP038 - NISSAN NP300</span></label></div>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label>Observaciones:</label>
            <textarea id="observaciones" rows="2" placeholder="Sin novedad..." oninput="updatePreview()"></textarea>
        </div>

        <!-- ACCIONES PRINCIPALES -->
        <div class="actions">
            <button class="btn-base btn-whatsapp" onclick="shareCard()">📤 Compartir Tarjeta (WhatsApp)</button>
            <button class="btn-base btn-save" onclick="saveToHistory()">💾 Guardar Reporte</button>
        </div>

        <!-- ZONA ADMINISTRATIVA -->
        <div class="file-admin">
            <div class="file-title">Administración de Archivos</div>
            <div class="admin-row">
                <button class="btn-base btn-download" onclick="downloadCSV()">📥 Descargar Historial</button>
                <button class="btn-base btn-danger" onclick="clearHistory()">⚠️ Resetear CSV</button>
            </div>
        </div>
    </div>

    <!-- PREVIEW CARD (LITE) -->
    <div class="preview-wrapper">
        <h3 style="text-align:center; color:#999; font-size: 0.9rem; margin-bottom:5px;">VISTA PREVIA DE TARJETA</h3>
        <div id="capture-card" class="theme-intra"> 
            <div class="card-header">
                <h1>Reporte de Fuerza</h1>
                <p>Braskem Idesa - Seguridad Patrimonial</p>
            </div>
            <div class="card-body">
                <table class="info-table">
                    <tr><td class="td-label">Fecha:</td><td class="td-val"><?php echo $fecha_servidor; ?></td></tr>
                    <tr><td class="td-label">Supervisor:</td><td class="td-val" id="prev-supervisor">--</td></tr>
                    <tr><td class="td-label">Turno:</td><td class="td-val" id="prev-turno">--</td></tr>
                    <tr><td class="td-label">Servicio:</td><td class="td-val" id="prev-servicio" style="text-transform:uppercase;">--</td></tr>
                </table>
                <div class="stats-grid">
                    <div class="stat-box sb-green"><span class="stat-number" id="prev-presentes">0</span><span class="stat-desc">Presentes</span></div>
                    <div class="stat-box sb-gray"><span class="stat-number" id="prev-descansos">0</span><span class="stat-desc">Descanso</span></div>
                    <div class="stat-box sb-blue"><span class="stat-number" id="prev-vacaciones">0</span><span class="stat-desc">Vacaciones</span></div>
                    <div class="stat-box sb-orange"><span class="stat-number" id="prev-permisos">0</span><span class="stat-desc">Permisos</span></div>
                    <div class="stat-box sb-purple"><span class="stat-number" id="prev-incapacidad">0</span><span class="stat-desc">Incapacidad</span></div>
                    <div class="stat-box sb-teal"><span class="stat-number" id="prev-imss">0</span><span class="stat-desc">IMSS</span></div>
                    <div class="stat-box sb-pink"><span class="stat-number" id="prev-onomastico">0</span><span class="stat-desc">Onomástico</span></div>
                    <div class="stat-box sb-red"><span class="stat-number" id="prev-faltas">0</span><span class="stat-desc">Faltas</span></div>
                </div>
                <div class="v-list"><div class="v-header">ESTADO DE FLOTILLA VEHICULAR</div><div id="prev-vehicle-list"></div></div>
                <div class="obs-container" id="prev-obs-box"><strong>Notas:</strong> <span id="prev-observaciones"></span></div>
            </div>
            <div class="card-footer">Generado: <span id="prev-timestamp"></span></div>
        </div>
    </div>

    <script>
        // --- LOGICA DEL FRONTEND ---
        let db = <?php echo $db_content; ?>;
        let currentSup = "";
        let currentServ = "";
        const counters = ['c_presentes', 'c_descansos', 'c_vacaciones', 'c_permisos', 'c_incapacidad', 'c_imss', 'c_onomastico', 'c_faltas'];
        const allPatrols = ['chk_gp018', 'chk_gp041', 'chk_gp027', 'chk_gp038'];

        const supervisorsList = {
            'Intramuros': ['Uriel Francisco Garfias Vela', 'Olegario reyes arnabar', 'Miguel Ángel Bartolo Torres'],
            'Extramuros': ['Lázaro de Jesús Pérez Alaniz', 'José Alejandro García Benítez', 'Alexis Yair Arguelles Vasconcelos', 'Santiago Morales Castillo']
        };

        document.addEventListener('DOMContentLoaded', () => {
            populateSupervisors(); 
            currentServ = document.getElementById('tipoServicio').value;
            currentSup = document.getElementById('supervisor').value;
            loadDataFor(currentSup, currentServ);
            updatePreview();
        });

        function handleServiceChange() {
            saveCurrentStateToMemory(); 
            currentServ = document.getElementById('tipoServicio').value;
            populateSupervisors();
            currentSup = document.getElementById('supervisor').value;
            loadDataFor(currentSup, currentServ);
            updatePreview();
        }

        function populateSupervisors() {
            const service = document.getElementById('tipoServicio').value;
            const select = document.getElementById('supervisor');
            select.innerHTML = '';
            const list = supervisorsList[service] || [];
            list.forEach(name => {
                const opt = document.createElement('option');
                opt.value = name; opt.innerText = name;
                select.appendChild(opt);
            });
        }

        async function shareCard() {
            const toast = document.getElementById('toast');
            toast.innerText = "📸 Generando...";
            toast.style.opacity = 1;
            const element = document.getElementById('capture-card');
            
            try {
                const canvas = await html2canvas(element, { scale: 2, backgroundColor: "#ffffff", logging: false });
                canvas.toBlob(async (blob) => {
                    if (!blob) return;
                    const file = new File([blob], "reporte_braskem.png", { type: "image/png" });
                    if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                        try { await navigator.share({ files: [file], title: 'Reporte Braskem' }); toast.innerText = "✅ Compartido"; } catch (err) {}
                    } else {
                        const link = document.createElement('a'); link.download = 'reporte_braskem.png'; link.href = canvas.toDataURL(); link.click(); alert("📲 Imagen descargada.");
                    }
                    setTimeout(() => toast.style.opacity = 0, 2000);
                }, 'image/png');
            } catch (err) { alert("Error generando captura."); toast.style.opacity = 0; }
        }

        // DESCARGAR CSV
        function downloadCSV() {
            window.location.href = window.location.pathname + "?action=download_csv";
        }

        // BORRADO SEGURO
        function clearHistory() {
            // Confirmación 1
            if(!confirm("⚠️ ZONA DE PELIGRO ⚠️\n\n¿Seguro que quieres BORRAR TODO el historial?\nEsta acción eliminará todos los registros CSV.")) return;
            
            // Confirmación 2 (Contraseña)
            const pass = prompt("Para confirmar el borrado, escribe la palabra: BORRAR");
            
            if(pass === "BORRAR") {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_history' })
                }).then(r => r.json()).then(() => alert("✅ Historial reiniciado correctamente."));
            } else {
                alert("⛔ Cancelado. La palabra clave no coincide.");
            }
        }

        function changeSupervisor() {
            saveCurrentStateToMemory();
            currentSup = document.getElementById('supervisor').value;
            loadDataFor(currentSup, currentServ);
            updatePreview();
        }
        function loadDataFor(sup, serv) {
            if (db[sup] && db[sup][serv]) {
                const data = db[sup][serv];
                document.getElementById('turno').value = data.turno || 'DIURNO';
                document.getElementById('observaciones').value = data.observaciones || '';
                counters.forEach(id => document.getElementById(id).value = data.contadores[id] || 0);
                allPatrols.forEach(id => document.getElementById(id).checked = data.patrullas[id] || false);
            } else { resetForm(); }
        }
        function saveCurrentStateToMemory() {
            if (!db[currentSup]) db[currentSup] = {};
            const state = {
                turno: document.getElementById('turno').value,
                observaciones: document.getElementById('observaciones').value,
                contadores: {},
                patrullas: {}
            };
            counters.forEach(id => state.contadores[id] = document.getElementById(id).value);
            allPatrols.forEach(id => state.patrullas[id] = document.getElementById(id).checked);
            db[currentSup][currentServ] = state;
            persistDb();
        }
        function persistDb() {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_db', db_payload: db })
            }).then(() => { const t = document.getElementById('toast'); t.innerText = "Guardado"; t.style.opacity = 1; setTimeout(() => t.style.opacity = 0, 1500); });
        }
        function resetForm() {
            counters.forEach(id => document.getElementById(id).value = 0);
            document.getElementById('observaciones').value = '';
            allPatrols.forEach(id => document.getElementById(id).checked = false);
        }
        function adj(id, val) {
            const el = document.getElementById(id); let v = parseInt(el.value) || 0; v += val; if(v < 0) v = 0; el.value = v; updatePreview(); clearTimeout(window.saveTimer); window.saveTimer = setTimeout(() => saveCurrentStateToMemory(), 800);
        }
        function updatePreview() {
            const sType = document.getElementById('tipoServicio').value;
            document.getElementById('prev-supervisor').innerText = document.getElementById('supervisor').value;
            document.getElementById('prev-turno').innerText = document.getElementById('turno').value;
            document.getElementById('prev-servicio').innerText = sType;
            const card = document.getElementById('capture-card');
            if (sType === 'Intramuros') {
                card.classList.remove('theme-extra'); card.classList.add('theme-intra');
                document.getElementById('group-intramuros').classList.add('active');
                document.getElementById('group-extramuros').classList.remove('active');
            } else {
                card.classList.remove('theme-intra'); card.classList.add('theme-extra');
                document.getElementById('group-intramuros').classList.remove('active');
                document.getElementById('group-extramuros').classList.add('active');
            }
            counters.forEach(c => { const prevId = 'prev-' + c.substring(2); const el = document.getElementById(prevId); if(el) el.innerText = document.getElementById(c).value; });
            const vList = document.getElementById('prev-vehicle-list'); vList.innerHTML = '';
            let currentPatrols = (sType === 'Intramuros') ? [{id:'chk_gp018', n:'GP018 HILUX'}, {id:'chk_gp041', n:'GP041 HILUX'}] : [{id:'chk_gp027', n:'GP027 L200'}, {id:'chk_gp038', n:'GP038 NP300'}];
            currentPatrols.forEach(p => {
                const isChk = document.getElementById(p.id).checked;
                const html = `<div class="v-item"><span>${p.n}</span><span class="${isChk ? 'st-ok':'st-bad'}">${isChk ? 'OPERATIVA':'FUERA SERVICIO'}</span></div>`;
                vList.innerHTML += html;
            });
            const obs = document.getElementById('observaciones').value; const obsBox = document.getElementById('prev-obs-box');
            if(obs.trim()) { obsBox.style.display = 'block'; document.getElementById('prev-observaciones').innerText = obs; } else { obsBox.style.display = 'none'; }
            const now = new Date(); document.getElementById('prev-timestamp').innerText = now.toLocaleString('es-MX');
        }
        function saveToHistory() {
            if(!confirm("¿Confirmar y guardar reporte en historial?")) return;
            const sType = document.getElementById('tipoServicio').value;
            let pLog = [];
            let activeP = (sType === 'Intramuros') ? [{id:'chk_gp018', n:'GP018'}, {id:'chk_gp041', n:'GP041'}] : [{id:'chk_gp027', n:'GP027'}, {id:'chk_gp038', n:'GP038'}];
            activeP.forEach(p => { pLog.push(`${p.n}: ${document.getElementById(p.id).checked ? 'OK' : 'FAIL'}`); });
            const payload = {
                supervisor: document.getElementById('supervisor').value,
                turno: document.getElementById('turno').value,
                tipo_servicio: sType,
                observaciones: document.getElementById('observaciones').value,
                cantidades: {
                    presentes: document.getElementById('c_presentes').value,
                    descansos: document.getElementById('c_descansos').value,
                    vacaciones: document.getElementById('c_vacaciones').value,
                    permisos: document.getElementById('c_permisos').value,
                    incapacidad: document.getElementById('c_incapacidad').value,
                    imss: document.getElementById('c_imss').value,
                    onomastico: document.getElementById('c_onomastico').value,
                    faltas: document.getElementById('c_faltas').value,
                },
                patrullas_log: pLog.join(' | ')
            };
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'log_history', payload: payload })
            }).then(r => r.json()).then(() => alert("✅ Reporte guardado en CSV."));
        }
    </script>
</body>
</html>