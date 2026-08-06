<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Luis Angel Alvarado Hernandez <luis.alvarado@crowe.mx>
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
'routes' => [
		/********************************** INDEX **********************************************/
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],


		/******************************** EMPLEADOS ********************************************/
		# OBTENER DATOS DE USUARIO NEXTCLOUD
		['name' => 'employees#GetUsers', 'url' => '/users', 'verb' => 'GET'],

		# OBTIENE LA LISTA DE EMPĹEADOS, USUARIOS Y USUARIOS DESACTIVADOS
		['name' => 'employees#GetUserLists', 'url' => '/GetUserLists', 'verb' => 'GET'],

		# LISTADO COMPLETO DE EMPLEADOS CON SUS DATOS
		['name' => 'employees#GetEmpleadosList', 'url' => '/GetEmpleadosList', 'verb' => 'GET'],

		# LISTADO DE EMPLEADOS POR AREA
		['name' => 'employees#GetEmpleadosArea', 'url' => '/GetEmpleadosArea/{id_area}', 'verb' => 'GET'],

		# LISTADO DE EMPLEADOS POR PUESTO
		['name' => 'employees#GetEmpleadosPuesto', 'url' => '/GetEmpleadosPuesto/{id_position}', 'verb' => 'GET'],

		# LISTADO DE EMPLEADOS POR EQUIPO
		['name' => 'employees#GetEmpleadosEquipo', 'url' => '/GetEmpleadosEquipo/{id_team}', 'verb' => 'GET'],

		# EXPORTA LISTA DE EMPLEADOS A EXCEL
		['name' => 'employees#ExportListEmpleados', 'url' => '/ExportListEmpleados', 'verb' => 'GET'],

		# LISTADO DE EMPLEADOS EN EQUIPO DEL USUARIO ACTUAL
		['name' => 'employees#GetMyEquipo', 'url' => '/GetMyEquipo', 'verb' => 'GET'],

		['name' => 'employees#uploadAvatar', 'url' => '/uploadAvatar', 'verb' => 'POST'],

		['name' => 'employees#GuardarNota', 'url' => '/GuardarNota', 'verb' => 'POST'],
		['name' => 'employees#CambiosEmpleado', 'url' => '/CambiosEmpleado', 'verb' => 'POST'],
		['name' => 'employees#CambiosPersonal', 'url' => '/CambiosPersonal', 'verb' => 'POST'],
		['name' => 'employees#listarContactosEmergencia', 'url' => '/employees/{id_employee}/contactos-emergencia', 'verb' => 'GET'],
		['name' => 'employees#crearEmergencyContact', 'url' => '/employees/{id_employee}/contactos-emergencia', 'verb' => 'POST'],
		['name' => 'employees#actualizarEmergencyContact', 'url' => '/employees/{id_employee}/contactos-emergencia/{id}', 'verb' => 'PUT'],
		['name' => 'employees#eliminarEmergencyContact', 'url' => '/employees/{id_employee}/contactos-emergencia/{id}', 'verb' => 'DELETE'],
		['name' => 'employees#marcarEmergencyContactPrincipal', 'url' => '/employees/{id_employee}/contactos-emergencia/{id}/principal', 'verb' => 'POST'],
		['name' => 'employees#ActivarEmpleado', 'url' => '/ActivarEmpleado', 'verb' => 'POST'],
		['name' => 'employees#createEmployeesFromNextcloud', 'url' => '/directory/users/import', 'verb' => 'POST'],
		['name' => 'employees#previewContactsOrganization', 'url' => '/directory/contacts/preview', 'verb' => 'GET'],
		['name' => 'employees#importContactsOrganization', 'url' => '/directory/contacts/import', 'verb' => 'POST'],
		['name' => 'employees#syncDirectory', 'url' => '/directory/sync', 'verb' => 'POST'],
		['name' => 'employees#directorySyncStatus', 'url' => '/directory/status', 'verb' => 'GET'],
		['name' => 'employees#ActivarUsuario', 'url' => '/ActivarUsuario', 'verb' => 'POST'],
		['name' => 'employees#EliminarEmpleado', 'url' => '/EliminarEmpleado', 'verb' => 'POST'],
		['name' => 'employees#DesactivarEmpleado', 'url' => '/DesactivarEmpleado', 'verb' => 'POST'],
		['name' => 'employees#ImportListEmpleados', 'url' => '/ImportListEmpleados', 'verb' => 'POST'],
		['name' => 'employees#ActualizarEstadoAhorro', 'url' => '/ActualizarEstadoAhorro', 'verb' => 'POST'],

		/***************************** ORGANIGRAMA *****************************************/
		['name' => 'org_chart#GetOrgChart', 'url' => '/GetOrgChart', 'verb' => 'GET'],
		['name' => 'org_chart#CrearRelacionOrgChart', 'url' => '/CrearRelacionOrgChart', 'verb' => 'POST'],
		['name' => 'org_chart#EliminarRelacionOrgChart', 'url' => '/EliminarRelacionOrgChart', 'verb' => 'POST'],

		['name' => 'org_chart#GuardarPosicionOrgChart', 'url' => '/GuardarPosicionOrgChart', 'verb' => 'POST'],
		['name' => 'org_chart#GuardarPosicionesOrgChart', 'url' => '/GuardarPosicionesOrgChart', 'verb' => 'POST'],


		/******************************** AREAS ********************************************/
		['name' => 'areas#GetAreasFix', 'url' => '/GetAreasFix', 'verb' => 'GET'],
		['name' => 'areas#GetAreasList', 'url' => '/GetAreasList', 'verb' => 'GET'],
		['name' => 'areas#ExportListAreas', 'url' => '/ExportListAreas', 'verb' => 'GET'],

		['name' => 'areas#GuardarCambioArea', 'url' => '/GuardarCambioArea', 'verb' => 'POST'],
		['name' => 'areas#ImportListAreas', 'url' => '/ImportListAreas', 'verb' => 'POST'],
		['name' => 'areas#EliminarArea', 'url' => '/EliminarArea', 'verb' => 'POST'],
		['name' => 'areas#crearArea', 'url' => '/crearArea', 'verb' => 'POST'],


		/****************************** PUESTOS *********************************************/
		['name' => 'positions#GetPositionsFix', 'url' => '/GetPositionsFix', 'verb' => 'GET'],
		['name' => 'positions#GetPositionsList', 'url' => '/GetPositionsList', 'verb' => 'GET'],
		['name' => 'positions#ExportListPositions', 'url' => '/ExportListPositions', 'verb' => 'GET'],

		['name' => 'positions#GuardarCambioPositions', 'url' => '/GuardarCambioPositions', 'verb' => 'POST'],
		['name' => 'positions#ImportListPositions', 'url' => '/ImportListPositions', 'verb' => 'POST'],
		['name' => 'positions#EliminarPuesto', 'url' => '/EliminarPuesto', 'verb' => 'POST'],
		['name' => 'positions#crearPuesto', 'url' => '/crearPuesto', 'verb' => 'POST'],


		/****************************** EQUIPOS *********************************************/
		['name' => 'teams#GetTeamsFix', 'url' => '/GetTeamsFix', 'verb' => 'GET'],
		['name' => 'teams#GetTeamsList', 'url' => '/GetTeamsList', 'verb' => 'GET'],
		['name' => 'teams#ExportListTeams', 'url' => '/ExportListTeams', 'verb' => 'GET'],

		['name' => 'teams#GuardarCambioEquipo', 'url' => '/GuardarCambioEquipo', 'verb' => 'POST'],
		['name' => 'teams#ImportListTeams', 'url' => '/ImportListTeams', 'verb' => 'POST'],
		['name' => 'teams#EliminarEquipo', 'url' => '/EliminarEquipo', 'verb' => 'POST'],
		['name' => 'teams#GetEquipoJefe', 'url' => '/GetEquipoJefe', 'verb' => 'POST'],
		['name' => 'teams#crearEquipo', 'url' => '/crearEquipo', 'verb' => 'POST'],


		/***************************** CONFIGURACIONES ***************************************/
		['name' => 'settings#GetConfigurations', 'url' => '/GetConfigurations', 'verb' => 'GET'],
		['name' => 'settings#GetDataManager', 'url' => '/GetDataManager', 'verb' => 'GET'],

		['name' => 'settings#provisioning', 'url' => '/provisioning', 'verb' => 'POST'],
		['name' => 'settings#ActualizarGestor', 'url' => '/ActualizarGestor', 'verb' => 'POST'],
		['name' => 'settings#ActualizarConfiguracion', 'url' => '/ActualizarConfiguracion', 'verb' => 'POST'],
		['name' => 'settings#ActualizarConfiguracionReportes', 'url' => '/ActualizarConfiguracionReportes', 'verb' => 'POST',],
		['name' => 'permissions#grupos', 'url' => '/permisos/grupos',	'verb' => 'GET',],
		['name' => 'permissions#usuario', 'url' => '/permisos/usuario/{uid}',	'verb' => 'GET',],
		['name' => 'permissions#actualizarUsuario', 'url' => '/permisos/usuario/{uid}',	'verb' => 'POST',],

		['name' => 'permission_groups#index', 'url' => '/permisos/catalogo', 'verb' => 'GET'],
		['name' => 'permission_groups#create', 'url' => '/permisos/catalogo', 'verb' => 'POST'],
		['name' => 'permission_groups#update', 'url' => '/permisos/catalogo/{id}', 'verb' => 'POST'],
		['name' => 'permission_groups#enable', 'url' => '/permisos/catalogo/{id}/enable', 'verb' => 'POST'],
		['name' => 'permission_groups#disable', 'url' => '/permisos/catalogo/{id}/disable', 'verb' => 'POST'],

		['name' => 'permission_groups#estructura', 'url' => '/permisos/catalogo/estructura', 'verb' => 'GET'],
		['name' => 'permission_groups#repararEstructura', 'url' => '/permisos/catalogo/estructura/reparar', 'verb' => 'POST'],

		['name' => 'permission_groups#gruposNextcloud', 'url' => '/permisos/catalogo/grupos-nextcloud', 'verb' => 'GET'],
		['name' => 'permissions#contexto', 'url' => '/permisos/contexto', 'verb' => 'GET'],

		/***************************** CAPITAL HUMANO ***************************************/
		['name' => 'human_resources#GetCapitalHumano', 'url' => '/GetCapitalHumano', 'verb' => 'GET'],
		['name' => 'human_resources#UpdateCapitalHumano', 'url' => '/UpdateCapitalHumano', 'verb' => 'POST'],


		/****************************** ANIVERSARIOS ****************************************/
		['name' => 'anniversaries#Getaniversarios', 'url' => '/Getaniversarios', 'verb' => 'GET'],
		['name' => 'anniversaries#VaciarAniversarios', 'url' => '/VaciarAniversarios', 'verb' => 'GET'],
		['name' => 'anniversaries#AgregarNewAniversario', 'url' => '/AgregarNewAniversario', 'verb' => 'POST'],
		['name' => 'anniversaries#ExportListAniversarios', 'url' => '/ExportListAniversarios', 'verb' => 'GET'],
		['name' => 'anniversaries#GetAniversarioByDate', 'url' => '/GetAniversarioByDate', 'verb' => 'POST'],
		['name' => 'anniversaries#ImportListAniversarios', 'url' => '/ImportListAniversarios', 'verb' => 'POST'],
		['name' => 'anniversaries#modificarAniversario', 'url' => '/modificarAniversario', 'verb' => 'POST'],
		['name' => 'anniversaries#deleteAniversario', 'url' => '/deleteAniversario', 'verb' => 'POST'],

		/******************************* AUSENCIAS *****************************************/
		['name' => 'absences#GetNotificationsSubordinates', 'url' => '/GetNotificationsSubordinates', 'verb' => 'GET'],

		['name' => 'absences#GetAusenciasEmployeeHistory', 'url' => '/GetAusenciasEmployeeHistory', 'verb' => 'POST'],
		['name' => 'absences#GetAusenciasHistoryAll', 'url' => '/GetAusenciasHistoryAll', 'verb' => 'POST'],
		['name' => 'absences#GetAusenciasHistory', 'url' => '/GetAusenciasHistory', 'verb' => 'POST'],
		['name' => 'absences#GetAusenciasMyWorkers', 'url' => '/GetAusenciasMyWorkers', 'verb' => 'POST'],
		['name' => 'absences#GetAusenciasByUser', 'url' => '/GetAusenciasByUser', 'verb' => 'POST'],
		['name' => 'absences#EnviarAusencia', 'url' => '/EnviarAusencia', 'verb' => 'POST'],
		['name' => 'absences#GetAbsenceDetails', 'url' => '/GetAbsenceDetails', 'verb' => 'GET'],
		['name' => 'absences#CancelarAusencia',   'url' => '/CancelarAusencia',   'verb' => 'POST'],
		['name' => 'absences#EditAbsence', 'url' => '/EditAbsence', 'verb' => 'POST'],
		['name' => 'absences#EditAbsence', 'url' => '/EditAbsence', 'verb' => 'POST'],
		['name' => 'absences#CheckPrimaVacacional', 'url' => '/check-prima-vacacional', 'verb' => 'GET'],
		['name' => 'absences#GetHistoryReporte', 'url' => '/historial-reporte', 'verb' => 'GET'],
		['name' => 'absences#GetHistoryReporteAniversario', 'url' => '/historial-reporte-Anniversary', 'verb' => 'GET'],
		['name' => 'absences#getEmployeeVacations', 'url' => '/employee-vacations', 'verb' => 'GET'],
		['name' => 'absences#getPeriodosVacaciones', 'url' => '/periodos-vacaciones', 'verb' => 'GET'],
		['name' => 'absences#EditarAcumuladoManual', 'url' => '/edit-acumulado-manual', 'verb' => 'POST'],
		['name' => 'absences#AsignarDiasDerecho', 'url' => '/AsignarDiasDerecho', 'verb' => 'POST'],

		['name' => 'absences#AprobarAusencia', 'url' => '/AprobarAusencia', 'verb' => 'POST'],
		['name' => 'absences#RechazarAusencia', 'url' => '/RechazarAusencia', 'verb' => 'POST'],

		['name' => 'absences#DescargarReportePeriodosExcel', 'url' => '/reporte-periodos-excel', 'verb' => 'GET'],

		/**************************** TIPO AUSENCIAS **************************************/
		['name' => 'absence_types#getType', 'url' => '/getType', 'verb' => 'GET'],
		['name' => 'absence_types#VaciarTipo', 'url' => '/VaciarTipo', 'verb' => 'GET'],
		['name' => 'absence_types#AgregarNewTipo', 'url' => '/AgregarNewTipo', 'verb' => 'POST'],
		['name' => 'absence_types#ExportarTipo', 'url' => '/ExportarTipo', 'verb' => 'GET'],
		['name' => 'absence_types#importarTipo', 'url' => '/importarTipo', 'verb' => 'POST'],
		['name' => 'absence_types#modificarTipo', 'url' => '/modificarTipo', 'verb' => 'POST'],
		['name' => 'absence_types#deleteTipo',    'url' => '/deleteTipo',    'verb' => 'POST'],

		/*************************** PRIMA VACACIONAL *************************************/
		['name' => 'vacation_bonus_payment#index', 'url' => '/prima-vacacional-pagos', 'verb' => 'GET'],
		['name' => 'vacation_bonus_payment#guardar', 'url' => '/prima-vacacional-pagos', 'verb' => 'POST'],

		/******************************** AHORRO ******************************************/
		['name' => 'savings#GetInfoAhorro', 'url' => '/GetInfoAhorro', 'verb' => 'POST'],
		['name' => 'savings#EnviarSolicitud', 'url' => '/EnviarSolicitud', 'verb' => 'POST'],
		['name' => 'savings#getHistory', 'url' => '/getHistory/{id_user}', 'verb' => 'GET'],
		['name' => 'savings#GetHistoryPanel', 'url' => '/GetHistoryPanel/{options_fechas_value}/{options_estado_values}', 'verb' => 'GET'],
		['name' => 'savings#GenerateReport', 'url' => '/GenerateReport/{options_fechas_value}/{options_estado_values}', 'verb' => 'GET'],
		['name' => 'savings#AceptarAhorro', 'url' => '/AceptarAhorro', 'verb' => 'POST'],
		['name' => 'savings#DenegarAhorro', 'url' => '/DenegarAhorro', 'verb' => 'POST'],

		/******************************* CLIENTES *****************************************/
		['name' => 'clients#GetCompaniesGroups',  'url' => '/GetCompaniesGroups',  'verb' => 'GET'],
		['name' => 'clients#GetClientesEmpleadosLookup', 'url' => '/GetClientesEmpleadosLookup', 'verb' => 'GET'],
		['name' => 'clients#GetCompanieGroup',            'url' => '/GetCompanieGroup',    'verb' => 'POST'],
		['name' => 'clients#crearCliente', 'url' => '/crearCliente', 'verb' => 'POST'],
		['name' => 'clients#modificarCliente',    'url' => '/modificarCliente',    'verb' => 'POST'],
		['name' => 'clients#deleteById',          'url' => '/deleteCliente',       'verb' => 'POST'],
		['name' => 'clients#importarClientes',    'url' => '/importarClientes',    'verb' => 'POST'],
		['name' => 'clients#Exportarclients',    'url' => '/Exportarclients',    'verb' => 'GET'],

		/******************************* HONORARIOS ***************************************/
		['name' => 'professional_fees#getHonorarios',     'url' => '/getHonorarios',       'verb' => 'GET'],
		['name' => 'professional_fees#findById',          'url' => '/getHonorario',        'verb' => 'POST'],
		['name' => 'professional_fees#crearHonorario',    'url' => '/crearHonorario',      'verb' => 'POST'],
		['name' => 'professional_fees#modificarHonorario','url' => '/modificarHonorario',  'verb' => 'POST'],
		['name' => 'professional_fees#deleteById',        'url' => '/deleteHonorario',     'verb' => 'POST'],
		['name' => 'professional_fees#findByCliente', 'url' => '/findHonorariosByCliente', 'verb' => 'POST'],
		['name' => 'professional_fees#completarHonorario', 'url' => '/completarHonorario', 'verb' => 'POST'],
		['name' => 'fee_payments#findByHonorario', 'url' => '/findParcialidadesByHonorario', 'verb' => 'POST'],
		['name' => 'fee_payments#marcarPagada', 'url' => '/marcarParcialidadPagada', 'verb' => 'POST'],
		['name' => 'fee_payments#findById', 'url' => '/findParcialidadesById', 'verb' => 'POST'],
		['name' => 'fee_payments#markInvoiced', 'url' => '/mark-fee-payment-invoiced', 'verb' => 'POST'],
		['name' => 'fee_payments#cancelarPago', 'url' => '/cancelarPagoParcialidad', 'verb' => 'POST'],
		['name' => 'fee_payments#agregarParcialidadIguala', 'url' => '/agregarParcialidadIguala', 'verb' => 'POST'],
		['name' => 'professional_fees#finalizarHonorario', 'url' => '/finalizarHonorario', 'verb' => 'POST'],
		['name' => 'professional_fees#reactivarHonorario', 'url' => '/reactivarHonorario', 'verb' => 'POST'],
		['name' => 'professional_fees#actualizarMetadatos', 'url' => '/actualizarMetadatosHonorario', 'verb' => 'POST'],
		['name' => 'professional_fees#generarSolicitudRecibo', 'url' => '/generarSolicitudRecibo', 'verb' => 'GET'],

		/****************************** ACTIVIDADES ***************************************/
		['name' => 'activities#crearActividad', 'url' => '/crearActividad', 'verb' => 'POST'],
		['name' => 'activities#modificarActividad', 'url' => '/ModificarActividad', 'verb' => 'POST'],
		['name' => 'activities#findById', 'url' => '/GetActividad', 'verb' => 'POST'],
		['name' => 'activities#deleteById', 'url' => '/DeleteActividad', 'verb' => 'POST'],
		['name' => 'activities#ImportarActivities', 'url' => '/ImportarActivities', 'verb' => 'POST'],
		['name' => 'activities#GetActivities', 'url' => '/GetActivities', 'verb' => 'GET'],
		['name' => 'activities#ExportarActivities', 'url' => '/ExportarActivities', 'verb' => 'GET'],

		/****************************** FESTIVOS ***************************************/
		['name' => 'holidays#getFestivos',       'url' => '/getFestivos',       'verb' => 'GET'],
		['name' => 'holidays#findById',          'url' => '/getFestivo',        'verb' => 'POST'],
		['name' => 'holidays#findByFecha',       'url' => '/getFestivoByFecha', 'verb' => 'POST'],
		['name' => 'holidays#crearFestivo',      'url' => '/crearFestivo',      'verb' => 'POST'],
		['name' => 'holidays#modificarFestivo',  'url' => '/modificarFestivo',  'verb' => 'POST'],
		['name' => 'holidays#deleteById',        'url' => '/deleteFestivo',     'verb' => 'POST'],
		['name' => 'holidays#importarFestivos',  'url' => '/importarFestivos',  'verb' => 'POST'],
		['name' => 'holidays#exportarFestivos',  'url' => '/exportarFestivos',  'verb' => 'GET'],
		['name' => 'holidays#vaciarFestivos',    'url' => '/vaciarFestivos',    'verb' => 'GET'],

		/****************************** BOARDING ***************************************/
		// Boarding (catálogo)
		['name' => 'boarding#getBoarding',       'url' => '/getBoarding',        'verb' => 'GET'],
		['name' => 'boarding#findById',          'url' => '/getBoardingItem',    'verb' => 'POST'],
		['name' => 'boarding#findByOn',          'url' => '/getBoardingByOn',    'verb' => 'POST'],
		['name' => 'boarding#crearBoarding',     'url' => '/crearBoarding',      'verb' => 'POST'],
		['name' => 'boarding#modificarBoarding', 'url' => '/modificarBoarding',  'verb' => 'POST'],
		['name' => 'boarding#deleteById',        'url' => '/deleteBoarding',     'verb' => 'POST'],

		// Empleados Boarding (pivote / checklist por empleado)
		['name' => 'employee_onboarding#getChecklist',       'url' => '/getChecklistEmpleado',      'verb' => 'POST'],
		['name' => 'employee_onboarding#generarChecklist',   'url' => '/generarChecklistEmpleado',  'verb' => 'POST'],
		['name' => 'employee_onboarding#marcarStatus',       'url' => '/marcarStatusBoarding',      'verb' => 'POST'],
		['name' => 'employee_onboarding#deleteById',         'url' => '/deleteBoardingEmpleado',    'verb' => 'POST'],
		['name' => 'employee_onboarding#deleteByEmpleado',   'url' => '/deleteBoardingByEmpleado',  'verb' => 'POST'],

		/************************** REPORTE DE TIEMPOS ************************************/
		['name' => 'time_reports#crearReporte', 'url' => '/crearReporte', 'verb' => 'POST'],
		['name' => 'time_reports#GetReportesAll', 'url' => '/GetReportesAll', 'verb' => 'GET'],
		['name' => 'time_reports#findById', 'url' => '/GetReportesById', 'verb' => 'POST'],

		['name' => 'time_reports#modificarReporte', 'url' => '/modificarReporte', 'verb' => 'POST'],
		['name' => 'time_reports#deleteReport', 'url' => '/deleteReport', 'verb' => 'POST'],

		['name' => 'time_reports#GetEmpleadosReports', 'url' => '/GetEmpleadosReports', 'verb' => 'POST'],
		['name' => 'time_reports#ExportarReportes', 'url' => '/ExportarReportes', 'verb' => 'POST'],
		['name' => 'time_reports#GetAdminReportsSummary', 'url' => '/GetAdminReportsSummary', 'verb' => 'POST',],
		['name' => 'time_reports#GetCostosLideres', 'url' => '/GetCostosLideres', 'verb' => 'POST'],
		['name' => 'time_reports#GetCostosActivities', 'url' => '/GetCostosActivities', 'verb' => 'GET'],
		['name' => 'time_reports#GetCandidateCostss', 'url' => '/GetCandidateCostss', 'verb' => 'POST'],
		['name' => 'time_reports#estadoReporteHoy', 'url' => '/estadoReporteHoy', 'verb' => 'GET',],
		['name' => 'time_reports#GetReportComplianceHoy', 'url' => '/GetReportComplianceHoy', 'verb' => 'GET',],
		['name' => 'time_reports#EnviarRecordatoriosPendientesHoy', 'url' => '/EnviarRecordatoriosPendientesHoy', 'verb' => 'POST',],

		/************************** EJEMPLO ************************************/
		['name' => 'example#nuevafuncion', 'url' => '/ejemplo', 'verb' => 'POST'],

		/************************** INVENTARIO TI ************************************/

		// Modelos de equipo
		['name' => 'inventory#GetInventoryModelos', 'url' => '/GetInventoryModelos', 'verb' => 'GET'],
		['name' => 'inventory#GetInventoryModelo', 'url' => '/GetInventoryModelo', 'verb' => 'POST'],
		['name' => 'inventory#CrearInventoryModelo', 'url' => '/CrearInventoryModelo', 'verb' => 'POST'],
		['name' => 'inventory#ActualizarInventoryModelo', 'url' => '/ActualizarInventoryModelo', 'verb' => 'POST'],
		['name' => 'inventory#EliminarInventoryModelo', 'url' => '/EliminarInventoryModelo', 'verb' => 'POST'],

		// Teams de cómputo
		['name' => 'inventory#GetInventoryComputo', 'url' => '/GetInventoryComputo', 'verb' => 'GET'],
		['name' => 'inventory#GetInventoryEquipo', 'url' => '/GetInventoryEquipo', 'verb' => 'POST'],
		['name' => 'inventory#GetInventoryEmpleado', 'url' => '/GetInventoryEmpleado', 'verb' => 'POST'],
		['name' => 'inventory#CrearInventoryEquipo', 'url' => '/CrearInventoryEquipo', 'verb' => 'POST'],
		['name' => 'inventory#ActualizarInventoryEquipo', 'url' => '/ActualizarInventoryEquipo', 'verb' => 'POST'],
		['name' => 'inventory#EliminarInventoryEquipo', 'url' => '/EliminarInventoryEquipo', 'verb' => 'POST'],
		['name' => 'inventory#GetInventoryTeamsSelect', 'url' => '/GetInventoryTeamsSelect', 'verb' => 'GET'],
		['name' => 'inventory#GetInventoryHistory', 'url' => '/inventario/Team/{id_team}/historial', 'verb' => 'GET'],
		['name' => 'inventory#CrearInventoryNota', 'url' => '/inventario/Team/{id_team}/historial/notes', 'verb' => 'POST'],
		['name' => 'inventory#GetTeamsEmpleado', 'url' => '/inventario/employees/{id_employee}/Team', 'verb' => 'GET'],
		['name' => 'inventory#AsignarEquipoEmpleado', 'url' => '/inventario/Team/{id_team}/asignar', 'verb' => 'POST'],
		['name' => 'inventory#DesasignarEquipoEmpleado', 'url' => '/inventario/Team/{id_team}/asignacion', 'verb' => 'DELETE'],
		['name' => 'inventory#SincronizarTeamsEmpleado', 'url' => '/inventario/employees/{id_employee}/Team', 'verb' => 'PUT'],
		// History de soporte
		['name' => 'inventory#GetSoporteEquipo', 'url' => '/GetSoporteEquipo', 'verb' => 'POST'],
		['name' => 'inventory#CrearSoporteEquipo', 'url' => '/CrearSoporteEquipo', 'verb' => 'POST'],
		['name' => 'inventory#ActualizarSoporteEquipo', 'url' => '/ActualizarSoporteEquipo', 'verb' => 'POST'],
		['name' => 'inventory#EliminarSoporteEquipo', 'url' => '/EliminarSoporteEquipo', 'verb' => 'POST'],

		// Calendario de mantenimientos de inventario
		['name' => 'maintenance#technicians', 'url' => '/inventario/mantenimientos/tecnicos', 'verb' => 'GET'],
		['name' => 'maintenance#groups', 'url' => '/inventario/mantenimientos/grupos', 'verb' => 'GET'],
		['name' => 'maintenance#createGroup', 'url' => '/inventario/mantenimientos/grupos', 'verb' => 'POST'],
		['name' => 'maintenance#groupMaintenances', 'url' => '/inventario/mantenimientos/grupos/{id}/Team', 'verb' => 'GET'],
		['name' => 'maintenance#cancelGroup', 'url' => '/inventario/mantenimientos/grupos/{id}/cancelar', 'verb' => 'POST'],
		['name' => 'maintenance#assignGroupTechnician', 'url' => '/inventario/mantenimientos/grupos/{id}/tecnico', 'verb' => 'POST'],
		['name' => 'maintenance#group', 'url' => '/inventario/mantenimientos/grupos/{id}', 'verb' => 'GET'],
		['name' => 'maintenance#eligibleEquipment', 'url' => '/inventario/mantenimientos/Department/{id}/Team', 'verb' => 'GET'],
		['name' => 'maintenance#overdue', 'url' => '/inventario/mantenimientos/atrasados', 'verb' => 'GET'],
		['name' => 'maintenance#duplicates', 'url' => '/inventario/mantenimientos/duplicados', 'verb' => 'GET'],
		['name' => 'maintenance#equipmentHistory', 'url' => '/inventario/mantenimientos/Team/{id}/historial', 'verb' => 'GET'],
		['name' => 'maintenance#start', 'url' => '/inventario/mantenimientos/{id}/iniciar', 'verb' => 'POST'],
		['name' => 'maintenance#schedule', 'url' => '/inventario/mantenimientos/{id}/programar', 'verb' => 'POST'],
		['name' => 'maintenance#complete', 'url' => '/inventario/mantenimientos/{id}/completar', 'verb' => 'POST'],
		['name' => 'maintenance#reschedule', 'url' => '/inventario/mantenimientos/{id}/reprogramar', 'verb' => 'POST'],
		['name' => 'maintenance#cancel', 'url' => '/inventario/mantenimientos/{id}/cancelar', 'verb' => 'POST'],
		['name' => 'maintenance#notApplicable', 'url' => '/inventario/mantenimientos/{id}/no-aplica', 'verb' => 'POST'],
		['name' => 'maintenance#assignTechnician', 'url' => '/inventario/mantenimientos/{id}/tecnico', 'verb' => 'POST'],
		['name' => 'maintenance#updateChecklist', 'url' => '/inventario/mantenimientos/{id}/checklist', 'verb' => 'PATCH'],
		['name' => 'maintenance#updateWork', 'url' => '/inventario/mantenimientos/{id}/trabajo', 'verb' => 'PATCH'],
		['name' => 'maintenance#maintenance', 'url' => '/inventario/mantenimientos/{id}', 'verb' => 'GET'],

		/************************** COMPRAS ************************************/
		['name' => 'purchase_request#index','url' => '/purchases/solicitudes','verb' => 'GET'],
		['name' => 'purchase_request#show','url' => '/purchases/solicitudes/{id}','verb' => 'GET'],
		['name' => 'purchase_request#history','url' => '/purchases/solicitudes/{id}/historial','verb' => 'GET'],
		['name' => 'purchase_request#flow','url' => '/purchases/solicitudes/{id}/flujo','verb' => 'GET'],
		['name' => 'purchase_request#create','url' => '/purchases/solicitudes','verb' => 'POST'],
		['name' => 'purchase_request#update','url' => '/purchases/solicitudes/{id}','verb' => 'PUT'],
		['name' => 'purchase_request#sendToApproval','url' => '/purchases/solicitudes/{id}/enviar-autorizacion','verb' => 'POST'],
		['name' => 'purchase_request#approve','url' => '/purchases/solicitudes/{id}/autorizar','verb' => 'POST'],
		['name' => 'purchase_request#reject','url' => '/purchases/solicitudes/{id}/rechazar','verb' => 'POST'],
		['name' => 'purchase_document#document','url' => '/purchases/solicitudes/{id}/document','verb' => 'GET',],
		['name' => 'purchase_request#cancel', 'url' => '/purchases/solicitudes/{id}/cancelar', 'verb' => 'POST'],
		['name' => 'purchase_request#update','url' => '/purchases/solicitudes/{id}','verb' => 'PUT'],
		['name' => 'purchase_request#context', 'url' => '/purchases/contexto', 'verb' => 'GET'],
		['name' => 'purchase_document#document','url' => '/purchases/solicitudes/{id}/document','verb' => 'GET'],
		['name' => 'purchase_document#guardarDocumento','url' => '/purchases/solicitudes/{id}/document/guardar','verb' => 'POST'],
		['name' => 'purchase_document#subirFirmado','url' => '/purchases/solicitudes/{id}/document/firmado','verb' => 'POST'],
		['name' => 'purchase_document#verFirmado','url' => '/purchases/solicitudes/{id}/document/firmado','verb' => 'GET'],
		['name' => 'purchase_logo#upload','url' => '/purchases/settings/logo','verb' => 'POST'],
		['name' => 'purchase_logo#show','url' => '/purchases/settings/logo','verb' => 'GET'],
		['name' => 'purchase_logo#delete','url' => '/purchases/settings/logo','verb' => 'DELETE'],
		['name' => 'settings#uploadCompraDocumentoLogo','url' => '/purchases/settings/logo','verb' => 'POST'],
		['name' => 'settings#getCompraDocumentoLogo','url' => '/purchases/settings/logo','verb' => 'GET'],
		['name' => 'settings#deleteCompraDocumentoLogo','url' => '/purchases/settings/logo','verb' => 'DELETE'],
	],
];
