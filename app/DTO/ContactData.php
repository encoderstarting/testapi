<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ContactData
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $comment,
    ) {}

    /**
     * @param  array{name: string, phone: string, email: string, comment: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            phone: $data['phone'],
            email: $data['email'],
            comment: $data['comment'],
        );
    }
}
