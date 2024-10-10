<?php

namespace OCA\Majordomo\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Psr\Log\LoggerInterface;

class Version020100ResendOnly extends SimpleMigrationStep {

    private IDBConnection $connection;
    private LoggerInterface $logger;
    private string $AppName;

    public function __construct(IDBConnection $connection, LoggerInterface $logger, $AppName) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->AppName = $AppName;
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable("majordomo_lists");
        $table->modifyColumn("listname", [
            "notnull" => false,
        ]);
        $table->modifyColumn("password", [
            "notnull" => false,
        ]);
        $table->modifyColumn("manager", [
            "notnull" => false,
        ]);

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
    }


}