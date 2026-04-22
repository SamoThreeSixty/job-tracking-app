<?php

namespace App\Controller;

use App\Contracts\TicketSourceParserInterface;
use App\Entity\SavedTicket;
use App\Entity\TimeBlock;
use App\Repository\SavedTicketRepository;
use App\Repository\TimeBlockRepository;
use App\Service\QuarterHourRounder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TimeTrackerController extends AbstractController
{
    #[Route('/', name: 'app_tracker_dashboard', methods: ['GET'])]
    public function index(TimeBlockRepository $timeBlockRepository, SavedTicketRepository $savedTicketRepository): Response
    {
        return $this->renderTodayPage($timeBlockRepository, $savedTicketRepository);
    }

    #[Route('/history', name: 'app_tracker_history', methods: ['GET'])]
    public function history(TimeBlockRepository $timeBlockRepository, SavedTicketRepository $savedTicketRepository): Response
    {
        $today = new \DateTimeImmutable('today');
        $historyDays = $this->buildHistoryDays($timeBlockRepository, $today);

        return $this->renderHistoryPage(
            $timeBlockRepository,
            $savedTicketRepository,
            $today,
            $historyDays,
            null,
            []
        );
    }

    #[Route('/history/{date}', name: 'app_tracker_history_day', methods: ['GET'], requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function historyDay(string $date, TimeBlockRepository $timeBlockRepository, SavedTicketRepository $savedTicketRepository): Response
    {
        $today = new \DateTimeImmutable('today');
        $selectedDay = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        if (!$selectedDay instanceof \DateTimeImmutable) {
            throw $this->createNotFoundException('The requested history date could not be read.');
        }

        $historyDays = $this->buildHistoryDays($timeBlockRepository, $today);

        if (!isset($historyDays[$selectedDay->format('Y-m-d')])) {
            throw $this->createNotFoundException('No entries were found for that day.');
        }

        return $this->renderHistoryPage(
            $timeBlockRepository,
            $savedTicketRepository,
            $today,
            $historyDays,
            $selectedDay,
            $timeBlockRepository->findBlocksForDay($selectedDay)
        );
    }

    #[Route('/tickets/import', name: 'app_tracker_import_tickets', methods: ['POST'])]
    public function importTickets(
        Request                     $request,
        SavedTicketRepository       $savedTicketRepository,
        TicketSourceParserInterface $ticketSourceParser,
        EntityManagerInterface      $entityManager,
    ): RedirectResponse
    {
        $this->validateCsrf('import-tickets', (string)$request->request->get('_token'));

        $sourceHtml = (string)$request->request->get('source_html');
        $parsedTickets = $ticketSourceParser->parse($sourceHtml);

        if ([] === $parsedTickets) {
            $this->addFlash('error', 'No live tickets were found in the pasted page source.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        $now = new \DateTimeImmutable();
        $createdCount = 0;
        $updatedCount = 0;

        foreach ($parsedTickets as $parsedTicket) {
            $savedTicket = $savedTicketRepository->findOneByTicket($parsedTicket->getTicket());

            if (null === $savedTicket) {
                $savedTicket = (new SavedTicket())
                    ->setTicket($parsedTicket->getTicket())
                    ->setCreatedAt($now);
                $entityManager->persist($savedTicket);
                ++$createdCount;
            } else {
                ++$updatedCount;
            }

            $savedTicket
                ->setJobNumber($parsedTicket->getJobNumber())
                ->setDescription($parsedTicket->getDescription())
                ->setUpdatedAt($now);
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Imported %d tickets. Created %d, updated %d.',
            count($parsedTickets),
            $createdCount,
            $updatedCount
        ));

        return $this->redirectToRoute('app_tracker_dashboard');
    }

    #[Route('/blocks/start', name: 'app_tracker_start', methods: ['POST'])]
    public function start(
        Request                $request,
        TimeBlockRepository    $timeBlockRepository,
        EntityManagerInterface $entityManager,
        QuarterHourRounder     $quarterHourRounder,
    ): RedirectResponse
    {
        $this->validateCsrf('start-block', (string)$request->request->get('_token'));

        if (null !== $timeBlockRepository->findActiveBlock()) {
            $this->addFlash('error', 'Stop the current block before starting a new one.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        $ticket = trim((string)$request->request->get('ticket'));
        $jobNumber = trim((string)$request->request->get('job_number'));
        $description = trim((string)$request->request->get('description'));

        if ('' === $ticket || '' === $jobNumber || '' === $description) {
            $this->addFlash('error', 'Ticket, job number, and description are all required.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        $now = new \DateTimeImmutable();
        $startTime = $this->resolveNextStartTime($timeBlockRepository, $quarterHourRounder, $now);

        $block = (new TimeBlock())
            ->setTicket($ticket)
            ->setJobNumber($jobNumber)
            ->setDescription($description)
            ->setCreatedAt($now)
            ->setStartTime($startTime);

        $entityManager->persist($block);
        $entityManager->flush();

        $this->addFlash('success', 'Tracking started.');

        return $this->redirectToRoute('app_tracker_dashboard');
    }

    #[Route('/blocks/{id}/continue', name: 'app_tracker_continue', methods: ['POST'])]
    public function continueBlock(
        Request $request,
        TimeBlock $timeBlock,
        TimeBlockRepository $timeBlockRepository,
        EntityManagerInterface $entityManager,
        QuarterHourRounder $quarterHourRounder,
    ): RedirectResponse
    {
        $this->validateCsrf(sprintf('continue-block-%d', $timeBlock->getId()), (string) $request->request->get('_token'));

        if (null !== $timeBlockRepository->findActiveBlock()) {
            $this->addFlash('error', 'Stop the current block before continuing another one.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        $now = new \DateTimeImmutable();
        $block = (new TimeBlock())
            ->setTicket($timeBlock->getTicket())
            ->setJobNumber($timeBlock->getJobNumber())
            ->setDescription($timeBlock->getDescription())
            ->setCreatedAt($now)
            ->setStartTime($this->resolveNextStartTime($timeBlockRepository, $quarterHourRounder, $now));

        $entityManager->persist($block);
        $entityManager->flush();

        $this->addFlash('success', 'A new block was started from the same ticket details.');

        return $this->redirectToRoute('app_tracker_dashboard');
    }

    #[Route('/blocks/{id}/stop', name: 'app_tracker_stop', methods: ['POST'])]
    public function stop(
        Request                $request,
        TimeBlock              $timeBlock,
        EntityManagerInterface $entityManager,
        QuarterHourRounder     $quarterHourRounder,
    ): RedirectResponse
    {
        $this->validateCsrf(sprintf('stop-block-%d', $timeBlock->getId()), (string)$request->request->get('_token'));

        if (!$timeBlock->isRunning()) {
            $this->addFlash('error', 'That block has already been stopped.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        $stoppedAt = $quarterHourRounder->normalizeForManualEntry(new \DateTimeImmutable());
        if ($stoppedAt < $timeBlock->getStartTime()) {
            $stoppedAt = $timeBlock->getStartTime();
        }

        $timeBlock->setEndTime($stoppedAt);
        $entityManager->flush();

        $this->addFlash('success', 'Tracking stopped and saved.');

        return $this->redirectToRoute('app_tracker_dashboard');
    }

    #[Route('/blocks/{id}/update', name: 'app_tracker_update', methods: ['POST'])]
    public function update(
        Request                $request,
        TimeBlock              $timeBlock,
        EntityManagerInterface $entityManager,
        QuarterHourRounder     $quarterHourRounder,
    ): RedirectResponse
    {
        $this->validateCsrf(sprintf('update-block-%d', $timeBlock->getId()), (string)$request->request->get('_token'));

        $ticket = trim((string)$request->request->get('ticket'));
        $jobNumber = trim((string)$request->request->get('job_number'));
        $description = trim((string)$request->request->get('description'));
        $startInput = (string)$request->request->get('start_time');
        $endInput = (string)$request->request->get('end_time');

        if ('' === $ticket || '' === $jobNumber || '' === $description || '' === $startInput) {
            $this->addFlash('error', 'Ticket, job number, description, and start time are required.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        try {
            $startTime = $quarterHourRounder->normalizeForManualEntry(new \DateTimeImmutable($startInput));
            $endTime = '' === $endInput ? null : $quarterHourRounder->normalizeForManualEntry(new \DateTimeImmutable($endInput));
        } catch (\Exception) {
            $this->addFlash('error', 'One of the provided dates could not be read.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        if (null !== $endTime && $endTime < $startTime) {
            $this->addFlash('error', 'End time must be after the start time.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        $timeBlock
            ->setTicket($ticket)
            ->setJobNumber($jobNumber)
            ->setDescription($description)
            ->setStartTime($startTime)
            ->setEndTime($endTime);

        $entityManager->flush();

        $this->addFlash('success', 'Block updated.');

        return $this->redirectToRoute('app_tracker_dashboard');
    }

    #[Route('/blocks/{id}/delete', name: 'app_tracker_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        TimeBlock $timeBlock,
        EntityManagerInterface $entityManager,
    ): RedirectResponse
    {
        $this->validateCsrf(sprintf('delete-block-%d', $timeBlock->getId()), (string) $request->request->get('_token'));

        $entityManager->remove($timeBlock);
        $entityManager->flush();

        $this->addFlash('success', 'Block deleted.');

        return $this->redirectToRoute('app_tracker_dashboard');
    }

    private function resolveNextStartTime(
        TimeBlockRepository $timeBlockRepository,
        QuarterHourRounder $quarterHourRounder,
        \DateTimeImmutable $now,
    ): \DateTimeImmutable {
        $startTime = $quarterHourRounder->floor($now);
        $latestFinishedBlock = $timeBlockRepository->findLatestFinishedBlock();

        if (null !== $latestFinishedBlock && null !== $latestFinishedBlock->getEndTime() && $latestFinishedBlock->getEndTime() > $startTime) {
            return $latestFinishedBlock->getEndTime();
        }

        return $startTime;
    }

    private function renderTodayPage(TimeBlockRepository $timeBlockRepository, SavedTicketRepository $savedTicketRepository): Response
    {
        $today = new \DateTimeImmutable('today');

        return $this->render('tracker/index.html.twig', [
            'activeBlock' => $timeBlockRepository->findActiveBlock(),
            'activeTab' => 'today',
            'historyGroups' => [],
            'savedTickets' => $savedTicketRepository->findAllForPicker(),
            'todayBlocks' => $timeBlockRepository->findBlocksForDay($today),
            'todayLabel' => $today->format('D j M Y'),
        ]);
    }

    /**
     * @return array<string, array{date: string, label: string, url: string}>
     */
    private function buildHistoryDays(TimeBlockRepository $timeBlockRepository, \DateTimeImmutable $today): array
    {
        $historyDays = [];

        foreach ($timeBlockRepository->findBlocksBeforeDay($today) as $block) {
            $key = $block->getStartTime()->format('Y-m-d');

            if (!isset($historyDays[$key])) {
                $historyDays[$key] = [
                    'date' => $key,
                    'label' => $block->getStartTime()->format('D j M Y'),
                    'url' => $this->generateUrl('app_tracker_history_day', ['date' => $key]),
                ];
            }
        }

        return $historyDays;
    }

    /**
     * @param array<string, array{date: string, label: string, url: string}> $historyDays
     * @param list<TimeBlock> $historyBlocks
     */
    private function renderHistoryPage(
        TimeBlockRepository $timeBlockRepository,
        SavedTicketRepository $savedTicketRepository,
        \DateTimeImmutable $today,
        array $historyDays,
        ?\DateTimeImmutable $selectedDay,
        array $historyBlocks,
    ): Response {
        return $this->render('tracker/index.html.twig', [
            'activeBlock' => $timeBlockRepository->findActiveBlock(),
            'activeTab' => 'history',
            'historyBlocks' => $historyBlocks,
            'historyDays' => array_values($historyDays),
            'selectedHistoryDay' => $selectedDay?->format('Y-m-d'),
            'selectedHistoryLabel' => $selectedDay?->format('D j M Y'),
            'savedTickets' => $savedTicketRepository->findAllForPicker(),
            'todayBlocks' => [],
            'todayLabel' => $today->format('D j M Y'),
        ]);
    }

    private function validateCsrf(string $id, string $token): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
