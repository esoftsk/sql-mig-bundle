<?php

declare(strict_types=1);

namespace EsoftSk\SqlMigBundle\Command;

use EsoftSk\SqlMigBundle\Dto\MigrationResult;
use EsoftSk\SqlMigBundle\Exception\MigrationCommandFailed;
use EsoftSk\SqlMigBundle\Exception\MissingTransactionControl;
use EsoftSk\SqlMigBundle\Service\DatabaseMigration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Functional\filter;
use function Functional\map;
use function Functional\some;

final class ApplyCommand extends Command
{
    private DatabaseMigration $migrationsService;

    public function __construct(DatabaseMigration $migrationsService)
    {
        $this->migrationsService = $migrationsService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('sql-mig:apply')
            ->setDescription('Apply available database migrations');
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->migrationsService->createDatabaseVersionTableIfNeeded();

        $results = $this->migrationsService->runMigrations();
        $hasFailedMigrations = some($results, fn(MigrationResult $r) => DatabaseMigration::RESULT_FAILURE === $r->result);

        $this->printResults($output, $results);

        return $hasFailedMigrations ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param MigrationResult[] $results
     */
    private function printResults(OutputInterface $output, array $results): void
    {
        $this->printResultTable($output, $results);

        $hasErrors = some($results, fn(MigrationResult $r) => DatabaseMigration::RESULT_FAILURE === $r->result);

        if ($hasErrors) {
            $this->printErrorTable($output, $results);
        }
    }

    /**
     * @param MigrationResult[] $results
     */
    private function printResultTable(OutputInterface $output, array $results): void
    {
        $colorizeSuccess = fn(string $s) => "<fg=green>$s</>";
        $colorizeError = fn(string $s) => "<fg=red>$s</>";

        (new Table($output))
            ->setHeaders(['Migrácia', 'Výsledok'])
            ->setRows(map($results, function (MigrationResult $r) use ($colorizeSuccess, $colorizeError) {
                $migrationLabel = basename($r->migration->scriptPath);
                $resultLabel = DatabaseMigration::RESULT_FAILURE === $r->result
                    ? $colorizeError('CHYBA') : $colorizeSuccess('OK');

                return [
                    $migrationLabel,
                    $resultLabel,
                ];
            }))
            ->render();
    }

    /**
     * @param MigrationResult[] $results
     */
    private function printErrorTable(OutputInterface $output, array $results): void
    {
        $exceptions = [];

        foreach ($results as $result) {
            $exceptions = array_merge($exceptions, $result->exceptions);
        }

        $filterErrors = fn(string $s) => str_contains(strtolower($s), 'error');

        $colorizeError = fn(string $s) => "<fg=red>$s</>";

        (new Table($output))
            ->setRows(map($exceptions, function (MissingTransactionControl|MigrationCommandFailed $e) use ($filterErrors, $colorizeError) {
                if (method_exists($e, 'getCommandOutput')) {
                    $commandOutput = $e->getCommandOutput();
                } else {
                    $commandOutput = [];
                }

                return [
                    join("\n", map(filter($commandOutput, $filterErrors), $colorizeError)),
                ];
            }))
            ->render();
    }
}
