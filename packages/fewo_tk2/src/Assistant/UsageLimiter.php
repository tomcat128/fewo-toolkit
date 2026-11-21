<?php

namespace FewoTk2\Assistant;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use FewoTk2\Entity\AssistantRequest;

/**
 * Begrenzt die Fragen an den Assistenten: je Besucher und für die ganze Website pro Stunde.
 *
 * Ein Sprachmodell auf dem eigenen Server beantwortet nur wenige Fragen gleichzeitig; ohne Grenze
 * könnte ein einzelner Besucher oder ein Skript den Server auslasten.
 */
class UsageLimiter
{
    private const RETENTION = '-1 day';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * Zählt die Frage und meldet, ob sie noch innerhalb der Grenzen liegt.
     */
    public function register(string $ipAddress, AssistantSettings $settings): bool
    {
        $now = new DateTimeImmutable();
        $visitor = hash('sha256', $this->settingsRepository->getSalt() . '|' . $ipAddress);
        $this->purge($now);

        $since = $now->modify('-1 hour');
        if ($this->count($since, $visitor) >= $settings->visitorLimit || $this->count($since) >= $settings->siteLimit) {
            return false;
        }
        $this->entityManager->persist(new AssistantRequest($visitor, $now));
        $this->entityManager->flush();

        return true;
    }

    public function count(DateTimeImmutable $since, ?string $visitor = null): int
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(AssistantRequest::class, 'r')
            ->where('r.createdAt >= :since')
            ->setParameter('since', $since, 'datetime_immutable');
        if ($visitor !== null) {
            $query->andWhere('r.visitor = :visitor')->setParameter('visitor', $visitor);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    private function purge(DateTimeImmutable $now): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(AssistantRequest::class, 'r')
            ->where('r.createdAt < :limit')
            ->setParameter('limit', $now->modify(self::RETENTION), 'datetime_immutable')
            ->getQuery()
            ->execute();
    }
}
