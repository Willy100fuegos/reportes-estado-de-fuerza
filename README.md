# Suite de Reporteadores Operativos (Estado de Fuerza) 👮‍♂️📱

> **Ecosistema de digitalización para la Seguridad Privada.**
> *Transformación del reporte operativo: Del texto plano a Tarjetas Tácticas Visuales e Inteligencia de Datos.*

---

## 🎯 El Problema Resuelto

Anteriormente, los supervisores y guardias reportaban sus novedades (asistencia, incidencias, estado de unidades) mediante mensajes de texto informales en WhatsApp. Esto generaba:
* ❌ Datos desestructurados imposibles de analizar.
* ❌ Falta de estandarización visual.
* ❌ Pérdida de información histórica en chats interminables.

## ✅ La Solución: Reporteadores Web Progresivos (PWA)

Hemos desarrollado una suite de **Web Apps** ligeras accesibles desde cualquier móvil. Permiten al operativo capturar datos mediante formularios intuitivos y generar automáticamente una **Tarjeta de Novedades (Imagen PNG)** lista para compartir, mientras el sistema almacena silenciosamente una base de datos histórica en el servidor.

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

## 🧠 Arquitectura de Datos (Flat-File System)

A diferencia de sistemas complejos, esta suite utiliza una arquitectura **Serverless-Like** basada en archivos planos, eliminando la necesidad de configurar bases de datos MySQL.

1.  **Persistencia Volátil (`database_*.json`):**
    * Cada vez que un usuario edita un campo, el sistema guarda el estado en un archivo JSON.
    * Esto permite "recuperar" el borrador si el usuario cierra el navegador o recarga la página.
    
2.  **Persistencia Histórica (`historial_*.csv`):**
    * Al "Cerrar Turno" o "Guardar Historial", los datos se escriben en un archivo CSV acumulativo.
    * **Autogeneración:** No es necesario crear estos archivos manualmente. El script PHP detecta si no existen y los crea automáticamente con los encabezados correctos (UTF-8 BOM compatible con Excel).

---

## 🛠️ Stack Tecnológico

Arquitectura **Monolito Ligero** diseñada para máxima velocidad y despliegue sin dependencias.

| Componente | Tecnología | Función |
| :--- | :--- | :--- |
| **Backend** | **PHP 8.x** | Procesamiento de datos y gestión de archivos (I/O). |
| **Frontend** | **HTML5 + TailwindCSS** | Interfaz responsiva *Touch-Friendly*. |
| **Renderizado** | **html2canvas** | Generación de imágenes (Screenshots) client-side. |
| **Share API** | **Web Share API** | Integración nativa con WhatsApp/Telegram. |

---

## 👨‍💻 Guía de Despliegue (Deploy)

Para instalar cualquiera de estos módulos en tu servidor (cPanel/Apache/Nginx):

1.  **Subir Archivo:** Sube el archivo `.php` deseado (ej. `novedades_braskem.php`) a tu carpeta pública.
2.  **Permisos de Escritura (Crucial):** Asegúrate de que la carpeta donde alojas el archivo tenga permisos de escritura (generalmente `755` o `777` en entornos controlados).
    * *¿Por qué?* El script necesita permiso para crear y escribir los archivos `.json` y `.csv`.
3.  **Listo:** Accede a la URL. El sistema creará los archivos de datos automáticamente en el primer uso.

---
**Desarrollado por:**
**William Velázquez Valenzuela**
*Director de Tecnologías | Pixmedia Agency*
