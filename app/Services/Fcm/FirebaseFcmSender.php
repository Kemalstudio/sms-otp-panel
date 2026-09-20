<?php

namespace App\Services\Fcm;

use App\Contracts\FcmSender;
use App\Exceptions\FcmDeliveryException;
use App\Models\OtpLog;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

/**
 * Firebase Admin SDK backed sender. The SDK is only constructed on the first
 * send, so a deployment without credentials still boots.
 */
class FirebaseFcmSender implements FcmSender
{
    private ?Messaging $messaging = null;

    public function __construct(
        private readonly ?string $credentialsPath = null,
    ) {
    }

    public function sendData(string $fcmToken, array $data): void
    {
        try {
            $this->messaging()->send(
                CloudMessage::withTarget('token', $fcmToken)
                    ->withData($data)
                    ->withAndroidConfig($this->androidConfig())
            );
        } catch (Throwable $e) {
            throw new FcmDeliveryException($e->getMessage(), 0, $e);
        }
    }

    /**
     * A data-only message defaults to normal priority, which Android is free to
     * hold back until the handset leaves Doze — hours, for a phone sitting idle
     * on a shelf overnight. An OTP cannot wait, hence `high`.
     *
     * The TTL matches the code's own lifetime: past that the code is expired,
     * and an SMS arriving late only confuses the customer and costs money.
     */
    private function androidConfig(): AndroidConfig
    {
        return AndroidConfig::fromArray([
            'priority' => 'high',
            'ttl' => (OtpLog::LIFETIME_MINUTES * 60).'s',
        ]);
    }

    private function messaging(): Messaging
    {
        if ($this->messaging instanceof Messaging) {
            return $this->messaging;
        }

        $path = $this->credentialsPath;

        if (blank($path)) {
            throw new FcmDeliveryException(
                'FIREBASE_CREDENTIALS_PATH is not configured.'
            );
        }

        if (! is_file($path)) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            throw new FcmDeliveryException(
                "Firebase service account file not found at [{$this->credentialsPath}]."
            );
        }

        return $this->messaging = (new Factory)
            ->withServiceAccount($path)
            ->createMessaging();
    }
}
