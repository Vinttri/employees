<?php
declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version2020Date20260708193202 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $table = $schema->getTable('vacation_history');

        if (!$table->hasColumn('manually_assigned')) {
            $table->addColumn('manually_assigned', 'smallint', [
                'notnull' => true,
                'default' => 0,
            ]);
        }

        return $schema;
    }
}