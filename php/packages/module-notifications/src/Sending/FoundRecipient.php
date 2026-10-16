<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Sending;

/** One chat a bot can write to, as "Find chats" lists it (§6.3). */
final readonly class FoundRecipient
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public ?string $username = null,
    ) {}

    /**
     * @return array{id: string, type: string, title: string, username: string|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'title' => $this->title, 'username' => $this->username];
    }
}
