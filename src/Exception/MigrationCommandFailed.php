<?php

declare(strict_types=1);

namespace EsoftSk\SqlMigBundle\Exception;

use EsoftSk\SqlMigBundle\Dto\Migration;
use LogicException;

final class MigrationCommandFailed extends LogicException
{
    private Migration $migration;

    /**
     * @var string[]
     */
    private array $commandOutput;

    /**
     * @param string[] $commandOutput
     */
    public function __construct(Migration $migration, array $commandOutput)
    {
        $message = "Migration script {$migration->scriptPath} failed to execute";
        $this->migration = $migration;
        $this->commandOutput = $commandOutput;

        parent::__construct($message);
    }

    public function getMigration(): Migration
    {
        return $this->migration;
    }

    /**
     * @return string[]
     */
    public function getCommandOutput(): array
    {
        return $this->commandOutput;
    }
}
