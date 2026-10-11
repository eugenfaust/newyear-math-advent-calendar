<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow administrators to open all calendar days for test users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD is_test_user BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP is_test_user');
    }
}
