<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add saved_ticket table for imported support tickets';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE saved_ticket (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, ticket VARCHAR(64) NOT NULL, job_number VARCHAR(64) NOT NULL, description VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_saved_ticket_ticket ON saved_ticket (ticket)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE saved_ticket');
    }
}
