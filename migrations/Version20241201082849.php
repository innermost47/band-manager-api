<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241201082849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audio_file_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE audio_file ADD audio_file_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE audio_file ADD CONSTRAINT FK_C32E2A4C37E82AD3 FOREIGN KEY (audio_file_type_id) REFERENCES audio_file_type (id)');
        $this->addSql('CREATE INDEX IDX_C32E2A4C37E82AD3 ON audio_file (audio_file_type_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audio_file DROP FOREIGN KEY FK_C32E2A4C37E82AD3');
        $this->addSql('DROP TABLE audio_file_type');
        $this->addSql('DROP INDEX IDX_C32E2A4C37E82AD3 ON audio_file');
        $this->addSql('ALTER TABLE audio_file DROP audio_file_type_id');
    }
}
