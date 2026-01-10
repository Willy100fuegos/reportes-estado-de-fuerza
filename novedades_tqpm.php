<?php
/**
 * SISTEMA DE REPORTE OPERATIVO - UNIDAD CAZADOR TQPM
 * Arquitectura: Monolito (PHP + JS + Tailwind)
 * Autor: Desarrollador Senior Full-Stack (IA)
 * Versión: 1.1.0 - Actualización Web Share API
 */

// --- CONFIGURACIÓN Y RUTAS ---
$dbFile = 'database_tqpm.json';
$historyFile = 'historial_tqpm.csv';

// --- BACKEND: MANEJO DE PETICIONES (API INTERNA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Acción: Guardar Estado Actual (Persistencia Volátil)
    if (isset($input['action']) && $input['action'] === 'save_state') {
        file_put_contents($dbFile, json_encode($input['data'], JSON_PRETTY_PRINT));
        echo json_encode(['status' => 'success', 'message' => 'Estado guardado']);
        exit;
    }

    // Acción: Guardar en Historial (Persistencia Permanente)
    if (isset($input['action']) && $input['action'] === 'save_history') {
        $data = $input['data'];
        
        // Crear cabeceras si el archivo no existe
        if (!file_exists($historyFile)) {
            $headers = "Fecha,Hora_Cierre,Turno,Reportado_Por,Total_Recorridos,Total_Eventos_Custodia,Total_Vehiculos,Log_Resumen\n";
            file_put_contents($historyFile, $headers);
        }

        // Preparar línea CSV
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');
        $logResumen = str_replace([",", "\n"], [";", " | "], $input['log_summary']); // Sanitizar CSV
        
        // Supervisor/Reportado Por ahora es fijo "Unidad Cazador"
        $reportadoPor = "Unidad Cazador";

        $line = sprintf(
            "%s,%s,%s,%s,%d,%d,%d,%s\n",
            $fecha,
            $hora,
            $data['turno'],
            $reportadoPor,
            $data['recorridos'],
            count($data['eventos']),
            $input['total_vehiculos'],
            $logResumen
        );

        file_put_contents($historyFile, $line, FILE_APPEND);
        
        // Limpiar estado actual tras guardar historial
        $data['eventos'] = [];
        $data['recorridos'] = 0;
        file_put_contents($dbFile, json_encode($data, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'success', 'message' => 'Historial archivado']);
        exit;
    }
    exit;
}

// --- CARGAR ESTADO INICIAL ---
$initialState = file_exists($dbFile) ? file_get_contents($dbFile) : json_encode([
    'turno' => 'Diurno',
    'recorridos' => 0,
    'eventos' => []
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reporte Cazador TQPM</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- HTML2Canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'tactical-dark': '#0f172a',
                        'tactical-blue': '#1e3a8a',
                        'tactical-accent': '#3b82f6',
                    }
                }
            }
        }
    </script>
    <style>
        /* Estilos específicos para impresión/captura */
        .capture-mode {
            background-color: #0f172a !important;
            color: white !important;
            width: 800px !important; /* Ancho fijo para la imagen */
            padding: 2rem !important;
        }
        /* Checkbox personalizado grande para móvil */
        .vehicle-check:checked + div {
            background-color: #1e40af;
            border-color: #60a5fa;
            color: white;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-200 font-sans min-h-screen pb-20">

    <!-- HEADER APP -->
    <header class="bg-slate-800 border-b border-slate-700 p-4 sticky top-0 z-50 shadow-lg">
        <div class="flex justify-between items-center max-w-4xl mx-auto">
            <h1 class="text-xl font-bold text-blue-400 tracking-wider">
                <i class="fa-solid fa-shield-halved mr-2"></i>CAZADOR TQPM
            </h1>
            <div id="status-indicator" class="text-xs text-green-500 font-mono">
                <i class="fa-solid fa-circle text-[8px] animate-pulse"></i> ONLINE
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto p-4 space-y-6">

        <!-- SECCIÓN 1: DATOS OPERATIVOS -->
        <section class="bg-slate-800 rounded-lg p-4 shadow-lg border border-slate-700">
            <h2 class="text-sm uppercase tracking-widest text-slate-400 mb-4 font-bold border-b border-slate-700 pb-2">
                1. Configuración de Turno
            </h2>
            <div class="grid grid-cols-1 gap-4">
                <!-- Se eliminó el selector de Supervisor -->
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Seleccionar Turno</label>
                    <select id="input-turno" class="w-full bg-slate-900 border border-slate-600 rounded p-3 text-white focus:border-blue-500 outline-none">
                        <option value="Diurno">☀️ Diurno (12x12)</option>
                        <option value="Nocturno">🌙 Nocturno (12x12)</option>
                    </select>
                </div>
            </div>

            <!-- CONTADOR DE RECORRIDOS -->
            <div class="mt-6 bg-slate-900 rounded p-4 border border-blue-900/50">
                <label class="block text-xs text-blue-400 mb-2 font-bold uppercase">Recorridos Disuasivos</label>
                <div class="flex items-center justify-between">
                    <button onclick="updateRecorridos(-1)" class="w-12 h-12 rounded bg-red-900/50 text-red-400 text-xl font-bold hover:bg-red-800"><i class="fa-solid fa-minus"></i></button>
                    <span id="display-recorridos" class="text-3xl font-mono font-bold text-white">0</span>
                    <button onclick="updateRecorridos(1)" class="w-12 h-12 rounded bg-green-900/50 text-green-400 text-xl font-bold hover:bg-green-800"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
        </section>

        <!-- SECCIÓN 2: CONSTRUCTOR DE CUSTODIAS -->
        <section class="bg-slate-800 rounded-lg p-4 shadow-lg border border-slate-700">
            <h2 class="text-sm uppercase tracking-widest text-slate-400 mb-4 font-bold border-b border-slate-700 pb-2">
                2. Registrar Custodia
            </h2>
            
            <div class="space-y-4">
                <!-- Hora y Ruta -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Hora</label>
                        <input type="time" id="custodia-hora" class="w-full bg-slate-900 border border-slate-600 rounded p-3 text-white text-center font-mono">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Ruta</label>
                        <select id="custodia-ruta" class="w-full bg-slate-900 border border-slate-600 rounded p-3 text-white text-xs">
                            <option value="Allende → Sitio">Allende ➡ Sitio</option>
                            <option value="Sitio → Allende">Sitio ➡ Allende</option>
                            <option value="Sitio → Nanchital">Sitio ➡ Nanchital</option>
                            <option value="Nanchital → Sitio">Nanchital ➡ Sitio</option>
                            <option value="Sitio → Braskem">Sitio ➡ Braskem</option>
                            <option value="Braskem → Sitio">Braskem ➡ Sitio</option>
                        </select>
                    </div>
                </div>

                <!-- Selección de Vehículos -->
                <div>
                    <label class="block text-xs text-slate-400 mb-2">Unidades Custodiadas</label>
                    <div class="grid grid-cols-2 gap-2" id="vehicle-grid">
                        <!-- Generado por JS -->
                    </div>
                    <!-- Campo "Otra" -->
                    <div class="mt-2">
                        <input type="text" id="custodia-otra" placeholder="Otra unidad (Placa/ID)" class="w-full bg-slate-900 border border-slate-600 rounded p-2 text-sm text-white focus:border-blue-500">
                    </div>
                </div>

                <button onclick="addEvent()" class="w-full bg-blue-700 hover:bg-blue-600 text-white font-bold py-3 rounded shadow-lg transform active:scale-95 transition-all">
                    <i class="fa-solid fa-plus-circle mr-2"></i> AGREGAR EVENTO
                </button>
            </div>

            <!-- Lista de eventos (Edición) -->
            <div class="mt-6">
                <h3 class="text-xs text-slate-500 mb-2 uppercase">Eventos Registrados (Toque para borrar)</h3>
                <div id="events-list-edit" class="space-y-2">
                    <!-- JS rellena esto -->
                </div>
            </div>
        </section>

        <!-- SECCIÓN 3: PREVIEW TARJETA TÁCTICA -->
        <section class="bg-slate-800 rounded-lg p-4 shadow-lg border border-slate-700">
            <h2 class="text-sm uppercase tracking-widest text-slate-400 mb-4 font-bold border-b border-slate-700 pb-2">
                3. Vista Previa (Reporte)
            </h2>
            
            <div class="overflow-x-auto pb-4">
                <!-- CONTENEDOR DE CAPTURA -->
                <div id="capture-target" class="bg-gradient-to-b from-slate-900 to-slate-800 p-6 mx-auto border-t-4 border-blue-600 shadow-2xl relative" style="width: 100%; min-width: 350px; max-width: 600px;">
                    
                    <!-- Tarjeta Header -->
                    <div class="flex justify-between items-start mb-6 border-b border-slate-700 pb-4">
                        <div>
                            <h2 class="text-2xl font-black text-white tracking-tighter italic">REPORTE OPERATIVO</h2>
                            <p class="text-blue-400 text-xs font-bold tracking-widest">UNIDAD CAZADOR TQPM</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-slate-400">FECHA</div>
                            <div class="text-white font-mono font-bold" id="card-date">--/--/----</div>
                            <div class="text-xs text-slate-400 mt-1">TURNO</div>
                            <div class="text-blue-300 font-bold uppercase" id="card-turno">--</div>
                        </div>
                    </div>

                    <!-- Datos Reporta -->
                    <div class="mb-4 flex items-center bg-slate-800/50 p-2 rounded border-l-2 border-blue-500">
                        <i class="fa-solid fa-user-shield text-blue-400 mr-3"></i>
                        <div>
                            <p class="text-[10px] text-slate-400 uppercase">Reportado Por</p>
                            <p class="text-sm font-bold text-white">UNIDAD CAZADOR</p>
                        </div>
                    </div>

                    <!-- Timeline de Eventos -->
                    <div class="mb-6">
                        <h3 class="text-xs font-bold text-slate-500 uppercase mb-2 border-b border-slate-700">Bitácora de Custodias</h3>
                        <div id="card-events-list" class="space-y-3 text-sm">
                            <!-- JS Rellena esto -->
                            <p class="text-slate-600 text-center text-xs italic py-4">Sin eventos registrados</p>
                        </div>
                    </div>

                    <!-- Resumen Estadístico (Footer Tarjeta) -->
                    <div class="bg-slate-950 rounded p-3 border border-slate-800">
                        <h4 class="text-[10px] text-blue-500 font-bold uppercase mb-2">Resumen Operativo</h4>
                        
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div class="text-center p-2 bg-slate-900 rounded">
                                <span class="block text-2xl font-bold text-white" id="card-total-recorridos">0</span>
                                <span class="text-[9px] text-slate-400 uppercase">Recorridos Disuasivos</span>
                            </div>
                            <div class="text-center p-2 bg-slate-900 rounded">
                                <span class="block text-2xl font-bold text-white" id="card-total-vehiculos">0</span>
                                <span class="text-[9px] text-slate-400 uppercase">Vehículos Custodiados</span>
                            </div>
                        </div>
                        
                        <!-- Tabla de desglose -->
                        <table class="w-full text-[10px] text-left text-slate-400">
                            <tbody id="card-summary-table">
                                <!-- JS rellena esto -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Branding (Personalizado Goratrack) -->
                    <div class="mt-4 pt-2 border-t border-slate-800 flex justify-between items-center opacity-70">
                        <span class="text-[8px] uppercase tracking-wide text-blue-300 font-semibold">Tecnología Goratrack - Reporteador Custodias TQPM v1.0</span>
                        <div class="flex space-x-2 text-slate-500">
                            <i class="fa-solid fa-satellite-dish"></i>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="grid grid-cols-1 gap-3 mt-4">
                <!-- Botón WhatsApp -->
                <button onclick="shareOnWhatsapp()" class="bg-green-600 hover:bg-green-500 text-white font-bold py-4 rounded shadow-lg flex justify-center items-center transition-all transform active:scale-95">
                    <i class="fa-brands fa-whatsapp text-2xl mr-3"></i> COMPARTIR POR WHATSAPP
                </button>
                
                <button onclick="saveHistory()" id="btn-history" class="bg-slate-700 hover:bg-slate-600 text-white font-bold py-3 rounded shadow flex justify-center items-center border border-slate-600">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> CERRAR TURNO Y GUARDAR
                </button>
            </div>
        </section>

    </main>

    <!-- Modal de Carga -->
    <div id="loader" class="fixed inset-0 bg-black/80 z-[60] hidden flex items-center justify-center flex-col">
        <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mb-4"></div>
        <p class="text-white font-bold animate-pulse">Procesando...</p>
    </div>

    <script>
        // --- 1. DATOS Y ESTADO ---
        // Lista oficial de unidades
        const VEHICLES_DB = [
            "VAN YKY-060-B", "VAN XZJ-801-C", "VAN XYF-783-C",
            "VAN YNT-936-A", "VAN YUU-664-A", "VAN YCD-240-C",
            "L200 XJ-9637-B", "L200 YH-3039-A"
        ];

        // Estado inicial desde PHP
        let appState = <?php echo $initialState; ?>;

        // --- 2. INICIALIZACIÓN ---
        document.addEventListener('DOMContentLoaded', () => {
            renderVehicleGrid();
            // Cargar datos guardados en inputs
            document.getElementById('input-turno').value = appState.turno || "Diurno";
            document.getElementById('display-recorridos').innerText = appState.recorridos || 0;
            
            // Listeners para actualización automática
            document.getElementById('input-turno').addEventListener('change', (e) => {
                appState.turno = e.target.value;
                autoSave();
                updateCard();
            });

            // Hora por defecto
            setNowTime();
            
            // Render inicial
            renderEventsList();
            updateCard();
        });

        function setNowTime() {
            const now = new Date();
            const timeString = now.toTimeString().split(' ')[0].substring(0, 5);
            document.getElementById('custodia-hora').value = timeString;
        }

        // --- 3. RENDERIZADO DE UI ---
        function renderVehicleGrid() {
            const grid = document.getElementById('vehicle-grid');
            grid.innerHTML = VEHICLES_DB.map((placa, index) => `
                <label class="relative cursor-pointer">
                    <input type="checkbox" value="${placa}" class="vehicle-check peer sr-only">
                    <div class="p-2 bg-slate-900 border border-slate-600 rounded text-[10px] md:text-xs text-center font-mono text-slate-300 peer-checked:bg-blue-900 peer-checked:border-blue-500 peer-checked:text-white transition-all select-none h-full flex items-center justify-center">
                        ${placa}
                    </div>
                </label>
            `).join('');
        }

        // --- 4. LÓGICA DE NEGOCIO ---

        function updateRecorridos(delta) {
            let current = parseInt(appState.recorridos) || 0;
            current += delta;
            if (current < 0) current = 0;
            appState.recorridos = current;
            document.getElementById('display-recorridos').innerText = current;
            autoSave();
            updateCard();
        }

        function addEvent() {
            const hora = document.getElementById('custodia-hora').value;
            const ruta = document.getElementById('custodia-ruta').value;
            const otra = document.getElementById('custodia-otra').value.trim();
            
            // Obtener placas seleccionadas
            const checkboxes = document.querySelectorAll('.vehicle-check:checked');
            let unidades = Array.from(checkboxes).map(cb => cb.value);
            
            if (otra) unidades.push(otra.toUpperCase());

            if (unidades.length === 0) {
                alert("⚠ Debes seleccionar al menos un vehículo.");
                return;
            }

            if (!hora) {
                alert("⚠ Falta la hora.");
                return;
            }

            const nuevoEvento = {
                id: Date.now(),
                hora: hora,
                ruta: ruta,
                unidades: unidades
            };

            appState.eventos.push(nuevoEvento);
            
            // Reset form parcial
            document.querySelectorAll('.vehicle-check').forEach(cb => cb.checked = false);
            document.getElementById('custodia-otra').value = '';
            setNowTime(); // Resetear hora a actual

            renderEventsList();
            updateCard();
            autoSave();
        }

        function removeEvent(id) {
            if(confirm('¿Eliminar este registro?')) {
                appState.eventos = appState.eventos.filter(e => e.id !== id);
                renderEventsList();
                updateCard();
                autoSave();
            }
        }

        function renderEventsList() {
            const container = document.getElementById('events-list-edit');
            if (appState.eventos.length === 0) {
                container.innerHTML = '<p class="text-xs text-slate-600 italic">No hay custodias en este turno.</p>';
                return;
            }

            container.innerHTML = appState.eventos.map(ev => `
                <div class="flex justify-between items-center bg-slate-900 p-3 rounded border border-slate-700">
                    <div>
                        <span class="text-blue-400 font-bold font-mono text-xs">${ev.hora}</span>
                        <span class="text-slate-300 text-xs ml-2">${ev.ruta}</span>
                        <div class="text-[10px] text-slate-500 mt-1">${ev.unidades.join(', ')}</div>
                    </div>
                    <button onclick="removeEvent(${ev.id})" class="text-red-500 hover:text-red-400 px-2"><i class="fa-solid fa-trash"></i></button>
                </div>
            `).join('');
        }

        // --- 5. ACTUALIZACIÓN DE TARJETA Y TOTALES ---

        function updateCard() {
            // Header
            document.getElementById('card-date').innerText = new Date().toLocaleDateString('es-MX');
            document.getElementById('card-turno').innerText = appState.turno;
            // Se eliminó la actualización del supervisor
            document.getElementById('card-total-recorridos').innerText = appState.recorridos;

            // Lista Visual
            const cardList = document.getElementById('card-events-list');
            if (appState.eventos.length === 0) {
                cardList.innerHTML = '<p class="text-slate-600 text-center text-xs italic py-4">Sin actividad registrada</p>';
            } else {
                // Ordenar por hora
                const sortedEvents = [...appState.eventos].sort((a, b) => a.hora.localeCompare(b.hora));
                
                cardList.innerHTML = sortedEvents.map(ev => `
                    <div class="relative pl-4 border-l-2 border-slate-600 pb-2">
                        <div class="absolute -left-[5px] top-1 w-2 h-2 rounded-full bg-blue-500"></div>
                        <div class="flex justify-between items-start">
                            <span class="font-mono text-blue-300 font-bold mr-2">${ev.hora}</span>
                            <span class="text-white flex-1 text-right font-semibold">${ev.ruta}</span>
                        </div>
                        <p class="text-slate-400 text-[10px] mt-1 break-words">${ev.unidades.join(', ')}</p>
                    </div>
                `).join('');
            }

            calculateTotals();
        }

        function calculateTotals() {
            let totalVehiculos = 0;
            let resumenRutas = {};

            appState.eventos.forEach(ev => {
                const count = ev.unidades.length;
                totalVehiculos += count;
                
                if (!resumenRutas[ev.ruta]) resumenRutas[ev.ruta] = 0;
                resumenRutas[ev.ruta] += count;
            });

            document.getElementById('card-total-vehiculos').innerText = totalVehiculos;

            // Renderizar tabla resumen
            const tableBody = document.getElementById('card-summary-table');
            let html = '';
            for (const [ruta, cantidad] of Object.entries(resumenRutas)) {
                html += `
                    <tr class="border-b border-slate-800/50">
                        <td class="py-1">${ruta}</td>
                        <td class="py-1 text-right font-mono text-white">${cantidad}</td>
                    </tr>
                `;
            }
            tableBody.innerHTML = html;
            
            return { totalVehiculos, resumenRutas };
        }

        // --- 6. PERSISTENCIA Y COMPARTIR ---

        async function autoSave() {
            try {
                await fetch('novedades_tqpm.php', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'save_state', data: appState })
                });
            } catch (e) {
                console.error("Error autosave", e);
            }
        }

        // FUNCION PRINCIPAL: COMPARTIR EN WHATSAPP
        function shareOnWhatsapp() {
            const target = document.getElementById('capture-target');
            const loader = document.getElementById('loader');
            
            loader.classList.remove('hidden'); 
            window.scrollTo(0,0); // Evitar glitches de scroll

            html2canvas(target, {
                scale: 2,
                backgroundColor: '#0f172a',
                useCORS: true
            }).then(canvas => {
                canvas.toBlob(blob => {
                    const file = new File([blob], "reporte_cazador.png", { type: "image/png" });
                    
                    // Verificar si el navegador soporta compartir archivos (Mobile)
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        navigator.share({
                            files: [file],
                            title: 'Reporte Operativo Cazador',
                            text: 'Adjunto reporte de turno.'
                        })
                        .then(() => loader.classList.add('hidden'))
                        .catch((error) => {
                            console.log('Error compartiendo', error);
                            loader.classList.add('hidden');
                        });
                    } else {
                        // Fallback para PC: Descargar imagen
                        loader.classList.add('hidden');
                        const link = document.createElement('a');
                        link.download = `Cazador_Reporte_${new Date().getTime()}.png`;
                        link.href = canvas.toDataURL("image/png");
                        link.click();
                        alert("ℹ En PC no se puede abrir WhatsApp directamente con la imagen. La imagen se ha descargado; por favor, adjúntala manualmente en WhatsApp Web.");
                    }
                }, 'image/png');
            }).catch(err => {
                loader.classList.add('hidden');
                alert("Error al generar imagen: " + err);
            });
        }

        async function saveHistory() {
            if(!confirm("¿Deseas cerrar el turno? Esto guardará el historial y limpiará los eventos actuales.")) return;

            const loader = document.getElementById('loader');
            loader.classList.remove('hidden');

            const totals = calculateTotals();
            
            let logText = "";
            appState.eventos.forEach(ev => {
                logText += `[${ev.hora} ${ev.ruta} (${ev.unidades.length} veh)] `;
            });

            try {
                const response = await fetch('novedades_tqpm.php', {
                    method: 'POST',
                    body: JSON.stringify({ 
                        action: 'save_history', 
                        data: appState,
                        total_vehiculos: totals.totalVehiculos,
                        log_summary: logText
                    })
                });
                const res = await response.json();
                
                if (res.status === 'success') {
                    alert("✅ Turno archivado correctamente.");
                    location.reload(); 
                }
            } catch (e) {
                alert("Error al guardar historial");
            } finally {
                loader.classList.add('hidden');
            }
        }
    </script>
</body>
</html>