<?php

declare(strict_types=1);

namespace App\Note\Application\Command\CreateNote;

use App\Note\Domain\Note;
use App\Note\Domain\NoteBody;
use App\Note\Domain\NoteId;
use App\Note\Domain\NoteRepository;
use App\Note\Domain\NoteTitle;
use App\Note\Domain\OwnerId;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;
use Jperdior\SharedKernel\Domain\Bus\Event\EventBus;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final readonly class CreateNoteCommandHandler implements CommandHandler
{
    public function __construct(
        private NoteRepository $notes,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateNoteCommand $command): void
    {
        $note = Note::create(
            id: NoteId::fromString($command->id),
            ownerId: OwnerId::fromString($command->ownerId),
            title: new NoteTitle($command->title),
            body: new NoteBody($command->body),
            now: $this->clock->now(),
        );

        $this->notes->save($note);
        $this->eventBus->publish(...$note->pullDomainEvents());
    }
}
