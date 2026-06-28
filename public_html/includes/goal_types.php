<?php
$GOAL_TYPES = [
    1 => ['key' => 'golden_loan', 'name' => 'وام طلایی ثنا', 'unit' => 'gram', 'icon' => '💰', 'sort' => 1],
    2 => ['key' => 'installment_sale', 'name' => 'فروش قسطی طلا', 'unit' => 'gram', 'icon' => '📦', 'sort' => 2],
    3 => ['key' => 'resalat_loan', 'name' => 'وام رسالت', 'unit' => 'million_rial', 'icon' => '🏦', 'sort' => 3],
    4 => ['key' => 'nik_card_loan', 'name' => 'وام نیک کارت', 'unit' => 'million_rial', 'icon' => '💳', 'sort' => 4],
    5 => ['key' => 'atiyeh_gold', 'name' => 'حساب آتیه طلا', 'unit' => 'gram', 'icon' => '⭐', 'sort' => 5],
    6 => ['key' => 'monthly_trade', 'name' => 'معاملات ماهانه', 'unit' => 'gram', 'icon' => '🔄', 'sort' => 6],
    7 => ['key' => 'atiyeh_rial_loan', 'name' => 'وام آتیه ریالی', 'unit' => 'million_rial', 'icon' => '💰', 'sort' => 7]
];

function getGoalTypes() {
    global $GOAL_TYPES;
    return $GOAL_TYPES;
}

function getGoalById($id) {
    global $GOAL_TYPES;
    return isset($GOAL_TYPES[$id]) ? $GOAL_TYPES[$id] : null;
}
?>