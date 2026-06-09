<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password_recovery_tokens with partial unique index on (user_id) where used_at IS NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_recovery_tokens (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_password_recovery_tokens_token_hash ON password_recovery_tokens (token_hash)');
        // Partial unique index: at most one active token per user. Enforces BR-U04 atomically under concurrency.
        $this->addSql('CREATE UNIQUE INDEX uq_password_recovery_tokens_active_per_user ON password_recovery_tokens (user_id) WHERE used_at IS NULL');
        $this->addSql('ALTER TABLE password_recovery_tokens ADD CONSTRAINT fk_password_recovery_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_recovery_tokens');
    }
}
