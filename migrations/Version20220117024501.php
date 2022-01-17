<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220117024501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            create table users (
                id int(255) auto_increment not null,
                name varchar(50) not null,
                surname varchar(150) null,
                email varchar(255) not null,
                password varchar(255) not null,
                role varchar(20) null,
                created_at datetime not null,
                updated_at datetime not null,
                constraint pk_users primary key (id)
            ) ENGINE=InnoDB;
        ');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('users');
    }
}
