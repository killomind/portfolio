<?php
declare(strict_types=1);

/**
 * Маппинг анкеты заявки из API Banki.ru в поля лида Битрикс24.
 *
 * Детальная информация формируется в виде комментария лида, значения словарей
 * переводятся на русский, предмет залога пишется в отдельное пользовательское поле.
 */
final class LeadMapper
{
    private const SOURCE_DESCRIPTION = 'Заявка с banki.ru';

    /**
     * @return array<string, mixed>
     */
    public function map(array $data): array
    {
        $lead = [
            'TITLE' => $this->title($data),
            'NAME' => $data['passport']['first-name'] ?? '',
            'LAST_NAME' => $data['passport']['last-name'] ?? '',
            'SECOND_NAME' => $data['passport']['middle-name'] ?? '',
            'BIRTHDATE' => $data['passport']['birth-day'] ?? '',
            'OPPORTUNITY' => $data['application']['amount'] ?? 0,
            'SOURCE_ID' => Config::get('source_id', 'REPEAT_SALE'),
            'SOURCE_DESCRIPTION' => self::SOURCE_DESCRIPTION,
            'ASSIGNED_BY_ID' => (int)Config::get('responsible_user_id', 0),
        ];

        $this->addContactFields($lead, $data);
        $lead['COMMENTS'] = $this->buildComments($data);
        $lead[(string)Config::get('field_pledge_info', 'UF_CRM_PLEDGE_INFO')] = $this->pledgeInfo($data);

        return $lead;
    }

    private function title(array $data): string
    {
        $last = $data['passport']['last-name'] ?? '';
        $first = $data['passport']['first-name'] ?? '';
        return trim("Заявка banki.ru: {$last} {$first}");
    }

    private function addContactFields(array &$lead, array $data): void
    {
        if (!empty($data['contact']['phone'])) {
            $lead['PHONE'] = [['VALUE' => $data['contact']['phone'], 'VALUE_TYPE' => 'WORK']];
        }

        if (!empty($data['contact']['email'])) {
            $lead['EMAIL'] = [['VALUE' => $data['contact']['email'], 'VALUE_TYPE' => 'WORK']];
        }
    }

    private function pledgeInfo(array $data): string
    {
        $type = $this->translate('pledgeType', $data['pledges']['pledgeType'] ?? '');
        $size = $data['pledges']['pledgeSize'] ?? '';
        $address = $data['pledges']['pledgeAddress']['address-name'] ?? '';

        return "Тип: {$type}\nПлощадь: {$size} м²\nАдрес: {$address}";
    }

    private function buildComments(array $data): string
    {
        $c = "*** ЗАЯВКА С BANKI.RU ***\n";
        $c .= str_repeat('=', 40) . "\n\n";

        $passport = $data['passport'] ?? [];
        $c .= "ПАСПОРТНЫЕ ДАННЫЕ ЗАЕМЩИКА:\n";
        $c .= $this->field('ФИО', trim(implode(' ', [
            $passport['last-name'] ?? '',
            $passport['first-name'] ?? '',
            $passport['middle-name'] ?? '',
        ])));
        $c .= $this->field('Серия/Номер', trim(($passport['series'] ?? '') . ' ' . ($passport['number'] ?? '')));
        $c .= $this->field('Код подразделения', $passport['division-code'] ?? '');
        $c .= $this->field('Кем выдан', $passport['issued-by'] ?? '');
        $c .= $this->field('Дата выдачи', $passport['issue-date'] ?? '');
        $c .= $this->field('Дата рождения', $passport['birth-day'] ?? '');
        $c .= $this->field('Место рождения', $passport['birth-place'] ?? '');
        $c .= $this->field('СНИЛС', $passport['SNILS'] ?? '');
        $c .= $this->field('Пол', $this->translate('gender', $passport['gender'] ?? ''));
        $c .= "\n";

        $c .= "АДРЕСА:\n";
        $c .= $this->field('Фактический адрес', $data['actual-address']['address-name'] ?? '');
        $c .= $this->field('Адрес регистрации', $data['registration-address']['address-name'] ?? '');
        $c .= "\n";

        $application = $data['application'] ?? [];
        $c .= "ПАРАМЕТРЫ ЗАЯВКИ:\n";
        $c .= $this->field('Запрашиваемая сумма', ($application['amount'] ?? 0) . ' руб.');
        $c .= $this->field('Образование', $this->translate('education', $application['education'] ?? ''));
        $c .= $this->field('Семейное положение', $this->translate('marital-status', $application['marital-status'] ?? ''));
        $c .= $this->field('Количество детей', $application['number-of-children'] ?? '');
        $c .= $this->field('Ежемесячные платежи по кредитам', ($application['loan-payments'] ?? 0) . ' руб.');
        $c .= $this->field('СНИЛС (из заявки)', $application['snils'] ?? '');
        $c .= $this->field('ИНН клиента', $application['client-inn'] ?? '');
        $c .= "\n";

        $employment = $data['employment'] ?? [];
        $c .= "ЗАНЯТОСТЬ И ДОХОД:\n";
        $c .= $this->field('Работодатель', $employment['organization-name'] ?? '');
        $c .= $this->field('Должность', $employment['employment-position'] ?? '');
        $c .= $this->field('Тип занятости', $this->translate('employment-type', $employment['employment-type'] ?? ''));
        $c .= $this->field('Тип должности', $this->translate('employment-position-type', $employment['employment-position-type'] ?? ''));
        $c .= $this->field('Ежемесячный доход', ($employment['monthly-income'] ?? 0) . ' руб.');
        $c .= $this->field('Общий стаж (мес.)', $employment['experience'] ?? '');
        $c .= $this->field('Дата начала работы', $employment['start-last-employment-date'] ?? '');
        $c .= $this->field('Адрес работы', $employment['organization-address'] ?? '');
        $c .= $this->field('Рабочий телефон', $employment['phone'] ?? '');
        $c .= $this->field('ИНН работодателя', $employment['inn'] ?? '');
        $c .= "\n";

        $pledges = $data['pledges'] ?? [];
        $c .= "ИНФОРМАЦИЯ О ЗАЛОГЕ:\n";
        $c .= $this->field('Тип залога', $this->translate('pledgeType', $pledges['pledgeType'] ?? ''));
        $c .= $this->field('Площадь залога', ($pledges['pledgeSize'] ?? '') . ' м²');
        $c .= $this->field('Адрес залога', $pledges['pledgeAddress']['address-name'] ?? '');
        $c .= "\n";

        $coBorrower = $data['coBorrower'] ?? [];
        if (!empty($coBorrower['first-name'])) {
            $c .= "ДАННЫЕ СОЗАЕМЩИКА:\n";
            $c .= $this->field('ФИО', trim(implode(' ', [
                $coBorrower['last-name'] ?? '',
                $coBorrower['first-name'] ?? '',
                $coBorrower['middle-name'] ?? '',
            ])));
            $c .= $this->field('Учитывать доход', ($coBorrower['profitFlag'] ?? false) ? 'Да' : 'Нет');
            $c .= $this->field('Дата рождения', $coBorrower['birth-day'] ?? '');
            $c .= $this->field('Пол', $this->translate('gender', $coBorrower['gender'] ?? ''));
            $c .= $this->field('СНИЛС', $coBorrower['SNILS'] ?? '');
            $c .= $this->field('Серия/Номер паспорта', trim(($coBorrower['series'] ?? '') . ' ' . ($coBorrower['number'] ?? '')));
            $c .= $this->field('Код подразделения', $coBorrower['division-code'] ?? '');
            $c .= $this->field('Кем выдан', $coBorrower['issued-by'] ?? '');
            $c .= $this->field('Дата выдачи', $coBorrower['issue-date'] ?? '');
            $c .= $this->field('Место рождения', $coBorrower['birth-place'] ?? '');
            $c .= "\n";
        }

        $c .= "СЛУЖЕБНАЯ ИНФОРМАЦИЯ:\n";
        $c .= $this->field('Juicy Token (антифрод)', $data['juicy-token'] ?? '');
        $c .= "\n";

        return $c;
    }

    /**
     * Форматирует строку комментария; пустые значения пропускаются.
     */
    private function field(string $label, mixed $value): string
    {
        if ($value === '' || $value === null || $value === [] || $value === false) {
            return '';
        }

        return "  • {$label}: {$value}\n";
    }

    /**
     * Переводит значение поля по словарю.
     */
    public function translate(string $fieldKey, mixed $value): string
    {
        $value = (string)$value;
        $translations = self::translations();

        if (isset($translations[$fieldKey][$value])) {
            return $translations[$fieldKey][$value];
        }

        return $value;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function translations(): array
    {
        return [
            'gender' => [
                'male' => 'Мужской',
                'female' => 'Женский',
            ],
            'education' => [
                'higher' => 'Высшее',
                'two-higher' => 'Два высших',
                'primary' => 'Начальное',
                'incomplete-higher' => 'Неполное высшее',
                'secondary' => 'Среднее',
                'academic-degree' => 'Ученая степень',
            ],
            'marital-status' => [
                'single' => 'Не в браке',
                'widower' => 'Вдовец/вдова',
                'married' => 'В браке',
                'divorced' => 'В разводе',
                'civil-marriage' => 'Гражданский брак',
            ],
            'pledgeType' => [
                'FLAT' => 'Квартира',
                'HOUSE' => 'Дом',
                'LAND' => 'Земельный участок',
                'FORCARS' => 'Гараж',
                'APARTMENT' => 'Апартаменты',
                'COMMERCIAL' => 'Коммерческая недвижимость',
            ],
            'employment-type' => [
                'not-work' => 'Не работает',
                'informal-work' => 'Неофициальное трудоустройство',
                'pensioner' => 'Пенсионер',
                'work-in-organization' => 'Работа в организации',
                'individual-entrepreneur' => 'Индивидуальный предприниматель',
                'self-employed' => 'Самозанятый',
            ],
            'employment-position-type' => [
                'not-working' => 'Не работает',
                'owner' => 'Владелец',
                'unskilled-worker' => 'Неквалифицированный работник',
                'specialist' => 'Специалист',
                'higher-management-level' => 'Высшее руководство',
                'head-of-division' => 'Руководитель подразделения',
            ],
            'status' => [
                'DUPLICATE' => 'Дубликат',
                'PROCESSING' => 'В обработке',
                'DECLINED' => 'Отклонено',
                'APPROVED' => 'Одобрено',
                'PRE-APPROVED' => 'Предварительно одобрено',
                'ISSUED' => 'Выдано',
            ],
        ];
    }
}
