<?php
function calculate_cost(array $items) {
    $total = 0;
    foreach ($items as $item) {
        $total += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
    }
    return $total;
}
