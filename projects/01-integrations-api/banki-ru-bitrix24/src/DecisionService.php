<?php
declare(strict_types=1);

/**
 * Статус заявки для Banki.ru на основе состояния лида/сделки в Битрикс24.
 *
 * Сопоставление «этап сделки → статус» настраивается в конфигурации (funnels).
 */
final class DecisionService
{
    private const STATUS_PRE_APPROVED = 'PRE-APPROVED';
    private const STATUS_APPROVED = 'APPROVED';
    private const STATUS_ISSUED = 'ISSUED';
    private const STATUS_DECLINED = 'DECLINED';

    public function resolve(string $applicationId): string
    {
        (new TokenService())->validate();

        $lead = BitrixClient::instance()->call('crm.lead.get', [
            'id' => $applicationId,
            'select' => ['ID', 'OPPORTUNITY', 'STATUS_ID'],
        ]);

        if (isset($lead['error']) || empty($lead['result'])) {
            Response::error('not_found', 'Лид не найден: ' . $applicationId, '', 404);
        }

        $leadData = $lead['result'];
        $requestedAmount = (float)($leadData['OPPORTUNITY'] ?? 0);

        // Лид забракован.
        if ($leadData['STATUS_ID'] === 'JUNK') {
            return $this->response($applicationId, self::STATUS_DECLINED);
        }

        // Лид преобразован в сделку — статус определяем по этапу сделки.
        if ($leadData['STATUS_ID'] === 'CONVERTED') {
            $resolved = $this->resolveConverted($applicationId);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Во всех остальных случаях — предодобрено.
        $preApproved = min($requestedAmount, (float)Config::get('max_pre_approved', 5000000));
        return $this->response($applicationId, self::STATUS_PRE_APPROVED, $preApproved);
    }

    private function resolveConverted(string $applicationId): ?string
    {
        $approvedField = (string)Config::get('field_approved_amount', 'UF_CRM_APPROVED_AMOUNT');
        $issuedField = (string)Config::get('field_issued_amount', 'UF_CRM_ISSUED_AMOUNT');

        $deal = BitrixClient::instance()->call('crm.deal.list', [
            'filter' => ['LEAD_ID' => (int)$applicationId],
            'select' => ['ID', 'STAGE_ID', 'CATEGORY_ID', 'OPPORTUNITY', $approvedField, $issuedField],
            'limit' => 1,
            'order' => ['ID' => 'DESC'],
        ]);

        if (empty($deal['result'])) {
            return null;
        }

        $dealData = $deal['result'][0];
        $categoryId = (int)($dealData['CATEGORY_ID'] ?? 0);
        $stageId = (string)($dealData['STAGE_ID'] ?? '');

        $approvedAmount = isset($dealData[$approvedField]) ? (float)$dealData[$approvedField] : null;
        $issuedAmount = isset($dealData[$issuedField]) ? (float)$dealData[$issuedField] : null;

        return $this->resolveFunnel($applicationId, $categoryId, $stageId, $approvedAmount, $issuedAmount);
    }

    private function resolveFunnel(
        string $applicationId,
        int $categoryId,
        string $stageId,
        ?float $approvedAmount,
        ?float $issuedAmount
    ): string {
        $funnel = $this->findFunnel($categoryId);

        if ($funnel === null) {
            return $this->response($applicationId, self::STATUS_PRE_APPROVED);
        }

        if (in_array($stageId, $funnel['approved_stages'] ?? [], true)) {
            return $this->response($applicationId, self::STATUS_APPROVED, $approvedAmount);
        }

        if (in_array($stageId, $funnel['issued_stages'] ?? [], true)) {
            return $this->response($applicationId, self::STATUS_ISSUED, $issuedAmount);
        }

        if (in_array($stageId, $funnel['declined_stages'] ?? [], true)) {
            return $this->response($applicationId, self::STATUS_DECLINED);
        }

        return $this->response($applicationId, self::STATUS_PRE_APPROVED);
    }

    private function findFunnel(int $categoryId): ?array
    {
        foreach ((array)Config::get('funnels', []) as $funnel) {
            if ((int)($funnel['category_id'] ?? 0) === $categoryId) {
                return $funnel;
            }
        }

        return null;
    }

    private function response(string $applicationId, string $status, ?float $limit = null): string
    {
        $base = rtrim((string)Config::get('application_url', 'https://example.com/zayavka/'), '/');
        $utm = Config::get('utm', []);

        $payload = [
            'application-id' => $applicationId,
            'status' => $status,
            'link' => $base . '/?' . http_build_query([
                'utm_source' => $utm['source'] ?? 'banki_ru',
                'utm_medium' => $utm['medium'] ?? 'api',
                'utm_campaign' => $utm['campaign'] ?? 'kpzn',
                'application-id' => $applicationId,
            ]),
        ];

        // Лимит возвращаем только для APPROVED и ISSUED.
        $payload['limit'] = in_array($status, [self::STATUS_APPROVED, self::STATUS_ISSUED], true) ? $limit : null;
        $payload['duration-days'] = null;
        $payload['ratePercentPerDay'] = null;

        return Response::success($payload);
    }
}
