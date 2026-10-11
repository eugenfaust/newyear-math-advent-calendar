<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011030000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create ornament library'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ornament (uuid UUID NOT NULL, name VARCHAR(150) NOT NULL, image_path VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, CONSTRAINT chk_ornament_active_image CHECK (NOT is_active OR image_path IS NOT NULL), PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX idx_ornament_active_created ON ornament (is_active, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ornament');
    }
}
