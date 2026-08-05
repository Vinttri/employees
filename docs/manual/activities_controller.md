# Documentación del Controlador ActivitiesController

## Descripción
El controlador ActivitiesController es responsable de gestionar las Activity de los Employee en Nextcloud. Proporciona endpoints para obtener, crear, modificar y eliminar Activity, así como exportar e importar datos en formato XLSX.

## Endpoints

### Obtener la lista de Activity
- **URL**: /api/Activity
- **Método**: GET
- **Descripción**: Obtiene la lista completa de Activity.
- **Acceso Requerido**: admin, Employee, recursos_humanos

### Obtener actividad por ID
- **URL**: /api/Activity/{id}
- **Método**: GET
- **Descripción**: Obtiene una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
- **Acceso Requerido**: admin, recursos_humanos

### Eliminar actividad
- **URL**: /api/Activity/{id}
- **Método**: DELETE
- **Descripción**: Elimina una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
- **Acceso Requerido**: admin, recursos_humanos

### Modificar actividad
- **URL**: /api/Activity/{id}
- **Método**: PUT
- **Descripción**: Modifica una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
  - nombre: Nombre de la actividad (string)
  - detalles: Detalles de la actividad (string, opcional)
  - tiempoestimado: Tiempo estimado en horas (float)
  - tipo: Tipo de tiempo ('horas' o 'minutos') (string)
- **Acceso Requerido**: admin, recursos_humanos

### Crear nueva actividad
- **URL**: /api/Activity
- **Método**: POST
- **Descripción**: Crea una nueva actividad.
- **Parámetros**:
  - nombre: Nombre de la actividad (string)
  - detalles: Detalles de la actividad (string, opcional)
  - tiempoestimado: Tiempo estimado en horas (float)
  - tipo: Tipo de tiempo ('horas' o 'minutos') (string)
- **Acceso Requerido**: admin, recursos_humanos

### Exportar Activity a XLSX
- **URL**: /api/Activity/exportar
- **Método**: GET
- **Descripción**: Exporta la lista de Activity a un archivo XLSX.
- **Acceso Requerido**: admin, recursos_humanos

### Importar Activity desde XLSX
- **URL**: /api/Activity/importar
- **Método**: POST
- **Descripción**: Importa la lista de Activity desde un archivo XLSX.
- **Parámetros**:
  - ActivitiesfileXLSX: Archivo XLSX con los datos de las Activity
- **Acceso Requerido**: admin, recursos_humanos

## Métodos Privados

### getUploadedFile(string $key): array
- **Descripción**: Obtiene un archivo subido y maneja posibles errores.
- **Parámetros**:
  - $key: Clave del archivo subido en la solicitud
- **Retorno**: Arreglo con información del archivo subido

## Notas Adicionales
- Todos los endpoints requieren autenticación.
- El tipo de tiempo puede ser 'horas' o 'minutos', y se convierte automáticamente a minutos si es 'horas'.
- Los files XLSX deben tener una estructura específica para que sean importados correctamente.

## Documentación del Controlador ActivitiesController

### Descripción
El controlador `ActivitiesController` es responsable de gestionar las Activity de los Employee en Nextcloud. Proporciona endpoints para obtener, crear, modificar y eliminar Activity, así como exportar e importar datos en formato XLSX.

## Endpoints

### Obtener la lista de Activity
- **URL**: /api/Activity
- **Método**: GET
- **Descripción**: Obtiene la lista completa de Activity.
- **Acceso Requerido**: admin, Employee, recursos_humanos

### Obtener actividad por ID
- **URL**: /api/Activity/{id}
- **Método**: GET
- **Descripción**: Obtiene una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
- **Acceso Requerido**: admin, recursos_humanos

### Eliminar actividad
- **URL**: /api/Activity/{id}
- **Método**: DELETE
- **Descripción**: Elimina una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
- **Acceso Requerido**: admin, recursos_humanos

### Modificar actividad
- **URL**: /api/Activity/{id}
- **Método**: PUT
- **Descripción**: Modifica una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
  - nombre: Nombre de la actividad (string)
  - detalles: Detalles de la actividad (string, opcional)
  - tiempoestimado: Tiempo estimado en horas (float)
  - tipo: Tipo de tiempo ('horas' o 'minutos') (string)
- **Acceso Requerido**: admin, recursos_humanos

### Crear nueva actividad
- **URL**: /api/Activity
- **Método**: POST
- **Descripción**: Crea una nueva actividad.
- **Parámetros**:
  - nombre: Nombre de la actividad (string)
  - detalles: Detalles de la actividad (string, opcional)
  - tiempoestimado: Tiempo estimado en horas (float)
  - tipo: Tipo de tiempo ('horas' o 'minutos') (string)
- **Acceso Requerido**: admin, recursos_humanos

### Exportar Activity a XLSX
- **URL**: /api/Activity/exportar
- **Método**: GET
- **Descripción**: Exporta la lista de Activity a un archivo XLSX.
- **Acceso Requerido**: admin, recursos_humanos

### Importar Activity desde XLSX
- **URL**: /api/Activity/importar
- **Método**: POST
- **Descripción**: Importa la lista de Activity desde un archivo XLSX.
- **Parámetros**:
  - ActivitiesfileXLSX: Archivo XLSX con los datos de las Activity
- **Acceso Requerido**: admin, recursos_humanos

## Métodos Privados

### getUploadedFile(string $key): array
- **Descripción**: Obtiene un archivo subido y maneja posibles errores.
- **Parámetros**:
  - $key: Clave del archivo subido en la solicitud
- **Retorno**: Arreglo con información del archivo subido

## Notas Adicionales
- Todos los endpoints requieren autenticación.
- El tipo de tiempo puede ser 'horas' o 'minutos', y se convierte automáticamente a minutos si es 'horas'.
- Los files XLSX deben tener una estructura específica para que sean importados correctamente.
