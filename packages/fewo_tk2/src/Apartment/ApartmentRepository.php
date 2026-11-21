<?php

namespace FewoTk2\Apartment;

use Doctrine\ORM\EntityManagerInterface;
use FewoTk2\Entity\Apartment;

/**
 * Zugriff auf die im Dashboard hinterlegten Wohnungen.
 */
class ApartmentRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(?int $id): ?Apartment
    {
        if ($id === null || $id < 1) {
            return null;
        }

        return $this->entityManager->find(Apartment::class, $id);
    }

    /**
     * @return Apartment[]
     */
    public function findAll(): array
    {
        return $this->entityManager->getRepository(Apartment::class)->findBy([], ['name' => 'ASC']);
    }

    /**
     * Auswahlliste für Block-Formulare.
     *
     * @return array<int|string, string>
     */
    public function getSelectOptions(string $emptyLabel): array
    {
        $options = ['' => $emptyLabel];
        foreach ($this->findAll() as $apartment) {
            $options[$apartment->getId()] = $apartment->getName();
        }

        return $options;
    }

    public function save(Apartment $apartment): void
    {
        $this->entityManager->persist($apartment);
        $this->entityManager->flush();
    }

    public function delete(Apartment $apartment): void
    {
        $this->entityManager->remove($apartment);
        $this->entityManager->flush();
    }
}
