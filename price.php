#!/usr/bin/php
<?php

$start = microtime(true); // Время начала выполнения скрипта

define('INPUT_FILE','Мерлион.csv');

define('OUT_FILE', 'Merlion_out.csv');

define('COLOR_LIST', 'colors.txt');

define('LOG_FILE', 'log.csv');

define('STRLN', '1');

define('WORD_FILE', 'dictionary.txt');

define('STATISTIC_ON', true);

// Счетчики

$count = array(
	'uppercase' => '0',
	'color' => '0',
	'space' => '0');


$input_file = file(INPUT_FILE);

$output_file = fopen(OUT_FILE, 'w');

$log_file = fopen(LOG_FILE, 'w');

$exclusion_word = file(WORD_FILE);

$color_list = file(COLOR_LIST);

$edit_string = '';

// это нужно для отображения текущего состояния обработки
$max_price_lines = max(array_keys($input_file));

// Быстрая чистка прайса - убираем все заглавные буквы там где их не должно быть
foreach($input_file as $line => $string)
{
	$string = iconv('CP1251','UTF-8', $string);

	// Разбор строки на стобцы
	$array_by_column = explode(';', $string);
	
	// Убираем лишние пробелы
	$array_by_column[1] = trim($array_by_column[1]);
	
	$array_by_column[1] = str_replace('"', '', $array_by_column[1]);

	// Разбор столбца названия на слова
	$array_by_word = explode(' ', $array_by_column[1]);
	
	// обработка слов
	foreach($array_by_word as $index => $str) 
	{
		if (0 == $index)
		{
			// Первая буква заглавная
			$array_by_word[0] = mb_convert_case($array_by_word[0], MB_CASE_TITLE, 'UTF-8');
		}
		elseif (0 < $index)
		{
			// Строки меньше STRLN не изменяются, а только с заглавными русскими 
			// и где нет английских букв и цифр (это скорее всего параметры)  
			if ((STRLN < strlen($str)) && (preg_match("/[А-Я]/u", $str)) && (!preg_match("/[0-9A-Za-z]/u", $str)))
			{
				$array_by_word[$index] = mb_convert_case($array_by_word[$index], MB_CASE_LOWER, 'UTF-8');
				
				$log = substr($string, 0, strlen($string)-1).' --> ;'.$array_by_word[$index].";Автоматически\n";
				$log = iconv('UTF-8','CP1251', $log);
				fputs($log_file, $log);
				
				//$count_uppercase += 1;
			}
		}
	}
	// Сливаем все части обратно в строку
	$ready_string = $array_by_column[0].';';
	foreach($array_by_word as $value)
	{
		$ready_string .= $value.' ';
	}
	$ready_string = trim($ready_string);
	$ready_string .= ';'.$array_by_column[2].';'.$array_by_column[3];
	$ready_string = iconv('UTF-8','CP1251', $ready_string);
			
	$input_file[$line] = $ready_string;
	
	// Выводим в процентах состояние обработки
	if (STATISTIC_ON)
	{	
		$result = (100 / $max_price_lines) * $line;
		$result = (int)$result;
		echo 'Быстрая чистка прайса: '.$result.' %'."\r";
	}
}
if (STATISTIC_ON) {echo 'Быстрая чистка прайса: 100 %'."\n";}

// Чистка прайса по ключевым словам
foreach($input_file as $line => $string)
{
	$string = iconv('CP1251','UTF-8', $string);

	// Разбор строки на стобцы
	$array_by_column = explode(';', $string);
	
	// Убираем лишние пробелы
	$array_by_column[1] = trim($array_by_column[1]);
	
	// Убираем лишние одинарные и двойные ковычки
	$array_by_column[1] = preg_replace('/["\']/', '', $array_by_column[1]);
	
	// обработка каждой строки на наличие слов-исключений и их исправление
	foreach($exclusion_word as $exc_word)
	{	
		$array_word_for_replace = PatternCreater($exc_word);

		if (empty($array_word_for_replace)) {exit;}
		
		foreach($array_word_for_replace as $key => $word)
		{
			$pattern = '/'.$array_word_for_replace[$key].'/iu';
			
			// Если слово написано с ошибкой - исправляем
			if (preg_match($pattern, $array_by_column[1], $sub_string))
			{
				// Если слово написано с ошибкой - исправляем
				foreach($sub_string as $test)
				{
					if ($test !== $exc_word)
					{
						$raplace_string = ' '.$array_word_for_replace[0].' '; // прослойка для вставки пробелов между словами исключениями
						$edit_string = preg_replace($pattern, $raplace_string, $array_by_column[1], -1, $number_of_changes);
						if (0 < $number_of_changes)
						{
							$array_by_column[1] = $edit_string;
							// Запись в логи
							$log = substr($string, 0, strlen($string)-1).' --> ;'.$exc_word;
							$log = iconv('UTF-8','CP1251', $log);
							fputs($log_file, $log);
							
							$count['uppercase'] += 1;
						}
						else
						{
							$log = substr($string, 0, strlen($string)-1).' --> ;'.$exc_word;
							$log = iconv('UTF-8','CP1251', $log);
							fputs($log_file, $log);
						}
					}
				}
			}
		}
	}
	
	
	// Сохранение строки обработанного прайса
	$array_by_column[1] = trim($array_by_column[1]);
	$output_string = $array_by_column[0].';'.$array_by_column[1].';'.$array_by_column[2].';'.$array_by_column[3];
	$input_file[$line] = $output_string;

	// Выводим в процентах состояние обработки
	if (STATISTIC_ON)
	{
		$result = (100 / $max_price_lines) * $line;
		$result = (int)$result;
		echo 'Исправление по ключевым словам: '.$result.' %'."\r";
	}
}
if (STATISTIC_ON) {echo 'Исправление по ключевым словам: 100 %'."\n";}

//-----------------------------------------------------------

// Перемещаем цвет в конец строки
foreach($input_file as $line => $string)
{
	// Разбор строки на стобцыMFU
	$array_by_column = explode(';', $string);
	
	foreach($color_list as $color)
	{
		$color = trim($color);
		
		$pattern = '/ '.$color.' /u';
		
		preg_match($pattern, $array_by_column[1], $result, PREG_OFFSET_CAPTURE);
		
		if (!empty($result))
		{
			$color_string_length = strlen($color);
			
			$string_length = strlen($array_by_column[1]);

			if($result[0][1] < ($string_length - $color_string_length))
			{
				$array_by_column[1] = preg_replace($pattern, '', $array_by_column[1], -1, $number_of_changes);
				$array_by_column[1] .= ' '.$color;
				
				// Сохранение строки обработанного прайса
				$output_string = $array_by_column[0].';'.$array_by_column[1].';'.$array_by_column[2].';'.$array_by_column[3];
				$input_file[$line] = $output_string;
				
				// Запись в логи
				$log = substr($string, 0, strlen($string)-1).' --> ;'.$color."\n";
				$log = iconv('UTF-8','CP1251', $log);
				fputs($log_file, $log);

				// Подсчет ошибок
				$count['color'] += 1;
			}
		}
	}
	// Выводим в процентах состояние обработки
	if (STATISTIC_ON)
	{
		$result_color = (100 / $max_price_lines) * $line;
		$result_color = (int)$result_color;
		echo 'Перемещение названий цветов в конец строки: '.$result_color.' %'."\r";
	}
}
if (STATISTIC_ON) {echo 'Перемещение названий цветов в конец строки: 100 %'."\n";}

// Удаление двойных пробелов
foreach($input_file as $line => $string)
{
	$pattern = '/ {2,}/';
	$raplace_atring = ' ';
	$input_file[$line] = preg_replace($pattern, $raplace_atring, $string, -1, $result);
	if (0 < $result)
	{
		$count['space'] += 1;
		$result = NULL;
	}

	// Выводим в процентах состояние обработки
	if (STATISTIC_ON)
	{
		$result_space = (100 / $max_price_lines) * $line;
		$result_space = (int)$result_space;
		echo 'Удаление двойных пробелов: '.$result_space.' %'."\r";
	}
}
if (STATISTIC_ON) {echo 'Удаление двойных пробелов: 100 %'."\n";}

// Сохраняем в файл
foreach ($input_file as $output_string)
{
	$output_string = iconv('UTF-8', 'CP1251', $output_string);
	fputs($output_file, $output_string);
}

// Статистика выполнения скрипта
$time = microtime(true) - $start;
if (STATISTIC_ON)
{
	echo '-------------------------------------'."\n";
	echo 'Исправлено названий: '.$count['uppercase']."\n";
	echo 'Исправлено цветов: '.$count['color']."\n";
	echo 'Удалено лишних пробелов: '.$count['space']."\n";
	$sum_count = $count['uppercase'] + $count['color'] + $count['space'];
	echo 'Всего исправлено: '.$sum_count."\n";
	printf('Скрипт выполнялся %.4F сек.', $time);
}
//--function--

function PatternCreater($arg)
{
	$array = explode(',', $arg);
	foreach ($array as $key => $value)
	{
		if (!empty($value))
		{
			$value = trim($value);
			// Замена плюсов
			$array[$key] = str_replace('+', ' ', $value);
		}
		else
		{
			unset($array[$key]);
		}
	}
	return $array;
}

?> 
