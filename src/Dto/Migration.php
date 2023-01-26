<?php

declare(strict_types=1);

namespace EsoftSk\SqlMigBundle\Dto;

use DateTimeImmutable;

final class Migration
{
    public ?int $versionId = null;
    public ?DateTimeImmutable $appliedAt = null;
    public string $scriptPath;
    public string $migrationScript;
    public int $number;
}
