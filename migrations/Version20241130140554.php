<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241130140554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lyrics DROP FOREIGN KEY FK_3BDA6C66B2E00B12');
        $this->addSql('DROP INDEX IDX_3BDA6C66B2E00B12 ON lyrics');
        $this->addSql('ALTER TABLE lyrics CHANGE song_id_id song_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lyrics ADD CONSTRAINT FK_3BDA6C66A0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id)');
        $this->addSql('CREATE INDEX IDX_3BDA6C66A0BDB2F3 ON lyrics (song_id)');
        $this->addSql('ALTER TABLE tablature DROP FOREIGN KEY FK_6FCF991B2E00B12');
        $this->addSql('DROP INDEX IDX_6FCF991B2E00B12 ON tablature');
        $this->addSql('ALTER TABLE tablature CHANGE song_id_id song_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tablature ADD CONSTRAINT FK_6FCF991A0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id)');
        $this->addSql('CREATE INDEX IDX_6FCF991A0BDB2F3 ON tablature (song_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lyrics DROP FOREIGN KEY FK_3BDA6C66A0BDB2F3');
        $this->addSql('DROP INDEX IDX_3BDA6C66A0BDB2F3 ON lyrics');
        $this->addSql('ALTER TABLE lyrics CHANGE song_id song_id_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lyrics ADD CONSTRAINT FK_3BDA6C66B2E00B12 FOREIGN KEY (song_id_id) REFERENCES song (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_3BDA6C66B2E00B12 ON lyrics (song_id_id)');
        $this->addSql('ALTER TABLE tablature DROP FOREIGN KEY FK_6FCF991A0BDB2F3');
        $this->addSql('DROP INDEX IDX_6FCF991A0BDB2F3 ON tablature');
        $this->addSql('ALTER TABLE tablature CHANGE song_id song_id_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tablature ADD CONSTRAINT FK_6FCF991B2E00B12 FOREIGN KEY (song_id_id) REFERENCES song (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_6FCF991B2E00B12 ON tablature (song_id_id)');
    }
}
