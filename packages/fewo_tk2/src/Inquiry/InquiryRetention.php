<?php

namespace FewoTk2\Inquiry;

use Concrete\Core\Package\PackageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use FewoTk2\Entity\Inquiry;

/**
 * Löschfrist für gespeicherte Anfragen (Datensparsamkeit).
 *
 * Abgelaufene Anfragen werden ohne Cronjob entfernt: beim Öffnen der Anfragen
 * im Dashboard und jedes Mal, wenn eine neue Anfrage gespeichert wird.
 */
class InquiryRetention
{
    public const DEFAULT_DAYS = 365;
    public const MAX_DAYS = 3650;

    private const CONFIG_KEY = 'inquiries.retention_days';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PackageService $packageService,
    ) {
    }

    /**
     * @return int Aufbewahrung in Tagen, 0 = nie löschen
     */
    public function getDays(): int
    {
        return max(0, min(self::MAX_DAYS, (int) $this->getConfig()->get(self::CONFIG_KEY, self::DEFAULT_DAYS)));
    }

    public function setDays(int $days): void
    {
        $this->getConfig()->save(self::CONFIG_KEY, max(0, min(self::MAX_DAYS, $days)));
    }

    /**
     * @return int Anzahl der gelöschten Anfragen
     */
    public function purgeExpired(): int
    {
        $days = $this->getDays();
        if ($days < 1) {
            return 0;
        }

        return (int) $this->entityManager->createQueryBuilder()
            ->delete(Inquiry::class, 'i')
            ->where('i.createdAt < :limit')
            ->setParameter('limit', new DateTimeImmutable("-{$days} days"), 'datetime_immutable')
            ->getQuery()
            ->execute();
    }

    private function getConfig()
    {
        return $this->packageService->getByHandle('fewo_tk2')->getController()->getFileConfig();
    }
}
