<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241204162115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD email_public TINYINT(1) DEFAULT NULL, ADD address_public TINYINT(1) DEFAULT NULL, ADD phone_public TINYINT(1) DEFAULT NULL, ADD sacem_number_public TINYINT(1) DEFAULT NULL, ADD bio LONGTEXT DEFAULT NULL, ADD bio_public TINYINT(1) DEFAULT NULL, ADD roles_public TINYINT(1) DEFAULT NULL, ADD projects_public TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP email_public, DROP address_public, DROP phone_public, DROP sacem_number_public, DROP bio, DROP bio_public, DROP roles_public, DROP projects_public');
    }
}
