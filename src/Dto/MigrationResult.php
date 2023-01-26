<?php

declare(strict_types=1);

namespace EsoftSk\SqlMigBundle\Dto;

use Exception;

final class MigrationResult
{
    public string $result;
    public Migration $migration;

    /**
     * @var Exception[]
     */
    public array $exceptions;
}
