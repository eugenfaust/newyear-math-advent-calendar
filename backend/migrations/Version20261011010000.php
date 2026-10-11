<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and hashed bearer tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE app_user (uuid UUID NOT NULL, login VARCHAR(100) NOT NULL, email VARCHAR(180) DEFAULT NULL, name VARCHAR(150) NOT NULL, password_hash VARCHAR(255) NOT NULL, roles JSON NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(uuid))");
        $this->addSql('CREATE UNIQUE INDEX uniq_user_login ON app_user (login)');
        $this->addSql('CREATE TABLE access_token (token_hash VARCHAR(64) NOT NULL, user_uuid UUID NOT NULL, issued_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(token_hash))');
        $this->addSql('CREATE INDEX idx_access_token_user ON access_token (user_uuid)');
        $this->addSql('CREATE INDEX idx_access_token_expires ON access_token (expires_at)');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT fk_access_token_user FOREIGN KEY (user_uuid) REFERENCES app_user (uuid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE app_user');
    }
}
