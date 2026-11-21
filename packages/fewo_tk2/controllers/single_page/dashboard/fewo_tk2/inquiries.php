<?php

namespace Concrete\Package\FewoTk2\Controller\SinglePage\Dashboard\FewoTk2;

use Concrete\Core\Page\Controller\DashboardPageController;
use FewoTk2\Entity\Inquiry;
use FewoTk2\Inquiry\InquiryRetention;

class Inquiries extends DashboardPageController
{
    private const PER_PAGE = 25;

    public function view($page = 1)
    {
        $retention = $this->app->make(InquiryRetention::class);
        $retention->purgeExpired();

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
        $this->set('retentionDays', $retention->getDays());
    }

    public function detail($id = null)
    {
        $inquiry = $this->find($id);
        if ($inquiry === null) {
            return $this->buildRedirect($this->action('view'));
        }
        $this->set('inquiry', $inquiry);
        $this->render('/dashboard/fewo_tk2/inquiries/detail', 'fewo_tk2');
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
            $this->app->make(InquiryRetention::class)->setDays((int) $this->request->request->get('retentionDays'));
            $this->flash('success', t('The settings have been saved.'));
        }

        return $this->buildRedirect($this->action('view'));
    }

    private function find($id): ?Inquiry
    {
        return $id ? $this->getEntityManager()->find(Inquiry::class, (int) $id) : null;
    }
}
