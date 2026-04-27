<?php

namespace App\Core\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Уведомление отправляет одноразовый код второго фактора по почте.
 * Сейчас это основной канал, а дальше к нему можно добавить телефон.
 */
class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $code,
        protected string $expiresAt,
    ) {
    }

    /**
     * Уведомление использует почту как первый канал 2FA.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Уведомление формирует простое письмо с одноразовым кодом.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Код подтверждения входа')
            ->line('Для входа в административную систему нужен код второго фактора.')
            ->line('Код: '.$this->code)
            ->line('Код действует до '.$this->expiresAt)
            ->line('Если вход выполняли не вы, проигнорируйте это письмо.');
    }
}