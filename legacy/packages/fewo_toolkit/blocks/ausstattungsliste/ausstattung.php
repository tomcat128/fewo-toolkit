<?php
namespace Concrete\Package\FewoToolkit\Block\Ausstattungsliste;

class Gegenstand
{
    public $aID;
    public $ausstattung;
    public $sortierung;
    public $kommentar;

    public function __construct($id, $name, $order, $comment = null)
    {
        $this->aID = $id;
        $this->ausstattung = $name;
        $this->sortierung = $order;
        $this->kommentar = $comment;
    }
    
    public function getID()
    {
        return $this->aID;
    }

    public function getName()
    {
        return $this->ausstattung;
    }

    public function getSortierung()
    {
        return $this->sortierung;
    }

    public function setSortierung($order)
    {
        $this->sortierung = $order;
    }

    public function getKommentar()
    {
        return (isset($this->kommentar) ? $this->kommentar : null);
    }
}
