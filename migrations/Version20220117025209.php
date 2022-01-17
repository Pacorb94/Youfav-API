<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220117025209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            create table videos (
                id int(255) auto_increment not null,
                user_id int(255),
                title varchar(255) not null,
                description text null,
                url varchar(255) not null,
                status varchar(50) null,
                created_at datetime not null,
                updated_at datetime not null,
                constraint pk_videos primary key (id),
                constraint fk_videos_users foreign key (user_id) references users(id) on delete cascade
            ) ENGINE=InnoDB;
        ');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('videos');
    }
}
