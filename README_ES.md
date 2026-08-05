# ERP para Nextcloud — Módulo de Gestión de Empleados y Recursos Humanos

**Desarrollado por:** Luis Ángel
**Versión:** Beta 2025
**Licencia:** GNU AGPL v3

---

## 📄 Descripción general

Este módulo ERP para Nextcloud permite a las empresas gestionar de forma integral la información de Employee, Department, Position, Team y beneficios, con una integración nativa en el ecosistema Nextcloud.

Diseñado para PyMEs, despachos y organizaciones que buscan soberanía digital y personalización, evitando los altos costos de SaaS comerciales.

---

## 🏢 Funcionalidades actuales

### 🔹 Capital Humano

* **Empleados**

  * Datos generales, bancarios y laborales.
  * History de vacaciones.
  * Fondo de savings.
  * Estructura laboral (Socio / Gerente / Empleado).
  * Asignación a Department y Position.
  * Notas internas.
  * Documentos (expedientes, memorándums, identificaciones).

* **Departamentos**

  * Creación y asignación de Employee a Department.

* **Positions**

  * Gestión de Position de trabajo.

* **Teams**

  * Agrupación de Employee bajo líderes o gerentes.

### 🔹 Módulo de Ahorro (Ahorro Gossler)

* Solicitud y gestión del fondo de savings por parte de los Employee.
* History de solicitudes.
* Panel de administración para revisar y autorizar solicitudes.

### 🔹 Tiempo Laboral

* Calendario de Absence y vacaciones.
* Cálculo automático de días de vacaciones conforme a la Ley Federal del Trabajo (México).

---

## 🖼️ Interfaz de usuario

**Vista general del empleado:**
![Empleado](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/employee_general.png)

**Notas personales:**
![Notas](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/notes.png)

**Datos personales (RFC, IMSS, CURP):**
![Personal](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/personal.png)

**Archivos y expedientes:**
![Archivos](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/files.png)

**Departamentos y Position:**
![Áreas y Position](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/areas_positions.png)

**Teams de trabajo:**
![Teams](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/Team.png)

**Solicitud de savings:**
![Solicitud](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/savings_request.png)

**Panel de administración de solicitudes:**
![Panel Ahorro](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/savings_panel.png)

**Calendario de vacaciones:**
![Calendario](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/calendar.png)

**Tabla de aniversarios y Absence:**
![Aniversarios y Absence](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/anniversaries_absences.png)

**Configuraciones globales del módulo:**
![Configuraciones](https://raw.githubusercontent.com/Destripador/employees/refs/heads/main/docs/screenshots/Settings.png)

---

## ⚙️ Requisitos técnicos

* Nextcloud 28+
* PHP 8.1+
* MariaDB / MySQL
* Docker (opcional, recomendado)

---

## 🔄 Próximos pasos en el Roadmap

1. Completar el flujo completo de vacaciones y Absence.
2. Reportes descargables en Excel/PDF.
3. Roles y permisos refinados.
4. Desarrollo de manuales técnicos y de usuario.
5. Preparar una versión estable de código abierto.

---

## 🔒 Licencia

GNU Affero General Public License v3 (AGPL-3.0) — Software libre para su uso, modificación y distribución bajo los términos de la licencia. Las modificaciones y las interacciones en red requieren la divulgación del código fuente.

---

## 🤝 Contribuciones

Este módulo está destinado para uso interno y colaboración con otras firmas interesadas en soluciones ERP personalizadas sobre Nextcloud.

Se aceptan colaboraciones y sugerencias.

---

## 🚀 Estado actual

* **Módulo funcional** en entorno de producción interno.
* **Beta pública** en preparación.
* **Documentación en desarrollo.**
