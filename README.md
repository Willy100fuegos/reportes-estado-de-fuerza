# Suite de Reporteadores Operativos (Estado de Fuerza) 👮‍♂️📱

> **Ecosistema de digitalización para la Seguridad Privada.**
> *Transformación del reporte operativo: Del texto plano a Tarjetas Tácticas Visuales e Inteligencia de Datos.*

---

## 🎯 El Problema Resuelto

Anteriormente, los supervisores y guardias reportaban sus novedades (asistencia, incidencias, estado de unidades) mediante mensajes de texto informales en WhatsApp. Esto generaba:
* ❌ Datos desestructurados imposibles de analizar.
* ❌ Falta de estandarización visual.
* ❌ Pérdida de información histórica en chats interminables.

## ✅ La Solución: Reporteadores Web Progresivos

Hemos desarrollado una suite de **Web Apps (PWA)** accesibles desde cualquier móvil, que permiten al operativo capturar datos mediante formularios intuitivos y generar automáticamente una **Tarjeta de Novedades (Imagen PNG)** lista para compartir, mientras el sistema almacena silenciosamente una base de datos histórica.

---

## 🚀 Módulos del Sistema

### 1. 🏭 Braskem Idesa (`novedades_braskem.php`)
* **Enfoque:** Seguridad Patrimonial Industrial.
* **Funciones:** Control de asistencia (Intramuros/Extramuros), checklist de patrullas (Hilux/L200) y bitácora de incidencias.
* **Output:** Tarjeta con indicadores de colores por estatus de patrulla.
![Braskem Screenshot](http://imgfz.com/i/sqaTD0U.png)

### 2. 🛡️ Cazador TQPM (`novedades_tqpm.php`)
* **Enfoque:** Custodia Móvil y Disuasión.
* **Funciones:** Registro de recorridos, bitácora de custodias en ruta (Allende-Sitio) y conteo de vehículos protegidos.
* **Output:** Timeline visual de eventos y resumen ejecutivo de rutas.
![TQPM Screenshot](http://imgfz.com/i/8DBkKb0.png)

### 3. 🚚 Centurión (`novedades_centurion.php`)
* **Enfoque:** Logística y Custodia de Carga Pesada.
* **Funciones:** Monitoreo de estado de fuerza en sedes remotas (Silao/Braskem) y checklist de unidades blindadas (Júpiter, Pegaso, etc.).
* **Output:** Dashboard de disponibilidad de flota y personal.
![Centurion Screenshot](http://imgfz.com/i/l0I3CeZ.png)

### 4. 🏙️ SESCA Veracruz (`novedades_veracruz.php`)
* **Enfoque:** Seguridad Física Regional.
* **Funciones:** Reporte de asistencia por zonas (Boca del Río, Zona Norte) con cálculo automático de % de cobertura operativa.
* **Output:** Gráficos de barras de cobertura y desglose por servicio.
![Veracruz Screenshot](http://imgfz.com/i/BTPu2XG.png)

### 5. 🏢 Gorat Coatzacoalcos (`novedades.php`)
* **Enfoque:** Operaciones Base / Master.
* **Funciones:** Gestión de servicios fijos y dinámicos (Eventuales), control de vacaciones y descansos.
* **Output:** Matriz completa de estado de fuerza local.
![Coatza Screenshot](http://imgfz.com/i/9OVPQcS.png)

---

## 🛠️ Stack Tecnológico

Arquitectura **Monolito Ligero** diseñada para máxima velocidad y despliegue sin dependencias complejas.

| Componente | Tecnología | Función |
| :--- | :--- | :--- |
| **Backend** | **PHP 8.x** | Procesamiento de datos y gestión de archivos (CSV/JSON). |
| **Persistencia** | **JSON Flat-File** | Base de datos NoSQL ligera para caché de estado (Persistencia de sesión). |
| **Histórico** | **CSV** | Logs estructurados descargables para análisis en Excel/PowerBI. |
| **Frontend** | **HTML5 + TailwindCSS** | Interfaz responsiva *Touch-Friendly*. |
| **Renderizado** | **html2canvas** | Generación de imágenes (Screenshots) del reporte en el cliente. |
| **Share API** | **Web Share API** | Integración nativa con WhatsApp/Telegram en móviles. |

---

## 🔄 Flujo de Trabajo (Workflow)

1.  **Captura:** El guardia accede a la URL desde su celular y llena los contadores (+/-).
2.  **Visualización:** El sistema genera una vista previa en tiempo real de la "Tarjeta".
3.  **Digitalización:** Al presionar "Compartir", se genera una imagen PNG de alta calidad.
4.  **Distribución:** La imagen se envía al grupo de WhatsApp de Coordinación.
5.  **Archivo:** Automáticamente, los datos se guardan en el CSV histórico del servidor para auditoría.

---
**Desarrollado por:**
**William Velázquez Valenzuela**
*Director de Tecnologías | Pixmedia Agency*
