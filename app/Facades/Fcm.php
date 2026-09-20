<?php

namespace App\Facades;

use App\Contracts\FcmSender;
use App\Services\Fcm\FakeFcmSender;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void sendData(string $fcmToken, array $data)
 *
 * @see \App\Contracts\FcmSender
 */
class Fcm extends Facade
{
    /**
     * Swap the real Firebase sender for a recording stub. Tests never reach
     * Google; everything asserted comes off the fake.
     */
    public static function fake(): FakeFcmSender
    {
        static::swap($fake = new FakeFcmSender);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return FcmSender::class;
    }
}
