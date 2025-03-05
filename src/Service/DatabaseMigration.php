<?php

declare(strict_types=1);

namespace EsoftSk\SqlMigBundle\Service;

use EsoftSk\SqlMigBundle\Dto\Migration;
use EsoftSk\SqlMigBundle\Dto\MigrationResult;
use EsoftSk\SqlMigBundle\Exception\MigrationCommandFailed;
use EsoftSk\SqlMigBundle\Exception\MissingTransactionControl;
use Doctrine\ORM\EntityManagerInterface;
use http\Url;
use Symfony\Component\HttpKernel\KernelInterface;


final class DatabaseMigration
{
    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAILURE = 'failure';

    private KernelInterface $kernel;
    private EntityManagerInterface $entityManager;
    private MigrationBuilder $migrationBuilder;

    public function __construct(
        KernelInterface  $kernel, EntityManagerInterface $entityManager,
        MigrationBuilder $migrationBuilder
    )
    {
        $this->kernel = $kernel;
        $this->entityManager = $entityManager;
        $this->migrationBuilder = $migrationBuilder;
    }

    /**
     * Create table form database versioning only if it does not exist.
     *
     * Schema will be created too if needed.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function createDatabaseVersionTableIfNeeded(): void
    {
        $connection = $this->entityManager->getConnection();

        $connection->executeQuery('create schema if not exists "Audit"');

        $createTable = <<<SQL
        create table if not exists "Audit"."DatabaseVersion" (
            id                integer not null generated always as identity,
            created_at        timestamp with time zone not null default now(),
            version           integer                     not null,
            migration_script  text                        not null,
            migration_content text                        not null,
            constraint "DatabaseVersion_pk" primary key (id),
            constraint version_uq unique (version)
        )
SQL;

        $connection->executeQuery($createTable);
    }

    /**
     * @return Migration[]
     */
    public function getAllMigrations(): array
    {
        $allMigrationScripts = glob($this->kernel->getProjectDir() . '/database/migrations/*.sql') ?: [];

        return array_map(
            fn(string $scriptPath) => $this->migrationBuilder->buildFromScriptPath($scriptPath),
            $allMigrationScripts
        );
    }

    /**
     * @return MigrationResult[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function runMigrations(): array
    {
        $migrations = $this->getMigrationsToApply();

        $results = [];

        foreach ($migrations as $migration) {
            try {
                $results[] = $this->runMigration($migration);
            } catch (MissingTransactionControl|MigrationCommandFailed $e) {
                $results[] = $this->migrationBuilder->buildResult(self::RESULT_FAILURE, $migration, [$e]);

                break;
            }
        }

        return $results;
    }

    /**
     * @return Migration[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function getMigrationsToApply(): array
    {
        $latestMigration = $this->findLatestMigration();
        $allMigrations = $this->getAllMigrations();

        if ($latestMigration) {
            $relevantMigrations = array_values(array_filter($allMigrations, function (Migration $migration) use ($latestMigration) {
                return $migration->number > $latestMigration->number;
            }));
        } else {
            $relevantMigrations = $allMigrations;
        }

        return $relevantMigrations;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \Exception
     */
    public function findLatestMigration(): ?Migration
    {
        $query = 'select * from "Audit"."DatabaseVersion" order by created_at desc, version desc';

        /**
         * @var array{
         *     id: int,
         *     created_at: string,
         *     version: int,
         *     migration_script: string,
         *     migration_content: string
         * }|null $latestMigrationResult
         */
        $latestMigrationResult = $this->entityManager
            ->getConnection()
            ->executeQuery($query)
            ->fetchAssociative();

        if ($latestMigrationResult) {
            $latestMigration = $this->migrationBuilder->buildFromQueryResult($latestMigrationResult);
        } else {
            $latestMigration = null;
        }

        return $latestMigration;
    }

    /**
     * @throws MissingTransactionControl
     */
    private function createMigrationScript(Migration $migration): string
    {
        $originalContent = $migration->migrationScript;

        if (!preg_match('/^begin;.*commit;$/is', $originalContent)) {
            throw new MissingTransactionControl($migration);
        }

        $openContent = preg_replace('/commit;$/', '', $originalContent);

        $migrationName = basename($migration->scriptPath);

        $content = <<<SQL
$openContent

insert into "Audit"."DatabaseVersion" (version, migration_script, migration_content) 
values ({$migration->number}, '{$migrationName}', $$|ORIGINAL_CONTENT_PLACEHOLDER|$$);

commit;
SQL;

        return str_replace('|ORIGINAL_CONTENT_PLACEHOLDER|', $originalContent, $content);
    }

    private function runMigration(Migration $migration): MigrationResult
    {
        $parsedUrl = parse_url($_ENV['DATABASE_URL']);

        $parsedUrl['scheme'] = 'postgresql';
        unset($parsedUrl['query']);

        $dbUrl = "{$parsedUrl['scheme']}://{$parsedUrl['user']}:{$parsedUrl['pass']}@{$parsedUrl['host']}:{$parsedUrl['port']}{$parsedUrl['path']}";

        $commandTpl = "psql --set ON_ERROR_STOP=1 $dbUrl < %s 2>&1";
        $migrationScript = $this->createMigrationScript($migration);

        $fh = tmpfile();

        if ($fh) {
            fwrite($fh, $migrationScript);

            $command = sprintf($commandTpl, stream_get_meta_data($fh)['uri']);
            $commandOutput = [];
            $commandResult = 0;

            ob_start();
            exec($command, $commandOutput, $commandResult);
            ob_end_clean();
            fclose($fh);

            if (0 === $commandResult) {
                return $this->migrationBuilder->buildResult(self::RESULT_SUCCESS, $migration);
            } else {
                throw new MigrationCommandFailed($migration, $commandOutput);
            }
        } else {
            throw new MigrationCommandFailed($migration, []);
        }
    }
}
