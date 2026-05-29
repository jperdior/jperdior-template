<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notes table with owner_id (FK to users.id) and composite index.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notes (
                id          CHAR(36)     NOT NULL,
                owner_id    CHAR(36)     NOT NULL,
                title       VARCHAR(200) NOT NULL,
                body        TEXT         NOT NULL,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_notes_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE
            );
        SQL);
        $this->addSql('CREATE INDEX idx_notes_owner_created ON notes (owner_id, created_at);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS notes;');
    }
}
