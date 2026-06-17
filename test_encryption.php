<?php
/**
 * test_encryption.php
 * Тестирование шифрования и расшифровки
 */

require_once __DIR__ . '/includes/auth.php';

echo "=== ТЕСТ ШИФРОВАНИЯ ДАННЫХ ===\n\n";

// Тест 1: Шифрование и расшифровка текста
echo "Тест 1: Шифрование и расшифровка текста\n";

$originalText = 'Конфиденциальные данные: проект "Цифровой двойник"';
echo "Оригинал: $originalText\n";

$encrypted = encryptData($originalText);
echo "Зашифровано: " . substr($encrypted, 0, 50) . "...\n";

$decrypted = decryptData($encrypted);
echo "Расшифровано: $decrypted\n";
echo "Результат: " . ($originalText === $decrypted ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n\n";

// Тест 2: Шифрование длинного текста
echo "Тест 2: Шифрование длинного текста\n";

$longText = str_repeat("Lorem ipsum dolor sit amet. ", 100);
echo "Длина оригинала: " . strlen($longText) . " символов\n";

$encrypted = encryptData($longText);
echo "Длина зашифрованного: " . strlen($encrypted) . " символов\n";

$decrypted = decryptData($encrypted);
echo "Длина расшифрованного: " . strlen($decrypted) . " символов\n";
echo "Результат: " . ($longText === $decrypted ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n\n";

// Тест 3: Шифрование пустой строки
echo "Тест 3: Шифрование пустой строки\n";

$emptyText = '';
$encrypted = encryptData($emptyText);
$decrypted = decryptData($encrypted);
echo "Результат: " . ($emptyText === $decrypted ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n\n";

// Тест 4: Шифрование с кириллицей
echo "Тест 4: Шифрование текста на кириллице\n";

$cyrillicText = 'Привет мир! Это тест шифрования. 1234567890';
$encrypted = encryptData($cyrillicText);
$decrypted = decryptData($encrypted);
echo "Результат: " . ($cyrillicText === $decrypted ? "✅ УСПЕШНО" : "❌ ОШИБКА") . "\n";

// Тест 5: Проверка на разные данные при каждом шифровании
echo "\nТест 5: Уникальность зашифрованных данных\n";

$text = 'Тестовый текст';
$encrypted1 = encryptData($text);
$encrypted2 = encryptData($text);
echo "Зашифровано 1: " . substr($encrypted1, 0, 50) . "...\n";
echo "Зашифровано 2: " . substr($encrypted2, 0, 50) . "...\n";
echo "Результат: " . ($encrypted1 !== $encrypted2 ? "✅ УСПЕШНО (разные результаты)" : "❌ ОШИБКА (одинаковые)") . "\n";
echo "Причина: используется случайный вектор инициализации (IV)\n";