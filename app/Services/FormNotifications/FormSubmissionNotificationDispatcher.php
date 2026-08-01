<?php

namespace App\Services\FormNotifications;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\FormNotifications\Contracts\FormSubmissionNotificationChannel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FormSubmissionNotificationDispatcher
{
    public function __construct(private readonly Container $container) {}

    public function dispatch(Form $form, FormSubmission $submission): void
    {
        $settings = data_get($form->settings, 'notifications', []);

        if (! is_array($settings) || ! filter_var($settings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        foreach (config('cms.form_notifications.channels', []) as $channelClass) {
            if (! is_string($channelClass) || ! is_a($channelClass, FormSubmissionNotificationChannel::class, true)) {
                continue;
            }

            try {
                /** @var FormSubmissionNotificationChannel $channel */
                $channel = $this->container->make($channelClass);

                if (! $channel->supports($settings)) {
                    continue;
                }

                $channel->send($form, $submission, $settings);
            } catch (Throwable $exception) {
                Log::warning('Form submission notification failed.', [
                    'channel' => $channelClass,
                    'form_id' => $form->getKey(),
                    'submission_id' => $submission->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
