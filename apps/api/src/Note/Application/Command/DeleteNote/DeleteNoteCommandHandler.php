<?php

declare(strict_types=1);

namespace App\Note\Application\Command\DeleteNote;

use App\Note\Domain\Exception\NoteNotFound;
use App\Note\Domain\NoteId;
use App\Note\Domain\NoteRepository;
use App\Note\Domain\OwnerId;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;
use Jperdior\SharedKernel\Domain\Bus\Event\EventBus;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final readonly class DeleteNoteCommandHandler implements CommandHandler
{
    public function __construct(
        private NoteRepository $notes,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DeleteNoteCommand $command): void
    {
        $note = $this->notes->findById(NoteId::fromString($command->id))
            ?? throw NoteNotFound::withId($command->id);

        $note->delete(OwnerId::fromString($command->editorId), $this->clock->now());

        $events = $note->pullDomainEvents();
        $this->notes->remove($note);
        $this->eventBus->publish(...$events);
    }
}
