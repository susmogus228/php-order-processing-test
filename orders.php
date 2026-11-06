<?php

declare(strict_types=1);

const SAMPLE_JSON = <<<'JSON'
[
    {"id": 101, "customer": "Иван", "amount": 1500, "status": "paid"},
    {"id": 102, "customer": "Мария", "amount": 2500, "status": "pending"},
    {"id": 103, "customer": "Пётр", "amount": 3000, "status": "paid"},
    {"id": 104, "customer": "Анна", "amount": 0, "status": "paid"}
]
JSON;

/** Decode and validate all orders before calculating anything. */
function getOrders(string $json): array
{
    try {
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new InvalidArgumentException('Некорректный JSON: ' . $exception->getMessage(), 0, $exception);
    }

    if (!is_array($decoded)) {
        throw new InvalidArgumentException('В корне JSON должен быть массив заказов.');
    }

    $orders = [];
    foreach ($decoded as $index => $order) {
        $position = $index + 1;
        if (!is_object($order)) {
            throw new InvalidArgumentException("Заказ #{$position}: ожидается объект.");
        }

        $data = get_object_vars($order);
        foreach (['id', 'customer', 'amount', 'status'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Заказ #{$position}: отсутствует поле {$field}.");
            }
        }

        if (!is_int($data['id']) || $data['id'] <= 0) {
            throw new InvalidArgumentException("Заказ #{$position}: id должен быть положительным целым числом.");
        }
        if (!is_string($data['customer']) || trim($data['customer']) === '') {
            throw new InvalidArgumentException("Заказ #{$position}: customer должен быть непустой строкой.");
        }
        $amount = $data['amount'];
        if ((!is_int($amount) && !is_float($amount)) || !is_finite((float) $amount) || $amount < 0) {
            throw new InvalidArgumentException("Заказ #{$position}: amount должен быть конечным неотрицательным числом.");
        }
        if (!is_string($data['status']) || !in_array($data['status'], ['paid', 'pending'], true)) {
            throw new InvalidArgumentException("Заказ #{$position}: неизвестный статус; допустимы paid и pending.");
        }
        $orders[] = $data;
    }

    return $orders;
}

function getPaidOrders(array $orders): array
{
    return array_values(array_filter($orders, static fn (array $order): bool => $order['status'] === 'paid'));
}

function calculateTotal(array $orders): int|float
{
    $total = 0;
    foreach ($orders as $order) {
        $total += $order['amount'];
        if (!is_finite((float) $total)) {
            throw new InvalidArgumentException('Общая сумма превышает допустимый диапазон чисел.');
        }
    }
    return $total;
}

function getMostExpensiveOrder(array $orders): ?array
{
    $mostExpensive = null;
    foreach ($orders as $order) {
        if ($mostExpensive === null || $order['amount'] > $mostExpensive['amount']) {
            $mostExpensive = $order;
        }
    }
    return $mostExpensive;
}

/** Additional task B: count all validated orders by status. */
function countByStatus(array $orders): array
{
    $counts = ['paid' => 0, 'pending' => 0];
    foreach ($orders as $order) {
        $counts[$order['status']]++;
    }
    return $counts;
}

try {
    if ($argc > 2) {
        throw new InvalidArgumentException('Использование: php orders.php [путь к JSON-файлу]');
    }

    $json = SAMPLE_JSON;
    if ($argc === 2) {
        $json = @file_get_contents($argv[1]);
        if ($json === false) {
            throw new InvalidArgumentException('Не удалось прочитать JSON-файл: ' . $argv[1]);
        }
    }

    $orders = getOrders($json);
    $paidOrders = getPaidOrders($orders);
    $total = calculateTotal($paidOrders);
    $mostExpensive = getMostExpensiveOrder($paidOrders);
    $statusCounts = countByStatus($orders);

    echo 'Количество оплаченных заказов: ' . count($paidOrders) . PHP_EOL;
    echo 'Общая сумма оплаченных заказов: ' . $total . PHP_EOL;
    if ($mostExpensive === null) {
        echo 'Самый дорогой оплаченный заказ: отсутствует' . PHP_EOL;
    } else {
        echo 'Самый дорогой оплаченный заказ: № ' . $mostExpensive['id'] . ' на сумму ' . $mostExpensive['amount'] . PHP_EOL;
    }
    echo 'Заказов по статусам: paid — ' . $statusCounts['paid'] . ', pending — ' . $statusCounts['pending'] . PHP_EOL;
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'Ошибка: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
