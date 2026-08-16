<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<?php
// === ПД-4 + максимальный QR-код (ГОСТ) для ИП ===
// Значения по умолчанию — демо-пример, замените своими реквизитами.

if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} elseif (file_exists('tcpdf/tcpdf.php')) {
    require_once 'tcpdf/tcpdf.php';
} else {
    die('TCPDF не найден. Скачайте с https://github.com/tecnickcom/TCPDF и поместите в папку tcpdf.');
}

$defaults = [
    'payee_name'    => 'ИП Иванов Иван Иванович',
    'payee_inn'     => '123456789012',
    'payee_kpp'     => '000000000',
    'ogrnip'        => '316500100000000',
    'oktmo'         => '00000000',            // ОКТМО
    'account'       => '40702810000000000000',
    'bank_name'     => 'ООО «БАНК»',
    'bank_bic'      => '000000000',
    'corr_account'  => '30101810000000000000',
    'kbk'           => '18210803010011000110',
    'uin'           => '0',
    'purpose'       => 'Оплата по договору № 1 от 01.01.2026',
    'payer_name'    => 'Иванов Иван Иванович',
    'payer_address' => 'г. Москва, ул. Примерная, д. 1, кв. 1',
    'payer_ls'      => '40817810000000000000',
    'payer_snils'   => '000-000-000 00',
    'amount_rub'    => '1000',
    'amount_kop'    => '00',
    'doc_date'      => '01.01.2026',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    foreach ($defaults as $k => $v) {
        $data[$k] = trim($_POST[$k] ?? $v);
    }

    $rub = (int)str_replace([' ', ','], '', $data['amount_rub']);
    $kop = (int)str_replace([' ', ','], '', $data['amount_kop']);
    $total_kop = $rub * 100 + $kop;

	$qrString = "ST00012|"
	    . "Name=" . $data['payee_name'] . "|"
	    . "PayeeINN=" . $data['payee_inn'] . "|"
	    . "KPP=" . $data['payee_kpp'] . "|"
	    . "OGRNIP=" . $data['ogrnip'] . "|"
	    . "OKTMO=" . $data['oktmo'] . "|"
	    . "KBK=" . $data['kbk'] . "|"
	    . "UIN=" . $data['uin'] . "|"
	    . "PersonalAcc=" . $data['account'] . "|"
	    . "BankName=" . $data['bank_name'] . "|"
	    . "BIC=" . $data['bank_bic'] . "|"
	    . "CorrespAcc=" . $data['corr_account'] . "|"
	    . "Sum=" . $total_kop . "|"
	    . "Purpose=" . $data['purpose'] . "|"
	    . "AddInfo=" . str_repeat('A', 250);   // 250 символов

    // PDF
    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->SetFont('freesans', '', 8);

    $endTopY = drawSection($pdf, 20, 'ИЗВЕЩЕНИЕ', $data);
    $pdf->SetLineStyle(['dash' => '2,2']);
    $pdf->Line(10, $endTopY + 5, 200, $endTopY + 5);

    $startBottomY = $endTopY + 10;
    $endBottomY = drawSection($pdf, $startBottomY, 'КВИТАНЦИЯ', $data);

    // QR-код 100×100 мм
    $qrSize = 100;
    $qrX = (210 - $qrSize) / 2;
    $qrY = $endBottomY + 10;
    $style = ['border' => false, 'padding' => 0, 'fgcolor' => [0,0,0], 'bgcolor' => false];
	$pdf->write2DBarcode($qrString, 'QRCODE,H', $qrX, $qrY, $qrSize, $qrSize, $style, 'N');

    $pdf->SetXY($qrX, $qrY + $qrSize + 2);
    $pdf->SetFont('freesans', '', 7);
    $pdf->Cell($qrSize, 4, 'QR-код для оплаты', 0, 1, 'C');

    $pdf->Output('PD4_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}

function drawSection($pdf, $startY, $title, $data) {
    $leftW = 30; $tableX = 40; $tableW = 140; $lh = 5;
    $sumStr = number_format((int)$data['amount_rub'], 0, ',', ' ') . ' руб. ' . $data['amount_kop'] . ' коп.';

    $rows = [
        ['Получатель:', $data['payee_name']],
        ['ИНН / КПП:', $data['payee_inn'] . ' / ' . $data['payee_kpp']],
        ['ОГРНИП:', $data['ogrnip'] . '   Счёт: ' . $data['account']],
        ['ОКТМО:', $data['oktmo'] . '   Банк: ' . $data['bank_name']],
        ['БИК:', $data['bank_bic'] . '   Кор./сч.: ' . $data['corr_account']],
        ['КБК:', $data['kbk'] . '   УИН: ' . $data['uin']],
        ['Назначение:', $data['purpose']],
        ['Плательщик:', $data['payer_name']],
        ['Адрес:', $data['payer_address']],
        ['Лиц. счёт:', $data['payer_ls'] . '   СНИЛС: ' . $data['payer_snils']],
        ['Сумма:', $sumStr],
        ['Дата:', $data['doc_date'] . '   Подпись: ______________________'],
    ];

    $lineHeights = [];
    $totalHeight = 0;
    foreach ($rows as $row) {
        $h = ($row[0] == 'Назначение:') ? $lh * 3 : $lh;
        $lineHeights[] = $h;
        $totalHeight += $h;
    }

    $pdf->Rect(10, $startY, $leftW, $totalHeight, 'D');
    $pdf->SetFont('freesans', 'B', 10);
    $pdf->SetXY(10, $startY + 3);
    $pdf->Cell($leftW, 6, $title, 0, 1, 'C');
    $pdf->SetFont('freesans', '', 8);
    $pdf->SetXY(10, $startY + $totalHeight/2 - 3);
    $pdf->Cell($leftW, 6, 'Кассир', 0, 1, 'C');
    $pdf->SetXY(10, $startY + $totalHeight - 8);
    $pdf->Cell($leftW, 4, '(подпись)', 0, 1, 'C');

    $pdf->SetFont('freesans', '', 7.5);
    $curY = $startY;
    for ($i = 0; $i < count($rows); $i++) {
        $text = $rows[$i][0] . ' ' . $rows[$i][1];
        $h = $lineHeights[$i];
        if ($rows[$i][0] == 'Назначение:') {
            $pdf->SetXY($tableX, $curY);
            $pdf->MultiCell($tableW, $lh, $text, 1, 'L', false, 0, '', '', true, 0, false, true, $h, 'M');
        } else {
            $pdf->SetXY($tableX, $curY);
            $pdf->Cell($tableW, $h, $text, 1, 1, 'L');
        }
        $curY += $h;
    }
    return $startY + $totalHeight;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Генератор квитанции ПД‑4 для ИП (полный QR)</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, textarea { width: 100%; padding: 5px; }
        .row { display: flex; gap: 10px; }
        .row input { flex: 1; }
        button { margin-top: 20px; padding: 10px 30px; font-size: 16px; background: #007bff; color: #fff; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h2>Заполните реквизиты для квитанции ПД‑4 (ИП)</h2>
    <form method="post">
        <label>Получатель</label>
        <input name="payee_name" value="<?= htmlspecialchars($defaults['payee_name']) ?>" required>
        <label>ИНН</label>
        <input name="payee_inn" value="<?= htmlspecialchars($defaults['payee_inn']) ?>" required>
        <label>КПП</label>
        <input name="payee_kpp" value="<?= htmlspecialchars($defaults['payee_kpp']) ?>">
        <label>ОГРНИП (15 цифр)</label>
        <input name="ogrnip" value="<?= htmlspecialchars($defaults['ogrnip']) ?>" required>
        <label>ОКТМО</label>
        <input name="oktmo" value="<?= htmlspecialchars($defaults['oktmo']) ?>">
        <label>Расчётный счёт</label>
        <input name="account" value="<?= htmlspecialchars($defaults['account']) ?>" required>
        <label>Банк</label>
        <input name="bank_name" value="<?= htmlspecialchars($defaults['bank_name']) ?>" required>
        <label>БИК</label>
        <input name="bank_bic" value="<?= htmlspecialchars($defaults['bank_bic']) ?>" required>
        <label>Корреспондентский счёт</label>
        <input name="corr_account" value="<?= htmlspecialchars($defaults['corr_account']) ?>" required>
        <label>КБК</label>
        <input name="kbk" value="<?= htmlspecialchars($defaults['kbk']) ?>">
        <label>УИН</label>
        <input name="uin" value="<?= htmlspecialchars($defaults['uin']) ?>">
        <label>Назначение платежа</label>
        <textarea name="purpose" rows="2" required><?= htmlspecialchars($defaults['purpose']) ?></textarea>
        <label>ФИО плательщика</label>
        <input name="payer_name" value="<?= htmlspecialchars($defaults['payer_name']) ?>" required>
        <label>Адрес плательщика</label>
        <input name="payer_address" value="<?= htmlspecialchars($defaults['payer_address']) ?>">
        <label>Лицевой счёт плательщика</label>
        <input name="payer_ls" value="<?= htmlspecialchars($defaults['payer_ls']) ?>">
        <label>СНИЛС</label>
        <input name="payer_snils" value="<?= htmlspecialchars($defaults['payer_snils']) ?>">
        <label>Сумма</label>
        <div class="row">
            <input name="amount_rub" value="<?= htmlspecialchars($defaults['amount_rub']) ?>" placeholder="Рубли" required>
            <input name="amount_kop" value="<?= htmlspecialchars($defaults['amount_kop']) ?>" placeholder="Копейки" required>
        </div>
        <label>Дата составления</label>
        <input name="doc_date" value="<?= htmlspecialchars($defaults['doc_date']) ?>" required>
        <button type="submit">📄 Сгенерировать PDF с QR‑кодом</button>
    </form>
</body>
</html>