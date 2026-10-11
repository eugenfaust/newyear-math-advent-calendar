<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Enable checked answers and multiple-choice task options'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD choices JSON DEFAULT NULL');
        $this->addSql("UPDATE task SET answer_type = CASE WHEN btrim(correct_answer) ~ '^[+-]?[0-9]+([.,][0-9]+)?$' THEN 'NUMBER' ELSE 'TEXT' END WHERE answer_type = 'NONE' AND correct_answer IS NOT NULL AND btrim(correct_answer) <> ''");
        $this->addSql("ALTER TABLE task ADD CONSTRAINT chk_task_answer_config CHECK (answer_type = 'NONE' OR (answer_type IN ('NUMBER', 'TEXT', 'MULTIPLE_CHOICE') AND correct_answer IS NOT NULL AND btrim(correct_answer) <> ''))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP CONSTRAINT chk_task_answer_config');
        $this->addSql("UPDATE task SET answer_type = 'NONE' WHERE answer_type IN ('NUMBER', 'TEXT', 'MULTIPLE_CHOICE')");
        $this->addSql('ALTER TABLE task DROP choices');
    }
}
