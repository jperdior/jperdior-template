<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users + refresh_tokens tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id          CHAR(36)     NOT NULL,
                email       VARCHAR(180) NOT NULL,
                password    VARCHAR(255) NOT NULL,
                roles       JSONB        NOT NULL DEFAULT '[]'::jsonb,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            );
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_users_email ON users (email);');

        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
                id           SERIAL       NOT NULL,
                refresh_token VARCHAR(128) NOT NULL,
                username      VARCHAR(255) NOT NULL,
                valid         TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            );
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_refresh_tokens_token ON refresh_tokens (refresh_token);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS refresh_tokens;');
        $this->addSql('DROP TABLE IF EXISTS users;');
    }
}
