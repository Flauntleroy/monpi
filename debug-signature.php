<?php

// Test different signature implementations for BPJS
$consid = "21095";
$secretkey = "468C5DFE8E9";
$userkey = "bdc202481c6658e7c8b25ac7ee65c7dc";

date_default_timezone_set('UTC');
$tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));

echo "🔍 Testing Different Signature Implementations\n";
echo "============================================\n";
echo "Consumer ID: " . $consid . "\n";
echo "Secret Key: " . $secretkey . "\n";
echo "User Key: " . $userkey . "\n";
echo "Timestamp: " . $tStamp . "\n\n";

// Test 1: Current implementation (Laravel style)
$data1 = $consid . '&' . $tStamp;
$signature1 = base64_encode(hash_hmac('sha256', $data1, $secretkey, true));
echo "Method 1 (Current Laravel): " . $signature1 . "\n";
echo "Data: " . $data1 . "\n\n";

// Test 2: Without ampersand
$data2 = $consid . $tStamp;
$signature2 = base64_encode(hash_hmac('sha256', $data2, $secretkey, true));
echo "Method 2 (No Ampersand): " . $signature2 . "\n";
echo "Data: " . $data2 . "\n\n";

// Test 3: Different order
$data3 = $tStamp . '&' . $consid;
$signature3 = base64_encode(hash_hmac('sha256', $data3, $secretkey, true));
echo "Method 3 (Timestamp First): " . $signature3 . "\n";
echo "Data: " . $data3 . "\n\n";

// Test 4: With pipe separator
$data4 = $consid . '|' . $tStamp;
$signature4 = base64_encode(hash_hmac('sha256', $data4, $secretkey, true));
echo "Method 4 (Pipe Separator): " . $signature4 . "\n";
echo "Data: " . $data4 . "\n\n";

// Test 5: Hex encode instead of binary
$signature5 = hash_hmac('sha256', $data1, $secretkey);
echo "Method 5 (Hex, no base64): " . $signature5 . "\n";
echo "Data: " . $data1 . "\n\n";

// Let's also test the timestamp calculation
echo "Timestamp calculations:\n";
echo "Current time: " . time() . "\n";
echo "1970-01-01 strtotime: " . strtotime('1970-01-01 00:00:00') . "\n";
echo "Difference: " . (time() - strtotime('1970-01-01 00:00:00')) . "\n";
echo "Our timestamp: " . $tStamp . "\n";

?>
