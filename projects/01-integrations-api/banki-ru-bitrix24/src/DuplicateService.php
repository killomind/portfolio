<?php
declare(strict_types=1);

/**
 * Проверка дублей по телефону: лиды → контакты, несколько форматов номера.
 *
 * Возвращает status: repeat | new.
 */
final class DuplicateService
{
    private BitrixClient $bitrix;
    private RequestLogger $logger;

    public function __construct(?BitrixClient $bitrix = null, ?RequestLogger $logger = null)
    {
        $this->bitrix = $bitrix ?? BitrixClient::instance();
        $this->logger = $logger ?? new RequestLogger();
    }

    public function check(string $phone): array
    {
        $requestId = RequestLogger::requestId();
        $this->logger->logIn($requestId, $phone);

        return ['status' => $this->resolveStatus($phone, $requestId)];
    }

    private function resolveStatus(string $phone, string $requestId): string
    {
        if ($phone === '') {
            $this->logger->logOut($requestId, 'new (empty)');
            return 'new';
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 10) {
            $this->logger->logOut($requestId, 'new (too short)');
            return 'new';
        }

        $base = substr($digits, -10);
        $variants = array_values(array_unique(['+7' . $base, '8' . $base, $phone]));

        if ($this->existsIn('crm.lead.list', $variants)) {
            $this->logger->logOut($requestId, 'repeat (lead found)');
            return 'repeat';
        }

        if ($this->existsIn('crm.contact.list', $variants)) {
            $this->logger->logOut($requestId, 'repeat (contact found)');
            return 'repeat';
        }

        $this->logger->logOut($requestId, 'new (no duplicates)');
        return 'new';
    }

    private function existsIn(string $method, array $variants): bool
    {
        foreach ($variants as $variant) {
            $result = $this->bitrix->call($method, [
                'filter' => ['PHONE' => $variant],
                'select' => ['ID'],
                'start' => 0,
            ]);

            if (!isset($result['error']) && !empty($result['result'])) {
                return true;
            }
        }

        return false;
    }
}
