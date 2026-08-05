<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2014Date20260626191208 extends SimpleMigrationStep {

    public function changeSchema(
        IOutput $output,
        Closure $schemaClosure,
        array $options
    ): ?ISchemaWrapper {

        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('absence_types')) {
            return null;
        }

        $table = $schema->getTable('absence_types');

        if (!$table->hasColumn('billable')) {
            $table->addColumn('billable', Types::INTEGER, [
                'notnull' => false,
                'default' => 0,
                'length' => 1,
            ]);
        }

        return $schema;
    }
}