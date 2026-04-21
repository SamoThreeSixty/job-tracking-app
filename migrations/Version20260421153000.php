<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the time block table for the background tracker.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE time_block (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, ticket VARCHAR(64) NOT NULL, job_number VARCHAR(64) NOT NULL, description VARCHAR(255) NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, created_at DATETIME NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE time_block');
    }
}
