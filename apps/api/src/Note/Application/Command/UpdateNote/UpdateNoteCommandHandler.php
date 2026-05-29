<?php

declare(strict_types=1);

namespace App\Note\Application\Command\UpdateNote;

use App\Note\Domain\Exception\NoteNotFound;
use App\Note\Domain\NoteBody;
use App\Note\Domain\NoteId;
use App\Note\Domain\NoteRepository;
use App\Note\Domain\NoteTitle;
use App\Note\Domain\OwnerId;
use Jperdior\SharedKernel\Domain\Bus\Command\CommandHandler;
use Jperdior\SharedKernel\Domain\Bus\Event\EventBus;
use Jperdior\SharedKernel\Domain\Clock\ClockInterface;

final readonly class UpdateNoteCommandHandler implements CommandHandler
{
    public function __construct(
        private NoteRepository $notes,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateNoteCommand $command): void
    {
        $note = $this->notes->findById(NoteId::fromString($command->id))
            ?? throw NoteNotFound::withId($command->id);

        $note->update(
            OwnerId::fromString($command->editorId),
            new NoteTitle($command->title),
            new NoteBody($command->body),
            $this->clock->now(),
        );

        $this->notes->save($note);
        $this->eventBus->publish(...$note->pullDomainEvents());
    }
}
