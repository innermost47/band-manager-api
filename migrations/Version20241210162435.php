<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241210162435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_exception (id INT AUTO_INCREMENT NOT NULL, parent_event_id INT DEFAULT NULL, exception_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_cancelled TINYINT(1) NOT NULL, reason VARCHAR(255) NOT NULL, rescheduled_start DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', rescheduled_end DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', alternate_location VARCHAR(255) DEFAULT NULL, INDEX IDX_F95CB050EE3A445A (parent_event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_exception ADD CONSTRAINT FK_F95CB050EE3A445A FOREIGN KEY (parent_event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event ADD project_id INT DEFAULT NULL, ADD recurrence_type VARCHAR(20) DEFAULT NULL, ADD recurrence_interval INT DEFAULT NULL, ADD recurrence_days JSON DEFAULT NULL, ADD recurrence_months JSON DEFAULT NULL, ADD recurrence_end DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP recurrence');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7166D1F9C ON event (project_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_exception DROP FOREIGN KEY FK_F95CB050EE3A445A');
        $this->addSql('DROP TABLE event_exception');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7166D1F9C');
        $this->addSql('DROP INDEX IDX_3BAE0AA7166D1F9C ON event');
        $this->addSql('ALTER TABLE event ADD recurrence VARCHAR(255) DEFAULT NULL, DROP project_id, DROP recurrence_type, DROP recurrence_interval, DROP recurrence_days, DROP recurrence_months, DROP recurrence_end');
    }
}
