<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241129231437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audio_file (id INT AUTO_INCREMENT NOT NULL, song_id_id INT DEFAULT NULL, filename VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C32E2A4CB2E00B12 (song_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lyrics (id INT AUTO_INCREMENT NOT NULL, song_id_id INT DEFAULT NULL, content LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3BDA6C66B2E00B12 (song_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE song (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tablature (id INT AUTO_INCREMENT NOT NULL, song_id_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6FCF991B2E00B12 (song_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE audio_file ADD CONSTRAINT FK_C32E2A4CB2E00B12 FOREIGN KEY (song_id_id) REFERENCES song (id)');
        $this->addSql('ALTER TABLE lyrics ADD CONSTRAINT FK_3BDA6C66B2E00B12 FOREIGN KEY (song_id_id) REFERENCES song (id)');
        $this->addSql('ALTER TABLE tablature ADD CONSTRAINT FK_6FCF991B2E00B12 FOREIGN KEY (song_id_id) REFERENCES song (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audio_file DROP FOREIGN KEY FK_C32E2A4CB2E00B12');
        $this->addSql('ALTER TABLE lyrics DROP FOREIGN KEY FK_3BDA6C66B2E00B12');
        $this->addSql('ALTER TABLE tablature DROP FOREIGN KEY FK_6FCF991B2E00B12');
        $this->addSql('DROP TABLE audio_file');
        $this->addSql('DROP TABLE lyrics');
        $this->addSql('DROP TABLE song');
        $this->addSql('DROP TABLE tablature');
    }
}
