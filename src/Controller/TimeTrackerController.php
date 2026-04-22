<?php

namespace App\Controller;

use App\Entity\TimeBlock;
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
    public function index(TimeBlockRepository $timeBlockRepository): Response
    {
        $today = new \DateTimeImmutable('today');

        return $this->render('tracker/index.html.twig', [
            'activeBlock' => $timeBlockRepository->findActiveBlock(),
            'todayBlocks' => $timeBlockRepository->findBlocksForDay($today),
            'todayLabel' => $today->format('D j M Y'),
        ]);
    }

    #[Route('/blocks/start', name: 'app_tracker_start', methods: ['POST'])]
    public function start(
        Request $request,
        TimeBlockRepository $timeBlockRepository,
        EntityManagerInterface $entityManager,
        QuarterHourRounder $quarterHourRounder,
    ): RedirectResponse {
        $this->validateCsrf('start-block', (string) $request->request->get('_token'));

        if (null !== $timeBlockRepository->findActiveBlock()) {
            $this->addFlash('error', 'Stop the current block before starting a new one.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        $ticket = trim((string) $request->request->get('ticket'));
        $jobNumber = trim((string) $request->request->get('job_number'));
        $description = trim((string) $request->request->get('description'));

        if ('' === $ticket || '' === $jobNumber || '' === $description) {
            $this->addFlash('error', 'Ticket, job number, and description are all required.');

            return $this->redirectToRoute('app_tracker_dashboard');
        }

        $now = new \DateTimeImmutable();
        $startTime = $quarterHourRounder->floor($now);
        $latestFinishedBlock = $timeBlockRepository->findLatestFinishedBlock();

        if (null !== $latestFinishedBlock && null !== $latestFinishedBlock->getEndTime() && $latestFinishedBlock->getEndTime() > $startTime) {
            $startTime = $latestFinishedBlock->getEndTime();
        }

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

    #[Route('/blocks/{id}/stop', name: 'app_tracker_stop', methods: ['POST'])]
    public function stop(
        Request $request,
        TimeBlock $timeBlock,
        EntityManagerInterface $entityManager,
        QuarterHourRounder $quarterHourRounder,
    ): RedirectResponse {
        $this->validateCsrf(sprintf('stop-block-%d', $timeBlock->getId()), (string) $request->request->get('_token'));

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
        Request $request,
        TimeBlock $timeBlock,
        EntityManagerInterface $entityManager,
        QuarterHourRounder $quarterHourRounder,
    ): RedirectResponse {
        $this->validateCsrf(sprintf('update-block-%d', $timeBlock->getId()), (string) $request->request->get('_token'));

        $ticket = trim((string) $request->request->get('ticket'));
        $jobNumber = trim((string) $request->request->get('job_number'));
        $description = trim((string) $request->request->get('description'));
        $startInput = (string) $request->request->get('start_time');
        $endInput = (string) $request->request->get('end_time');

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

    private function validateCsrf(string $id, string $token): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
