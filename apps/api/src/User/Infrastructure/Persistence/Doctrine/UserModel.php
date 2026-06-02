<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uq_users_email', columns: ['email'])]
final class UserModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    public string $id;

    #[ORM\Column(type: 'string', length: 180)]
    public string $email;

    #[ORM\Column(type: 'string', length: 255)]
    public string $password;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    public array $roles = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'must_reset_password', type: 'boolean')]
    public bool $mustResetPassword = false;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $deletedAt = null;
}
