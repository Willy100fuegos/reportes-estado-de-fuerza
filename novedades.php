<?php
// 1. Lógica del Servidor (PHP 8.x) - Coatzacoalcos V4.0
// ------------------------------------------------------------------
date_default_timezone_set('America/Mexico_City');
$fecha_servidor = date('d/m/Y');
$hora_servidor = date('H:i:s');

// Archivos de almacenamiento
$archivo_db = 'database.json';
$archivo_csv = 'historial_gorat.csv';

// A) Manejo de Peticiones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);

    // 1. Guardar Estado
    if (isset($data['action']) && $data['action'] === 'save_state') {
        file_put_contents($archivo_db, json_encode($data['payload']));
        echo json_encode(["status" => "ok"]);
        exit;
    }

    // 2. Guardar Historial (CSV)
    if (isset($data['action']) && $data['action'] === 'log_history') {
        $logData = $data['payload'];
        $existe = file_exists($archivo_csv);
        
        $fp = fopen($archivo_csv, 'a');
        
        // V4: Se agrega columna Vacaciones
        if (!$existe) {
            fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($fp, ['Fecha', 'Hora', 'Turno', 'Supervisor', 'Zona', 'Servicio', 'Presentes', 'Faltas', 'Descansos', 'Vacaciones']);
        }

        foreach ($logData['detalles'] as $fila) {
            fputcsv($fp, [
                $logData['fecha'],
                $logData['hora'],
                $logData['turno'],
                $logData['supervisor'],
                $fila['zona'],
                $fila['servicio'],
                $fila['p'],
                $fila['f'],
                $fila['d'],
                $fila['v'] // Nuevo campo
            ]);
        }
        
        fclose($fp);
        echo json_encode(["status" => "logged", "message" => "Historial actualizado"]);
        exit;
    }

    // 3. Borrar Historial
    if (isset($data['action']) && $data['action'] === 'clear_history') {
        if (file_exists($archivo_csv)) {
            unlink($archivo_csv);
        }
        echo json_encode(["status" => "cleared", "message" => "Historial eliminado"]);
        exit;
    }
}

// B) Descarga CSV
if (isset($_GET['download_csv'])) {
    if (file_exists($archivo_csv)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Historial_GORAT_'.date('Ymd').'.csv');
        readfile($archivo_csv);
        exit;
    } else {
        echo "Aún no hay historial registrado.";
        exit;
    }
}

// C) Carga Inicial
$datos_guardados = '{}';
if (file_exists($archivo_db)) {
    $contenido = file_get_contents($archivo_db);
    if (!empty($contenido) && trim($contenido) !== '') {
        $datos_guardados = $contenido;
    }
}

// ------------------------------------------------------------------
// Configuración Coatzacoalcos
// ------------------------------------------------------------------

$supervisores_list = [
    "GERARDO MAXIMILIANO JIMÉNEZ MÉNDEZ",
    "Daniel Antonio Figueroa Velázquez",
    "Joaquín Contreras"
];

$grupos = [
    'Zona Local' => ['Agencia Corona', 'Outlet Hogar', '18 de Marzo', 'Nido', 'Camper J. Rosas'],
    'Zona TQPM' => ['TQPM Custodia', 'TQPM Sitio', 'TQPM Muelle']
];

function cleanId($str) {
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Fuerza - SESCA GORAT v3.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        
        .bg-security {
            background-image: radial-gradient( circle farthest-corner at 10% 20%,  rgba(30,41,58,1) 0%, rgba(15,23,42,1) 90% );
        }
        .logo-container {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }
        @media (max-width: 380px) {
            #reporte-visual { font-size: 0.85rem; }
        }
        .no-select {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased pb-20">

    <!-- Navbar -->
    <nav class="bg-blue-900 text-white p-4 shadow-lg sticky top-0 z-50">
        <div class="flex justify-between items-center max-w-md mx-auto">
            <h1 class="font-bold text-lg">👮‍♂️ GORAT v3.0</h1>
            <div id="status-indicator" class="text-[10px] bg-blue-800 px-2 py-1 rounded text-blue-200">
                Listo
            </div>
        </div>
    </nav>

    <div class="max-w-md mx-auto p-4 space-y-6">

        <!-- 1. FORMULARIO DE CONTROL -->
        <div class="bg-white rounded-xl shadow-md p-4 border-t-4 border-blue-600">
            <h2 class="text-gray-700 font-bold mb-4 border-b pb-2 text-sm uppercase">📝 Datos del Turno</h2>
            
            <div class="grid grid-cols-1 gap-4 mb-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Supervisor</label>
                    <select id="supervisor_select" onchange="updatePreview()" class="w-full bg-blue-50 border border-blue-200 text-blue-900 text-sm font-bold rounded-lg p-2">
                        <?php foreach ($supervisores_list as $sup): ?>
                            <option value="<?= $sup ?>"><?= $sup ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Turno</label>
                    <select id="turno" onchange="updatePreview()" class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg p-2">
                        <option value="MATUTINO">☀️ Matutino</option>
                        <option value="NOCTURNO">🌙 Nocturno</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-between items-center border-b pb-2 mb-4 mt-6">
                <h2 class="text-gray-700 font-bold text-sm uppercase">👮 Fuerza Operativa</h2>
                <button onclick="resetZeros()" class="text-[10px] text-red-500 hover:text-red-700 underline">Resetear Todo</button>
            </div>

            <!-- Servicios FIJOS -->
            <?php foreach ($grupos as $zona => $servicios): ?>
                <div class="mb-5">
                    <h3 class="text-[10px] font-black text-white bg-slate-500 uppercase tracking-wider mb-2 px-2 py-0.5 rounded inline-block"><?= $zona ?></h3>
                    <?php foreach ($servicios as $servicio): $id = cleanId($servicio); ?>
                        <div class="mb-3 border-b border-gray-100 pb-2 last:border-0 hover:bg-gray-50 rounded px-1">
                            <div class="mb-1">
                                <span class="text-xs font-bold text-gray-700 leading-tight"><?= $servicio ?></span>
                            </div>
                            
                            <!-- Controles (4 Columnas) -->
                            <div class="flex space-x-1 justify-between">
                                <!-- Presentes (Verde) -->
                                <div class="flex flex-col items-center w-1/4">
                                    <span class="text-[8px] text-green-600 font-bold uppercase mb-0.5">P</span>
                                    <div class="flex items-center bg-green-50 rounded-lg p-0.5 border border-green-100 w-full justify-between no-select">
                                        <button type="button" onclick="adjust('p_<?= $id ?>', -1)" class="w-5 h-8 flex items-center justify-center text-green-700 font-bold hover:bg-green-200 rounded">-</button>
                                        <input type="number" id="p_<?= $id ?>" value="0" min="0" oninput="updatePreview()" class="w-full text-center bg-transparent text-xs font-bold text-green-800 focus:outline-none p-0" readonly>
                                        <button type="button" onclick="adjust('p_<?= $id ?>', 1)" class="w-5 h-8 flex items-center justify-center text-green-700 font-bold hover:bg-green-200 rounded">+</button>
                                    </div>
                                </div>

                                <!-- Faltas (Rojo) -->
                                <div class="flex flex-col items-center w-1/4">
                                    <span class="text-[8px] text-red-500 font-bold uppercase mb-0.5">F</span>
                                    <div class="flex items-center bg-red-50 rounded-lg p-0.5 border border-red-100 w-full justify-between no-select">
                                        <button type="button" onclick="adjust('f_<?= $id ?>', -1)" class="w-5 h-8 flex items-center justify-center text-red-400 font-bold hover:bg-red-200 rounded">-</button>
                                        <input type="number" id="f_<?= $id ?>" value="0" min="0" oninput="updatePreview()" class="w-full text-center bg-transparent text-xs font-bold text-red-600 focus:outline-none p-0" readonly>
                                        <button type="button" onclick="adjust('f_<?= $id ?>', 1)" class="w-5 h-8 flex items-center justify-center text-red-400 font-bold hover:bg-red-200 rounded">+</button>
                                    </div>
                                </div>

                                <!-- Descanso (Azul) -->
                                <div class="flex flex-col items-center w-1/4">
                                    <span class="text-[8px] text-sky-500 font-bold uppercase mb-0.5">D</span>
                                    <div class="flex items-center bg-sky-50 rounded-lg p-0.5 border border-sky-100 w-full justify-between no-select">
                                        <button type="button" onclick="adjust('d_<?= $id ?>', -1)" class="w-5 h-8 flex items-center justify-center text-sky-600 font-bold hover:bg-sky-200 rounded">-</button>
                                        <input type="number" id="d_<?= $id ?>" value="0" min="0" oninput="updatePreview()" class="w-full text-center bg-transparent text-xs font-bold text-sky-700 focus:outline-none p-0" readonly>
                                        <button type="button" onclick="adjust('d_<?= $id ?>', 1)" class="w-5 h-8 flex items-center justify-center text-sky-600 font-bold hover:bg-sky-200 rounded">+</button>
                                    </div>
                                </div>

                                <!-- Vacaciones (Morado) -->
                                <div class="flex flex-col items-center w-1/4">
                                    <span class="text-[8px] text-purple-500 font-bold uppercase mb-0.5">V</span>
                                    <div class="flex items-center bg-purple-50 rounded-lg p-0.5 border border-purple-100 w-full justify-between no-select">
                                        <button type="button" onclick="adjust('v_<?= $id ?>', -1)" class="w-5 h-8 flex items-center justify-center text-purple-600 font-bold hover:bg-purple-200 rounded">-</button>
                                        <input type="number" id="v_<?= $id ?>" value="0" min="0" oninput="updatePreview()" class="w-full text-center bg-transparent text-xs font-bold text-purple-700 focus:outline-none p-0" readonly>
                                        <button type="button" onclick="adjust('v_<?= $id ?>', 1)" class="w-5 h-8 flex items-center justify-center text-purple-600 font-bold hover:bg-purple-200 rounded">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <!-- SECCIÓN: SERVICIOS ESPECIALES -->
            <div class="mb-5 border-t-2 border-dashed border-gray-200 pt-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-[10px] font-black text-white bg-indigo-500 uppercase tracking-wider px-2 py-0.5 rounded">Servicios Especiales</h3>
                    <button onclick="addSpecialService()" class="text-[10px] bg-indigo-100 text-indigo-700 px-2 py-1 rounded font-bold border border-indigo-200 hover:bg-indigo-200 transition">
                        + Agregar
                    </button>
                </div>
                
                <div id="special-services-container" class="space-y-4">
                    <!-- Dinámicos -->
                </div>
            </div>

        </div>

        <!-- 2. VISUALIZADOR DE REPORTE -->
        <div class="mt-4">
            <p class="text-center text-[10px] text-gray-400 mb-2 uppercase tracking-widest">Vista Previa</p>
            
            <div id="reporte-visual" class="bg-security relative overflow-hidden rounded-none mx-auto text-white shadow-2xl w-full max-w-[380px]">
                
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-900 to-blue-800 p-4 border-b-4 border-yellow-500 relative">
                    <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                    
                    <div class="flex justify-between items-start relative z-10">
                        <!-- LOGO -->
                        <div class="logo-container p-2 rounded-lg flex items-center justify-center w-28 md:w-32">
                             <img src="http://imgfz.com/i/ioW1kPu.png" alt="Logo SESCA" class="w-full h-auto object-contain">
                        </div>
                        <div class="text-right pl-2">
                            <div class="text-3xl font-black text-yellow-400 leading-none drop-shadow-md" id="view-total-percent">100%</div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-blue-200 block mt-1">Cobertura</span>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-between items-end relative z-10">
                        <div>
                            <p class="text-[9px] text-blue-300 uppercase font-semibold tracking-wider">Reporte Operativo</p>
                            <h3 class="text-lg md:text-xl font-bold text-white leading-none mt-1 shadow-black drop-shadow-sm" id="view-turno">TURNO MATUTINO</h3>
                            <p class="text-[9px] text-blue-200 uppercase tracking-widest">Coatzacoalcos</p>
                        </div>
                        <p class="text-xs font-mono text-yellow-500 font-bold bg-blue-950 px-2 py-1 rounded border border-blue-800"><?= $fecha_servidor ?></p>
                    </div>
                </div>

                <!-- Cuerpo -->
                <div class="p-4 space-y-3 bg-opacity-50">
                    <?php foreach ($grupos as $zona => $servicios): ?>
                        <div class="space-y-1">
                            <h4 class="text-[8px] font-bold text-blue-200/60 uppercase tracking-[0.2em] border-b border-blue-800/50 pb-1 mb-1"><?= $zona ?></h4>
                            <?php foreach ($servicios as $servicio): $id = cleanId($servicio); ?>
                                <div class="flex justify-between items-center py-1 group border-b border-white/5 last:border-0">
                                    <span class="text-xs md:text-sm font-medium text-gray-300 group-hover:text-white transition w-1/3"><?= $servicio ?></span>
                                    <div class="flex space-x-2 text-xs md:text-sm font-mono justify-end w-2/3">
                                        <span class="text-green-400 font-bold" id="view_p_<?= $id ?>">0 P</span>
                                        <span class="text-gray-500 transition-colors" id="view_f_<?= $id ?>">0 F</span>
                                        <span class="text-gray-500 transition-colors" id="view_d_<?= $id ?>">0 D</span>
                                        <span class="text-gray-500 transition-colors" id="view_v_<?= $id ?>">0 V</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Sección Dinámica en Canvas -->
                    <div id="view-special-section" class="hidden space-y-1 mt-2">
                         <h4 class="text-[8px] font-bold text-indigo-300 uppercase tracking-[0.2em] border-b border-indigo-800/50 pb-1 mb-1">Servicios Especiales</h4>
                         <div id="view-special-container"></div>
                    </div>

                    <!-- Resumen -->
                    <div class="mt-4 bg-gradient-to-r from-blue-900/60 to-slate-900/60 rounded-lg border border-blue-700/50 p-3 flex justify-between items-center backdrop-blur-sm">
                        <div class="flex flex-col text-center w-1/4">
                            <span class="text-[7px] text-green-300 uppercase font-bold">Presentes</span>
                            <span class="text-lg font-bold text-white tracking-tight" id="view-total-presentes">0</span>
                        </div>
                        <div class="h-8 w-px bg-blue-700/50"></div>
                        <div class="flex flex-col text-center w-1/4">
                            <span class="text-[7px] text-red-300 uppercase font-bold">Faltas</span>
                            <span class="text-lg font-bold text-gray-500 tracking-tight" id="view-total-faltas">0</span>
                        </div>
                        <div class="h-8 w-px bg-blue-700/50"></div>
                        <div class="flex flex-col text-center w-1/4">
                            <span class="text-[7px] text-sky-300 uppercase font-bold">Descanso</span>
                            <span class="text-lg font-bold text-gray-500 tracking-tight" id="view-total-descansos">0</span>
                        </div>
                         <div class="h-8 w-px bg-blue-700/50"></div>
                        <div class="flex flex-col text-center w-1/4">
                            <span class="text-[7px] text-purple-300 uppercase font-bold">Vacac.</span>
                            <span class="text-lg font-bold text-gray-500 tracking-tight" id="view-total-vacaciones">0</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-black/60 p-2 text-center border-t border-gray-800 backdrop-blur-md">
                    <p class="text-[8px] text-gray-500 uppercase tracking-widest mb-1">Responsable del Turno</p>
                    <p class="text-[10px] font-bold text-gray-200" id="view-supervisor">SUPERVISOR</p>
                    
                    <!-- NUEVO FOOTER TECNOLÓGICO -->
                    <p class="text-[8px] text-gray-500 mt-2 font-mono border-t border-gray-800 pt-1 opacity-70">
                        Tecnología Goratrack - Reporteador SESCA v3.0
                    </p>
                </div>
            </div>
        </div>

        <!-- 3. PANEL DE ACCIONES -->
        <div class="bg-blue-50 rounded-xl p-4 border border-blue-200 mt-6">
            <h3 class="text-xs font-bold text-blue-800 uppercase mb-2">📊 Acciones de Registro</h3>
            <p class="text-[10px] text-gray-600 mb-4">
                Una vez generada la imagen, guarda los datos aquí para las estadísticas mensuales.
            </p>
            
            <button onclick="shareOnWhatsApp()" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg shadow-lg flex items-center justify-center gap-2 mb-3 transition transform active:scale-95">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                <span>Compartir en WhatsApp</span>
            </button>
            
            <button onclick="logHistory()" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold py-3 rounded-lg shadow-lg flex items-center justify-center gap-2 mb-3">
                <span>💾</span> Guardar en Historial Diario
            </button>

            <a href="?download_csv=true" class="block w-full text-center text-xs text-blue-600 underline font-semibold py-2 hover:text-blue-800 mb-4">
                📥 Descargar Archivo Excel (CSV)
            </a>

            <button onclick="clearHistory()" class="w-full border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 font-bold py-2 rounded-lg text-xs flex items-center justify-center gap-2">
                <span>🗑️</span> Resetear Historial Completo
            </button>
        </div>

    </div>

    <script>
        const serverData = <?= $datos_guardados ?>;
        const estructuraServicios = <?php echo json_encode($grupos); ?>;
        
        let specialServices = [];
        let debounceTimer;

        document.addEventListener('DOMContentLoaded', () => {
            loadServerData();
            updatePreview();
        });

        // 1. MANEJO DE SERVICIOS ESPECIALES
        function addSpecialService(existingData = null) {
            const id = existingData ? existingData.id : 'sp_' + Date.now();
            
            if(!existingData) {
                // V4: Se incluye 'v'
                const newData = { id: id, name: '', p: 0, f: 0, d: 0, v: 0 };
                specialServices.push(newData);
            }

            const container = document.getElementById('special-services-container');
            const row = document.createElement('div');
            row.id = `row_${id}`;
            row.className = "bg-gray-50 rounded-lg p-2 border border-indigo-100 shadow-sm transition-all";
            
            const valName = existingData ? existingData.name : '';
            const valP = existingData ? existingData.p : 0;
            const valF = existingData ? existingData.f : 0;
            const valD = existingData ? existingData.d : 0;
            const valV = existingData ? existingData.v : 0;

            row.innerHTML = `
                <div class="flex justify-between items-center mb-2">
                    <input type="text" id="name_${id}" value="${valName}" placeholder="Nombre del Servicio..." 
                        oninput="updateSpecialData('${id}')"
                        class="w-full bg-white border border-gray-300 text-gray-700 text-xs font-bold rounded p-1.5 focus:border-indigo-500 focus:outline-none placeholder-gray-400">
                    <button onclick="removeSpecialService('${id}')" class="ml-2 text-red-400 hover:text-red-600 p-1">
                        ✕
                    </button>
                </div>
                <div class="flex space-x-1 justify-between">
                    <!-- Presentes -->
                    <div class="flex items-center bg-green-50 rounded p-0.5 w-1/4 justify-between border border-green-100">
                        <button onclick="adjustSpecial('${id}', 'p', -1)" class="w-4 text-green-700 font-bold hover:bg-green-200 rounded">-</button>
                        <span id="disp_p_${id}" class="text-xs font-bold text-green-800">${valP}</span>
                        <button onclick="adjustSpecial('${id}', 'p', 1)" class="w-4 text-green-700 font-bold hover:bg-green-200 rounded">+</button>
                    </div>
                    <!-- Faltas -->
                    <div class="flex items-center bg-red-50 rounded p-0.5 w-1/4 justify-between border border-red-100">
                        <button onclick="adjustSpecial('${id}', 'f', -1)" class="w-4 text-red-500 font-bold hover:bg-red-200 rounded">-</button>
                        <span id="disp_f_${id}" class="text-xs font-bold text-red-600">${valF}</span>
                        <button onclick="adjustSpecial('${id}', 'f', 1)" class="w-4 text-red-500 font-bold hover:bg-red-200 rounded">+</button>
                    </div>
                    <!-- Descansos -->
                    <div class="flex items-center bg-sky-50 rounded p-0.5 w-1/4 justify-between border border-sky-100">
                        <button onclick="adjustSpecial('${id}', 'd', -1)" class="w-4 text-sky-600 font-bold hover:bg-sky-200 rounded">-</button>
                        <span id="disp_d_${id}" class="text-xs font-bold text-sky-700">${valD}</span>
                        <button onclick="adjustSpecial('${id}', 'd', 1)" class="w-4 text-sky-600 font-bold hover:bg-sky-200 rounded">+</button>
                    </div>
                    <!-- Vacaciones (Nuevo) -->
                    <div class="flex items-center bg-purple-50 rounded p-0.5 w-1/4 justify-between border border-purple-100">
                        <button onclick="adjustSpecial('${id}', 'v', -1)" class="w-4 text-purple-600 font-bold hover:bg-purple-200 rounded">-</button>
                        <span id="disp_v_${id}" class="text-xs font-bold text-purple-700">${valV}</span>
                        <button onclick="adjustSpecial('${id}', 'v', 1)" class="w-4 text-purple-600 font-bold hover:bg-purple-200 rounded">+</button>
                    </div>
                </div>
            `;
            container.appendChild(row);
            
            if(!existingData) updatePreview();
        }

        function removeSpecialService(id) {
            if(!confirm("¿Eliminar este servicio especial?")) return;
            specialServices = specialServices.filter(s => s.id !== id);
            const row = document.getElementById(`row_${id}`);
            if(row) row.remove();
            updatePreview();
        }

        function adjustSpecial(id, type, val) {
            const service = specialServices.find(s => s.id === id);
            if(service) {
                let current = service[type] || 0;
                let result = current + val;
                if(result < 0) result = 0;
                service[type] = result;
                document.getElementById(`disp_${type}_${id}`).innerText = result;
                updatePreview();
            }
        }

        function updateSpecialData(id) {
            const service = specialServices.find(s => s.id === id);
            if(service) {
                const nameInput = document.getElementById(`name_${id}`);
                service.name = nameInput.value;
                updatePreview();
            }
        }

        // 2. LOGICA ESTÁNDAR
        function adjust(id, val) {
            const input = document.getElementById(id);
            let current = parseInt(input.value) || 0;
            let result = current + val;
            if (result < 0) result = 0;
            input.value = result;
            updatePreview();
        }

        function updatePreview() {
            let totalPresentes = 0;
            let totalFaltas = 0;
            let totalDescansos = 0;
            let totalVacaciones = 0;

            const turno = document.getElementById('turno').value;
            const supervisor = document.getElementById('supervisor_select').value;

            document.getElementById('view-turno').innerText = 'TURNO ' + turno;
            document.getElementById('view-supervisor').innerText = supervisor.toUpperCase();

            // A) Sumar Servicios FIJOS
            const inputsP = document.querySelectorAll('input[id^="p_"]');
            inputsP.forEach(input => {
                const idBase = input.id.replace('p_', '');
                
                const pVal = parseInt(document.getElementById('p_' + idBase).value) || 0;
                const fVal = parseInt(document.getElementById('f_' + idBase).value) || 0;
                const dVal = parseInt(document.getElementById('d_' + idBase).value) || 0;
                const vVal = parseInt(document.getElementById('v_' + idBase).value) || 0;

                totalPresentes += pVal;
                totalFaltas += fVal;
                totalDescansos += dVal;
                totalVacaciones += vVal;

                document.getElementById('view_p_' + idBase).innerText = pVal + ' P';
                
                const fEl = document.getElementById('view_f_' + idBase);
                fEl.innerText = fVal + ' F';
                fEl.className = fVal > 0 ? 'text-red-500 font-bold' : 'text-gray-500';

                const dEl = document.getElementById('view_d_' + idBase);
                dEl.innerText = dVal + ' D';
                dEl.className = dVal > 0 ? 'text-sky-400 font-bold' : 'text-gray-500';

                const vEl = document.getElementById('view_v_' + idBase);
                vEl.innerText = vVal + ' V';
                vEl.className = vVal > 0 ? 'text-purple-400 font-bold' : 'text-gray-500';
            });

            // B) Sumar Servicios ESPECIALES
            const specialContainer = document.getElementById('view-special-container');
            const specialSection = document.getElementById('view-special-section');
            specialContainer.innerHTML = '';

            if(specialServices.length > 0) {
                specialSection.classList.remove('hidden');
                
                specialServices.forEach(s => {
                    totalPresentes += s.p;
                    totalFaltas += s.f;
                    totalDescansos += s.d;
                    totalVacaciones += (s.v || 0); // Manejo seguro si no existe

                    const row = document.createElement('div');
                    row.className = "flex justify-between items-center py-1 group border-b border-indigo-500/20 last:border-0";
                    
                    const displayName = s.name.trim() === '' ? 'Servicio Especial' : s.name;
                    
                    row.innerHTML = `
                        <span class="text-xs md:text-sm font-bold text-indigo-200 flex-1 italic pr-2 leading-tight break-words">${displayName}</span>
                        <div class="flex space-x-1 text-xs md:text-sm font-mono justify-end w-auto shrink-0">
                            <span class="text-green-400 font-bold whitespace-nowrap">${s.p}P</span>
                            <span class="${s.f > 0 ? 'text-red-500 font-bold' : 'text-gray-500'} whitespace-nowrap">${s.f}F</span>
                            <span class="${s.d > 0 ? 'text-sky-400 font-bold' : 'text-gray-500'} whitespace-nowrap">${s.d}D</span>
                             <span class="${(s.v || 0) > 0 ? 'text-purple-400 font-bold' : 'text-gray-500'} whitespace-nowrap">${s.v || 0}V</span>
                        </div>
                    `;
                    specialContainer.appendChild(row);
                });

            } else {
                specialSection.classList.add('hidden');
            }


            // C) Totales
            document.getElementById('view-total-presentes').innerText = totalPresentes;
            
            const tfEl = document.getElementById('view-total-faltas');
            tfEl.innerText = totalFaltas;
            tfEl.className = totalFaltas > 0 ? 'text-lg font-bold text-red-500' : 'text-lg font-bold text-gray-500';

            const tdEl = document.getElementById('view-total-descansos');
            tdEl.innerText = totalDescansos;
            tdEl.className = totalDescansos > 0 ? 'text-lg font-bold text-sky-400' : 'text-lg font-bold text-gray-500';

            const tvEl = document.getElementById('view-total-vacaciones');
            tvEl.innerText = totalVacaciones;
            tvEl.className = totalVacaciones > 0 ? 'text-lg font-bold text-purple-400' : 'text-lg font-bold text-gray-500';

            // Porcentaje
            const totalPlantilla = totalPresentes + totalFaltas;
            let porcentaje = totalPlantilla > 0 ? Math.round((totalPresentes / totalPlantilla) * 100) : 100;
            
            const percentEl = document.getElementById('view-total-percent');
            percentEl.innerText = porcentaje + '%';
            
            if(porcentaje < 90) percentEl.className = 'text-3xl font-black text-red-500 leading-none drop-shadow-md';
            else if (porcentaje < 100) percentEl.className = 'text-3xl font-black text-yellow-400 leading-none drop-shadow-md';
            else percentEl.className = 'text-3xl font-black text-green-400 leading-none drop-shadow-md';

            // Guardado automático
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(saveStateToServer, 500);
        }

        // --- PERSISTENCIA ---
        function loadServerData() {
            if(serverData && typeof serverData === 'object') {
                if(serverData.turno) document.getElementById('turno').value = serverData.turno;
                if(serverData.supervisor) document.getElementById('supervisor_select').value = serverData.supervisor;
                
                if(serverData.valores) {
                    for (const [id, val] of Object.entries(serverData.valores)) {
                        const el = document.getElementById(id);
                        if(el) el.value = val;
                    }
                }
                
                if(serverData.specialServices && Array.isArray(serverData.specialServices)) {
                    specialServices = serverData.specialServices;
                    specialServices.forEach(s => addSpecialService(s));
                }
            }
        }

        function saveStateToServer() {
            const state = {
                turno: document.getElementById('turno').value,
                supervisor: document.getElementById('supervisor_select').value,
                valores: {},
                specialServices: specialServices
            };
            
            document.querySelectorAll('input[type="number"]').forEach(input => {
                 state.valores[input.id] = input.value;
            });

            sendData({ action: 'save_state', payload: state });
        }

        function logHistory() {
            if(!confirm("¿Confirmar guardar reporte en el Historial Permanente?")) return;

            const reporte = {
                fecha: "<?= $fecha_servidor ?>",
                hora: new Date().toLocaleTimeString(),
                turno: document.getElementById('turno').value,
                supervisor: document.getElementById('supervisor_select').value,
                detalles: []
            };

            for (const [zona, servicios] of Object.entries(estructuraServicios)) {
                servicios.forEach(servicio => {
                    const id = servicio.toLowerCase().replace(/[^a-z0-9]/g, '');
                    const p = document.getElementById('p_' + id).value || 0;
                    const f = document.getElementById('f_' + id).value || 0;
                    const d = document.getElementById('d_' + id).value || 0;
                    const v = document.getElementById('v_' + id).value || 0;

                    reporte.detalles.push({
                        zona: zona,
                        servicio: servicio,
                        p: p,
                        f: f,
                        d: d,
                        v: v
                    });
                });
            }

            specialServices.forEach(s => {
                reporte.detalles.push({
                    zona: 'Servicios Especiales',
                    servicio: s.name || 'Sin Nombre',
                    p: s.p,
                    f: s.f,
                    d: s.d,
                    v: s.v || 0
                });
            });

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'log_history', payload: reporte })
            })
            .then(r => r.json())
            .then(data => {
                alert("✅ Reporte guardado en el historial correctamente.");
            })
            .catch(e => {
                alert("Error al guardar historial.");
            });
        }

        // --- COMPARTIR WHATSAPP ---
        function shareOnWhatsApp() {
            const element = document.getElementById("reporte-visual");

            const options = {
                scale: 3,
                useCORS: true, 
                backgroundColor: "#0f172a", 
                onclone: (clonedDoc) => {
                    const logo = clonedDoc.querySelector('.logo-container');
                    if(logo) {
                        logo.style.display = 'none'; 
                    }
                }
            };

            html2canvas(element, options).then(canvas => {
                canvas.toBlob(blob => {
                    const file = new File([blob], "Reporte_SESCA.png", { type: "image/png" });

                    if (navigator.share) {
                        navigator.share({
                            files: [file],
                            title: 'Reporte de Fuerza',
                            text: 'Adjunto reporte operativo actualizado.'
                        }).catch(console.error);
                    } else {
                        const link = document.createElement('a');
                        link.download = 'Reporte_SESCA.png';
                        link.href = canvas.toDataURL("image/png");
                        link.click();
                        alert("Imagen descargada (Sin logo). Arrástrala a WhatsApp Web.");
                    }
                });
            });
        }

        // --- FUNCIÓN BLINDADA CONTRA ERRORES HUMANOS ---
        function clearHistory() {
            if(!confirm("⚠️ ADVERTENCIA CRÍTICA ⚠️\n\n¿Estás seguro de que quieres BORRAR TODO el historial de estadísticas?\n\nEsta acción eliminará permanentemente el archivo Excel del servidor y NO se puede deshacer.")) return;

            const confirmacion = prompt("Para confirmar el borrado definitivo, escribe la palabra: BORRAR");
            
            if (confirmacion !== "BORRAR") {
                alert("⛔ Acción cancelada por seguridad.\nLa palabra de confirmación no coincide.");
                return;
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_history' })
            })
            .then(r => r.json())
            .then(data => {
                alert("✅ El historial ha sido borrado. Puedes iniciar de nuevo.");
            })
            .catch(e => {
                console.error(e);
                alert("Error borrando el historial.");
            });
        }

        function sendData(dataObj) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dataObj)
            }).then(r => r.json()).then(d => {
                const ind = document.getElementById('status-indicator');
                ind.innerText = "Guardado";
                setTimeout(() => ind.innerText = "Listo", 1000);
            });
        }

        function resetZeros() {
            if(confirm("¿Poner todos los contadores en cero?")) {
                document.querySelectorAll('input[type="number"]').forEach(input => input.value = 0);
                specialServices.forEach(s => { s.p = 0; s.f = 0; s.d = 0; s.v = 0; });
                
                specialServices.forEach(s => {
                    document.getElementById(`disp_p_${s.id}`).innerText = 0;
                    document.getElementById(`disp_f_${s.id}`).innerText = 0;
                    document.getElementById(`disp_d_${s.id}`).innerText = 0;
                    document.getElementById(`disp_v_${s.id}`).innerText = 0;
                });

                updatePreview();
            }
        }
    </script>
</body>
</html>