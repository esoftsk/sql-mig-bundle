<?php

declare(strict_types=1);

namespace EsoftSk\SqlMigBundle\Exception;

use EsoftSk\SqlMigBundle\Dto\Migration;
use LogicException;

class MissingTransactionControl extends LogicException
{
    private Migration $migration;

    public function __construct(Migration $migration)
    {
        $message = "Migration script {$migration->scriptPath} is missing transaction control";
        $this->migration = $migration;

        parent::__construct($message);
    }

    public function getMigration(): Migration
    {
        return $this->migration;
    }
}
