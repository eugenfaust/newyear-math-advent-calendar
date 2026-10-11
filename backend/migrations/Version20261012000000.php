<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012000000 extends AbstractMigration
{
    public function getDescription(): string { return 'Make the calendar tree slot uniqueness explicitly partial'; }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_ornament_calendar_slot');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_ornament_calendar_slot ON user_ornament (calendar_id, tree_slot) WHERE tree_slot IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_ornament_calendar_slot');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_ornament_calendar_slot ON user_ornament (calendar_id, tree_slot)');
    }
}
