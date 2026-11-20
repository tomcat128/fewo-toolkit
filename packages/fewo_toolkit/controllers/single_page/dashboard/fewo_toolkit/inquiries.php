<?php

namespace Concrete\Package\FewoToolkit\Controller\SinglePage\Dashboard\FewoToolkit;

use Concrete\Core\Package\PackageService;
use Concrete\Core\Page\Controller\DashboardPageController;
use DateTimeImmutable;
use FewoToolkit\Entity\Inquiry;

class Inquiries extends DashboardPageController
{
    private const PER_PAGE = 25;
    private const CONFIG_RETENTION = 'inquiries.retention_days';
    private const DEFAULT_RETENTION_DAYS = 365;

    public function view($page = 1)
    {
        $this->purgeExpired();

        $status = (string) $this->request->query->get('status', '');
        $page = max(1, (int) $page);
        $repository = $this->getEntityManager()->getRepository(Inquiry::class);
        $criteria = in_array($status, [Inquiry::STATUS_NEW, Inquiry::STATUS_DONE], true) ? ['status' => $status] : [];

        $total = $repository->count($criteria);
        $this->set('inquiries', $repository->findBy($criteria, ['createdAt' => 'DESC'], self::PER_PAGE, ($page - 1) * self::PER_PAGE));
        $this->set('status', $status);
        $this->set('page', $page);
        $this->set('pages', max(1, (int) ceil($total / self::PER_PAGE)));
        $this->set('total', $total);
        $this->set('newCount', $repository->count(['status' => Inquiry::STATUS_NEW]));
        $this->set('retentionDays', $this->getRetentionDays());
    }

    public function detail($id = null)
    {
        $inquiry = $this->find($id);
        if ($inquiry === null) {
            return $this->buildRedirect($this->action('view'));
        }
        $this->set('inquiry', $inquiry);
        $this->render('/dashboard/fewo_toolkit/inquiries/detail', 'fewo_toolkit');
    }

    public function set_status($id = null)
    {
        $inquiry = $this->find($id);
        if ($inquiry !== null && $this->token->validate('fewo_inquiry_status')) {
            $inquiry->setStatus((string) $this->request->request->get('status'));
            $this->getEntityManager()->flush();
            $this->flash('success', t('The status has been updated.'));
        }

        return $this->buildRedirect($this->action('detail', (int) $id));
    }

    public function delete($id = null)
    {
        $inquiry = $this->find($id);
        if ($inquiry !== null && $this->token->validate('fewo_inquiry_delete')) {
            $this->getEntityManager()->remove($inquiry);
            $this->getEntityManager()->flush();
            $this->flash('success', t('The inquiry has been deleted.'));
        }

        return $this->buildRedirect($this->action('view'));
    }

    public function save_settings()
    {
        if ($this->token->validate('fewo_inquiry_settings')) {
            $days = max(0, min(3650, (int) $this->request->request->get('retentionDays')));
            $this->getPackageConfig()->save(self::CONFIG_RETENTION, $days);
            $this->flash('success', t('The settings have been saved.'));
        }

        return $this->buildRedirect($this->action('view'));
    }

    private function find($id): ?Inquiry
    {
        return $id ? $this->getEntityManager()->find(Inquiry::class, (int) $id) : null;
    }

    private function getRetentionDays(): int
    {
        return (int) $this->getPackageConfig()->get(self::CONFIG_RETENTION, self::DEFAULT_RETENTION_DAYS);
    }

    /**
     * Löscht Anfragen, die älter als die eingestellte Aufbewahrungsfrist sind (0 = nie).
     */
    private function purgeExpired(): void
    {
        $days = $this->getRetentionDays();
        if ($days < 1) {
            return;
        }
        $this->getEntityManager()->createQueryBuilder()
            ->delete(Inquiry::class, 'i')
            ->where('i.createdAt < :limit')
            ->setParameter('limit', new DateTimeImmutable("-{$days} days"), 'datetime_immutable')
            ->getQuery()
            ->execute();
    }

    private function getPackageConfig()
    {
        return $this->app->make(PackageService::class)->getByHandle('fewo_toolkit')->getController()->getFileConfig();
    }
}
