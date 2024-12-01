<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241130134959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audio_file DROP FOREIGN KEY FK_C32E2A4CB2E00B12');
        $this->addSql('DROP INDEX IDX_C32E2A4CB2E00B12 ON audio_file');
        $this->addSql('ALTER TABLE audio_file CHANGE song_id_id song_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE audio_file ADD CONSTRAINT FK_C32E2A4CA0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id)');
        $this->addSql('CREATE INDEX IDX_C32E2A4CA0BDB2F3 ON audio_file (song_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audio_file DROP FOREIGN KEY FK_C32E2A4CA0BDB2F3');
        $this->addSql('DROP INDEX IDX_C32E2A4CA0BDB2F3 ON audio_file');
        $this->addSql('ALTER TABLE audio_file CHANGE song_id song_id_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE audio_file ADD CONSTRAINT FK_C32E2A4CB2E00B12 FOREIGN KEY (song_id_id) REFERENCES song (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_C32E2A4CB2E00B12 ON audio_file (song_id_id)');
    }
}
