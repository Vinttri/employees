<?php

namespace OCA\Employees\Activity;

use OCP\Activity\IExtension;

class ActivityExtension implements IExtension {

    public function getNotificationTypes(string $language): array {
        return [
            [
                'id' => 'employees',
                'desc' => 'Notificaciones del módulo Empleados',
            ],
        ];
    }

    public function getDefaultTypes(): array {
        // Indica que por defecto las notificaciones de este type deben enviarse por email
        return ['stream', 'email'];
    }
}
