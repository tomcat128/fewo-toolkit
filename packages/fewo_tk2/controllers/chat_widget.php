<?php

namespace Concrete\Package\FewoTk2\Controller;

use Concrete\Core\Controller\Controller;
use FewoTk2\Assistant\ChatResponder;
use FewoTk2\Assistant\SettingsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Aufrufe des Chats auf jeder Seite (Dashboard › Fewo Toolkit › KI-Assistent › "Chat auf jeder Seite"):
 * /fewo_tk2/chat/token, /fewo_tk2/chat/ask und /fewo_tk2/chat/warm.
 */
class ChatWidget extends Controller
{
    public const TOKEN_ACTION = 'fewo_chat_widget';

    /**
     * Token für die folgenden Aufrufe. Es wird erst beim Öffnen des Chats geholt und steht nicht im HTML
     * der Seite – so bleibt es auch gültig, wenn Seiten aus dem Zwischenspeicher ausgeliefert werden.
     */
    public function token()
    {
        if (!$this->isActive()) {
            return new JsonResponse(['error' => t('The chat is not available.')], 404);
        }

        return new JsonResponse(['token' => $this->app->make('token')->generate(self::TOKEN_ACTION)], 200, ['Cache-Control' => 'no-store']);
    }

    public function ask()
    {
        if (!$this->isActive()) {
            return new JsonResponse(['error' => t('The chat is not available.')], 404);
        }
        if (!$this->hasValidToken()) {
            return new JsonResponse(['error' => t('The chat has expired. Please reload the page.')], 403);
        }

        return $this->app->make(ChatResponder::class)->ask($this->request);
    }

    public function warm()
    {
        if (!$this->isActive() || !$this->hasValidToken()) {
            return new JsonResponse(['ok' => false], 403);
        }

        return $this->app->make(ChatResponder::class)->warm();
    }

    private function isActive(): bool
    {
        return $this->app->make(SettingsRepository::class)->get()->isWidgetActive();
    }

    private function hasValidToken(): bool
    {
        return $this->app->make('token')->validate(self::TOKEN_ACTION, (string) $this->request->request->get('fewo_token', ''));
    }
}
