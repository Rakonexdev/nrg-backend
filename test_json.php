<?php
$str = file_get_contents('postman_collection_full.json');
echo "Length: " . strlen($str) . "\n";
$arr = json_decode($str, true);
echo "JSON Error: " . json_last_error_msg() . "\n";
if (is_array($arr) && isset($arr['item'])) {
    echo "Item count: " . count($arr['item']) . "\n";
} else {
    echo "NO ITEMS ARRAY\n";
}
