<?php

namespace FewoToolkit\Import;

use DateTimeImmutable;
use FewoToolkit\Entity\Pricelist;
use FewoToolkit\Entity\Season;
use SimpleXMLElement;

/**
 * Importiert Preislisten im XML-Format des alten Fewo Toolkits
 * (Export unter /fewobacher/export/pricelist/{id}).
 *
 * <fewobacher data="pricelist">
 *   <status><type>OK</type>…</status>
 *   <pricelist><name/><cleaning/><min_stay/><max_guests/><commission/></pricelist>
 *   <seasons><season><name/><date_start/><date_end/><prices><persons/><value/></prices>…</season>…</seasons>
 * </fewobacher>
 */
class LegacyPricelistImporter
{
    /**
     * @throws ImportException
     */
    public function import(string $xml, ?Pricelist $target = null): Pricelist
    {
        $document = $this->load($xml);

        $status = strtoupper(trim((string) ($document->status->type ?? 'OK')));
        if ($status !== 'OK') {
            throw new ImportException(t('The export reports an error: %s', trim((string) $document->status->message)));
        }
        if (!isset($document->pricelist)) {
            throw new ImportException(t('The file does not contain a price list.'));
        }

        $pricelist = $target ?? new Pricelist();
        $meta = $document->pricelist;
        if ($target === null || $pricelist->getName() === '') {
            $pricelist->setName(trim((string) $meta->name) ?: t('Imported price list'));
        }
        $pricelist
            ->setCleaningFee($this->amount((string) $meta->cleaning) ?? 0)
            ->setMinNights((int) $meta->min_stay ?: 1)
            ->setMaxGuests((int) $meta->max_guests ?: 2)
            ->setDownPaymentPercent((int) $meta->commission);

        $pricelist->clearSeasons();
        foreach ($document->seasons->season ?? [] as $node) {
            $from = $this->date((string) $node->date_start);
            $to = $this->date((string) $node->date_end);
            if ($from === null || $to === null) {
                continue;
            }
            $prices = [];
            foreach ($node->prices as $price) {
                $prices[(int) $price->persons] = $this->amount((string) $price->value);
            }
            $season = new Season(trim((string) $node->name), $from, $to < $from ? $from : $to);
            $season->setPrices($prices);
            $pricelist->addSeason($season);
        }

        if ($pricelist->getSeasons() === []) {
            throw new ImportException(t('The file does not contain any valid seasons.'));
        }

        return $pricelist;
    }

    private function load(string $xml): SimpleXMLElement
    {
        $xml = trim(preg_replace('/^\xEF\xBB\xBF/', '', $xml));
        if ($xml === '') {
            throw new ImportException(t('The file is empty.'));
        }
        // Das alte Toolkit hat Sonderzeichen nicht maskiert ("Ostern & Pfingsten").
        $xml = preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9a-f]+);)/i', '&amp;', $xml);

        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($document === false) {
            throw new ImportException(t('The file is not valid XML.'));
        }

        return $document;
    }

    /**
     * Beträge im Export sind maschinenlesbar ("85", "85.5" oder "85,50").
     */
    private function amount(string $value): ?int
    {
        $value = str_replace(',', '.', trim($value));

        return is_numeric($value) ? (int) round((float) $value * 100) : null;
    }

    private function date(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        foreach (['!Y-m-d', '!d.m.Y', '!Ymd'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false && $date->format(substr($format, 1)) === $value) {
                return $date;
            }
        }

        return null;
    }
}
