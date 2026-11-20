<?php

namespace FewoToolkit\Pricing;

use Doctrine\ORM\EntityManagerInterface;
use FewoToolkit\Entity\Pricelist;

/**
 * Zugriff auf die Preislisten.
 */
class PricelistRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(?int $id): ?Pricelist
    {
        if ($id === null || $id < 1) {
            return null;
        }

        return $this->entityManager->find(Pricelist::class, $id);
    }

    /**
     * @return Pricelist[]
     */
    public function findAll(): array
    {
        return $this->entityManager->getRepository(Pricelist::class)->findBy([], ['name' => 'ASC']);
    }

    /**
     * Auswahlliste für Block-Formulare.
     *
     * @return array<int|string, string>
     */
    public function getSelectOptions(string $emptyLabel = ''): array
    {
        $options = $emptyLabel !== '' ? ['' => $emptyLabel] : [];
        foreach ($this->findAll() as $pricelist) {
            $options[$pricelist->getId()] = $pricelist->getName();
        }

        return $options;
    }

    public function save(Pricelist $pricelist): void
    {
        $this->entityManager->persist($pricelist);
        $this->entityManager->flush();
    }

    public function delete(Pricelist $pricelist): void
    {
        $this->entityManager->remove($pricelist);
        $this->entityManager->flush();
    }
}
