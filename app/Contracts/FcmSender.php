<?php

namespace App\Contracts;

use App\Exceptions\FcmDeliveryException;

interface FcmSender
{
    /**
     * Push a data-only message to a single device.
     *
     * @param  array<string, string>  $data
     *
     * @throws FcmDeliveryException when the message could not be handed to FCM.
     */
    public function sendData(string $fcmToken, array $data): void;
}
