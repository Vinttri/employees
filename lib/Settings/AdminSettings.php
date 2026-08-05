<?php
declare(strict_types=1);

namespace OCA\Employees\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Service\ConfigRepairer;

class AdminSettings implements ISettings {
    private IL10N $l;
    private IConfig $config;
    private SettingsMapper $SettingsMapper;
    private ConfigRepairer $repairer;

    public function __construct(
        IConfig $config,
        IL10N $l,
        SettingsMapper $SettingsMapper,
        ConfigRepairer $repairer
    ) {
        $this->config = $config;
        $this->l = $l;
        $this->SettingsMapper = $SettingsMapper;
        $this->repairer = $repairer;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse {
        $rows = $this->SettingsMapper->GetConfig();
        $params = is_array($rows) ? array_column($rows, 'data', 'name') : [];

        // Verifica si faltan claves, si sí → ejecuta reparación
        $requiredKeys = [
            'usuario_almacenamiento',
            'automatic_save_note',
            'acumular_vacaciones',
            'modulo_savings',
            'modulo_ausencias',
            'ausencias_readonly',
            'modulo_clients',
            'modulo_reporte_tiempos',
            'modulo_inventario',
            'modulo_soporte',
            'modulo_purchases',
        ];
        $missing = array_diff($requiredKeys, array_keys($params));

        if (!empty($missing)) {
            $this->repairer->run();
            // Recarga la configuración tras reparar
            $rows = $this->SettingsMapper->GetConfig();
            $params = is_array($rows) ? array_column($rows, 'data', 'name') : [];
        }

        return new TemplateResponse('employees', 'settings/admin', [
            'config' => $params,
        ], '');
    }

    public function getSection(): string {
        return 'employees'; // name de la sección creada
    }

    public function getPriority(): int {
        return 1;
    }
}
