<?php
declare(strict_types=1);

/**
 * Создание заявки: валидация → бизнес-правила → лид в Битрикс24 → ответ API.
 */
final class ApplicationService
{
    private const REQUIRED_FIELDS = [
        'passport.first-name' => 'Имя',
        'passport.last-name' => 'Фамилия',
        'contact.phone' => 'Телефон',
        'passport.series' => 'Серия паспорта',
        'passport.number' => 'Номер паспорта',
    ];

    public function create(): string
    {
        (new TokenService())->validate();
        $data = Response::readJsonBody();

        $this->validateRequiredFields($data);

        // Минимальная сумма: ниже порога заявка отклоняется без создания лида.
        $amount = (float)($data['application']['amount'] ?? 0);
        if ($amount < (float)Config::get('min_amount', 100000)) {
            return Response::json([
                'data' => [
                    'link' => '',
                    'status' => 'DECLINED',
                    'application-id' => '',
                ],
            ]);
        }

        $leadData = (new LeadMapper())->map($data);
        $result = BitrixClient::instance()->call('crm.lead.add', ['fields' => $leadData]);

        if (isset($result['error'])) {
            Response::error(
                'bitrix_api_error',
                'Ошибка при создании лида: ' . ($result['error_description'] ?? $result['error']),
                null,
                500
            );
        }

        $applicationId = (int)$result['result'];

        return Response::success([
            'link' => $this->applicationLink($applicationId),
            'status' => 'PROCESSING',
            'application-id' => (string)$applicationId,
        ], 201);
    }

    private function validateRequiredFields(array $data): void
    {
        foreach (self::REQUIRED_FIELDS as $path => $label) {
            if (self::valueAtPath($data, $path) === null) {
                Response::error('missing_required_field', "Обязательное поле отсутствует: {$label}", $path, 400);
            }
        }
    }

    private static function valueAtPath(array $array, string $path): mixed
    {
        $current = $array;

        foreach (explode('.', $path) as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    private function applicationLink(int $applicationId): string
    {
        $base = rtrim((string)Config::get('application_url', 'https://example.com/zayavka/'), '/');
        $utm = Config::get('utm', []);

        return $base . '/?' . http_build_query([
            'utm_source' => $utm['source'] ?? 'banki_ru',
            'utm_medium' => $utm['medium'] ?? 'api',
            'utm_campaign' => $utm['campaign'] ?? 'kpzn',
            'application-id' => $applicationId,
        ]);
    }
}
