<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011060000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create awarded ornaments with unique day and tree slot constraints'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_ornament (uuid UUID NOT NULL, calendar_id UUID NOT NULL, day_progress_id INT NOT NULL, ornament_id UUID NOT NULL, tree_slot SMALLINT DEFAULT NULL, received_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, placed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, CONSTRAINT chk_user_ornament_slot CHECK (tree_slot IS NULL OR tree_slot BETWEEN 1 AND 31), PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_ornament_day ON user_ornament (day_progress_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_ornament_calendar_slot ON user_ornament (calendar_id, tree_slot)');
        $this->addSql('CREATE INDEX idx_user_ornament_calendar ON user_ornament (calendar_id)');
        $this->addSql('CREATE INDEX idx_user_ornament_ornament ON user_ornament (ornament_id)');
        $this->addSql('ALTER TABLE user_ornament ADD CONSTRAINT fk_user_ornament_calendar FOREIGN KEY (calendar_id) REFERENCES advent_calendar (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_ornament ADD CONSTRAINT fk_user_ornament_day FOREIGN KEY (day_progress_id) REFERENCES advent_day_progress (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_ornament ADD CONSTRAINT fk_user_ornament_ornament FOREIGN KEY (ornament_id) REFERENCES ornament (uuid) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_ornament');
    }
}
