<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Baseline for the initial empty application schema';
    }

    public function up(Schema $schema): void
    {
        // Entity tables are introduced by their feature migrations.
    }

    public function down(Schema $schema): void
    {
    }
}
