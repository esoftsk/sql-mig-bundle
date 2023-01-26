<?php

declare(strict_types=1);

namespace EsoftSk\SqlMigBundle\Service;

use DateTimeImmutable;
use EsoftSk\SqlMigBundle\Dto\Migration;
use EsoftSk\SqlMigBundle\Dto\MigrationResult;

final class MigrationBuilder
{
    /**
     * @param array{
     *     id: int,
     *     created_at: string,
     *     version: int,
     *     migration_script: string,
     *     migration_content: string
     * } $queryResult
     *
     * @return \EsoftSk\SqlMigBundle\Dto\Migration
     * @throws \Exception
     */
    public function buildFromQueryResult(array $queryResult): Migration
    {
        $migration = new Migration();
        $migration->versionId = $queryResult['id'];
        $migration->appliedAt = new DateTimeImmutable($queryResult['created_at']);
        $migration->number = $queryResult['version'];
        $migration->scriptPath = $queryResult['migration_script'];
        $migration->migrationScript = $queryResult['migration_content'];

        return $migration;
    }

    public function buildFromScriptPath(string $scriptPath): Migration
    {
        $migration = new Migration();
        $migration->scriptPath = $scriptPath;
        $migration->migrationScript = file_get_contents($scriptPath) ?: '';
        $migration->number = intval(basename($scriptPath));

        return $migration;
    }

    /**
     * @param string $result
     * @param \EsoftSk\SqlMigBundle\Dto\Migration $migration
     * @param \Exception[] $exceptions
     * @return \EsoftSk\SqlMigBundle\Dto\MigrationResult
     */
    public function buildResult(string $result, Migration $migration, array $exceptions = []): MigrationResult
    {
        $migrationResult = new MigrationResult();
        $migrationResult->result = $result;
        $migrationResult->migration = $migration;
        $migrationResult->exceptions = $exceptions;

        return $migrationResult;
    }
}
