<?php

namespace FewoTk2\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Eine an den KI-Assistenten gestellte Frage – gespeichert wird nur, dass und wann gefragt wurde,
 * nicht was. Dient der Begrenzung (Fragen je Besucher und je Website pro Stunde) und wird nach
 * einem Tag gelöscht. Der Besucher ist nur als gesalzener Hash seiner IP-Adresse enthalten.
 *
 * @ORM\Entity
 * @ORM\Table(name="FewoAssistantRequests", indexes={@ORM\Index(name="created", columns={"createdAt"})})
 */
class AssistantRequest
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id = null;

    /**
     * @ORM\Column(type="string", length=64)
     */
    protected string $visitor;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    protected DateTimeImmutable $createdAt;

    public function __construct(string $visitor, ?DateTimeImmutable $createdAt = null)
    {
        $this->visitor = $visitor;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVisitor(): string
    {
        return $this->visitor;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
