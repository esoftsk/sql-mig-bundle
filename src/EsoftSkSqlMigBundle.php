<?php
declare(strict_types=1);

namespace EsoftSk\SqlMigBundle;

use EsoftSk\SqlMigBundle\DependencyInjection\SqlMigBundleExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class EsoftSkSqlMigBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new SqlMigBundleExtension();
    }
}
