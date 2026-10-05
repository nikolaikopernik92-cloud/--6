<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Almaty');

/* ---------- Вспомогательные функции ---------- */

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postString(string $key): ?string
{
    $value = $_POST[$key] ?? null;
    return is_string($value) ? $value : null;
}

/* ---------- Функции валидации (каждая возвращает текст ошибки или null) ---------- */

function validateFullName(string $value): ?string
{
    if ($value === '') {
        return 'Укажите ФИО.';
    }
    $length = mb_strlen($value);
    if ($length < 3) {
        return 'ФИО должно содержать не менее 3 символов.';
    }
    if ($length > 100) {
        return 'ФИО не должно превышать 100 символов.';
    }
    return null;
}

function validateCourse(string $value): ?string
{
    if ($value === '') {
        return 'Укажите курс.';
    }
    $course = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 6],
    ]);
    return $course === false ? 'Курс должен быть целым числом от 1 до 6.' : null;
}

function validateDate(string $value): ?string
{
    if ($value === '') {
        return 'Выберите дату визита.';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    // Формат должен совпасть полностью (отсекает 2026-02-31 и подобное)
    if ($date === false || $date->format('Y-m-d') !== $value) {
        return 'Введите корректную дату в формате ГГГГ-ММ-ДД.';
    }
    // Бизнес-правило: дата не раньше текущей
    if ($date < new DateTimeImmutable('today')) {
        return 'Дата визита не может быть раньше сегодняшней.';
    }
    return null;
}

/** @param list<string> $allowed */
function validateAllowed(string $value, array $allowed, string $message): ?string
{
    return in_array($value, $allowed, true) ? null : $message;
}

function validateReason(string $value): ?string
{
    $length = mb_strlen($value);
    if ($length === 0) {
        return 'Опишите причину обращения.';
    }
    if ($length < 10 || $length > 300) {
        return 'Причина должна содержать от 10 до 300 символов (сейчас ' . $length . ').';
    }
    return null;
}

/* ---------- Разрешённые значения (allow-list) ---------- */

$allowedSlots = ['09:00-10:00', '10:00-11:00', '11:00-12:00', '14:00-15:00', '15:00-16:00'];
$allowedVisitTypes = ['first' => 'Первичное', 'repeat' => 'Повторное'];

/* ---------- Состояние ---------- */

$values = [
    'full_name'  => '',
    'course'     => '',
    'date'       => '',
    'slot'       => '',
    'visit_type' => '',
    'reason'     => '',
    'agreement'  => false,
];
$errors = [];
$success = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Извлечение (проверка типа) + нормализация
    $values['full_name']  = trim(postString('full_name') ?? '');
    $values['course']     = trim(postString('course') ?? '');
    $values['date']       = trim(postString('date') ?? '');
    $values['slot']       = postString('slot') ?? '';
    $values['visit_type'] = postString('visit_type') ?? '';
    $values['reason']     = trim(str_replace("\r\n", "\n", postString('reason') ?? ''));
    $values['agreement']  = postString('agreement') === '1';

    // Валидация
    $checks = [
        'full_name'  => validateFullName($values['full_name']),
        'course'     => validateCourse($values['course']),
        'date'       => validateDate($values['date']),
        'slot'       => validateAllowed($values['slot'], $allowedSlots, 'Выберите временной интервал из списка.'),
        'visit_type' => validateAllowed($values['visit_type'], array_keys($allowedVisitTypes), 'Выберите вид обращения.'),
        'reason'     => validateReason($values['reason']),
        'agreement'  => $values['agreement'] ? null : 'Подтвердите согласие на обработку данных.',
    ];
    foreach ($checks as $field => $error) {
        if ($error !== null) {
            $errors[$field] = $error;
        }
    }
    $success = $errors === [];
}

function errorBlock(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="error" id="err-' . h($field) . '" role="alert">' . h($errors[$field]) . '</p>';
}

function invalidAttr(array $errors, string $field): string
{
    return isset($errors[$field])
        ? ' aria-invalid="true" aria-describedby="err-' . h($field) . '"'
        : '';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Запись в медицинский пункт</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f6f8; margin: 0; }
        main { max-width: 560px; margin: 2rem auto; background: #fff; padding: 1.5rem 2rem; border-radius: 8px; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input[type=text], input[type=number], input[type=date], select, textarea {
            width: 100%; box-sizing: border-box; padding: .5rem; margin-top: .25rem; font: inherit;
        }
        fieldset { margin-top: 1rem; border: 1px solid #ccc; }
        fieldset label, label.inline { display: inline-block; font-weight: normal; margin-right: 1rem; }
        .error { color: #b00020; margin: .25rem 0 0; font-size: .9rem; }
        [aria-invalid=true] { border: 2px solid #b00020; }
        button { margin-top: 1.5rem; padding: .6rem 1.5rem; font: inherit; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <h1>Запись в медицинский пункт</h1>

    <?php if ($success): ?>
        <p>Заявка принята.</p>
        <ul>
            <li>Студент: <?= h($values['full_name']) ?>, курс <?= h($values['course']) ?></li>
            <li>Дата: <?= h($values['date']) ?>, время: <?= h($values['slot']) ?></li>
            <li>Вид обращения: <?= h($allowedVisitTypes[$values['visit_type']]) ?></li>
            <li>Причина: <?= h($values['reason']) ?></li>
        </ul>
    <?php else: ?>
        <form method="post" action="" novalidate>
            <label for="full_name">ФИО</label>
            <input type="text" id="full_name" name="full_name" maxlength="100"
                   value="<?= h($values['full_name']) ?>" required<?= invalidAttr($errors, 'full_name') ?>>
            <?= errorBlock($errors, 'full_name') ?>

            <label for="course">Курс (1-6)</label>
            <input type="number" id="course" name="course" min="1" max="6"
                   value="<?= h($values['course']) ?>" required<?= invalidAttr($errors, 'course') ?>>
            <?= errorBlock($errors, 'course') ?>

            <label for="date">Дата визита</label>
            <input type="date" id="date" name="date" min="<?= h(date('Y-m-d')) ?>"
                   value="<?= h($values['date']) ?>" required<?= invalidAttr($errors, 'date') ?>>
            <?= errorBlock($errors, 'date') ?>

            <label for="slot">Временной интервал</label>
            <select id="slot" name="slot" required<?= invalidAttr($errors, 'slot') ?>>
                <option value="">Выберите интервал</option>
                <?php foreach ($allowedSlots as $item): ?>
                    <option value="<?= h($item) ?>" <?= $values['slot'] === $item ? 'selected' : '' ?>>
                        <?= h($item) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= errorBlock($errors, 'slot') ?>

            <fieldset>
                <legend>Вид обращения</legend>
                <?php foreach ($allowedVisitTypes as $key => $label): ?>
                    <label>
                        <input type="radio" name="visit_type" value="<?= h($key) ?>"
                            <?= $values['visit_type'] === $key ? 'checked' : '' ?>>
                        <?= h($label) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?= errorBlock($errors, 'visit_type') ?>

            <label for="reason">Причина обращения (10-300 символов)</label>
            <textarea id="reason" name="reason" rows="4" maxlength="300"
                      required<?= invalidAttr($errors, 'reason') ?>><?= h($values['reason']) ?></textarea>
            <?= errorBlock($errors, 'reason') ?>

            <label class="inline">
                <input type="checkbox" name="agreement" value="1" <?= $values['agreement'] ? 'checked' : '' ?>>
                Согласен на обработку персональных данных
            </label>
            <?= errorBlock($errors, 'agreement') ?>

            <button type="submit">Записаться</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>