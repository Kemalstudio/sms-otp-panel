<?php

namespace App\Services\Fcm;

use App\Contracts\FcmSender;
use App\Exceptions\FcmDeliveryException;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * In-memory stand-in installed by Fcm::fake(). Records every push instead of
 * talking to Google, and can be told to reject them to exercise the
 * failed-delivery path.
 */
class FakeFcmSender implements FcmSender
{
    /** @var list<array{token: string, data: array<string, string>}> */
    private array $messages = [];

    private ?string $failureMessage = null;

    public function sendData(string $fcmToken, array $data): void
    {
        if ($this->failureMessage !== null) {
            throw new FcmDeliveryException($this->failureMessage);
        }

        $this->messages[] = ['token' => $fcmToken, 'data' => $data];
    }

    /**
     * Make every subsequent push fail, as FCM would for an unregistered token.
     */
    public function shouldFail(string $message = 'The registration token is not registered.'): self
    {
        $this->failureMessage = $message;

        return $this;
    }

    /** @return list<array{token: string, data: array<string, string>}> */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * @param  (callable(array<string, string>, string): bool)|null  $callback
     */
    public function assertSent(?callable $callback = null): void
    {
        $matching = array_filter(
            $this->messages,
            fn (array $m) => $callback === null || $callback($m['data'], $m['token'])
        );

        PHPUnit::assertNotEmpty($matching, 'No matching FCM message was sent.');
    }

    /**
     * @param  (callable(array<string, string>): bool)|null  $callback
     */
    public function assertSentTo(string $fcmToken, ?callable $callback = null): void
    {
        $this->assertSent(
            fn (array $data, string $token) => $token === $fcmToken
                && ($callback === null || $callback($data))
        );
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertSame([], $this->messages, 'Expected no FCM messages to be sent.');
    }

    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->messages);
    }
}
