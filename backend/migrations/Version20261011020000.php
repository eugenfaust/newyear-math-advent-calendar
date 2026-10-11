<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create task library'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task (uuid UUID NOT NULL, title VARCHAR(150) NOT NULL, content TEXT NOT NULL, image_path VARCHAR(255) DEFAULT NULL, answer_type VARCHAR(255) NOT NULL, correct_answer TEXT DEFAULT NULL, solution TEXT DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX idx_task_active_created ON task (is_active, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task');
    }
}
