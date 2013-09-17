#!/usr/bin/php
<?php

$start = microtime(true); // Время начала выполнения скрипта

$input_file = 'Мерлион.csv'; // Имя входного файла по умолчанию

$output_file = 'Merlion_finished_'.date('d-m-Y_H-i').'.csv'; // Имя выходного файла по умолчанию

$arg[] = ''; // Массив входных параметров

$procent = 0;

define('WORD_FILE', 'dictionary.txt'); // Словарь

define('COLOR_LIST', 'colors.txt'); // Файл со списком цветов

define('LOG_FILE', 'log.csv'); // Имя файла логов

define('STRLN', '2'); // Минимальная длина подстроки подверженная изменениям

define('STATISTIC_ON', true); // Вывод информации о процессе обработки. true - выводить, false - не выводить.

// Разбираем параметры
if ( 1 < $argc)
{
	foreach($argv as $index => $value)
	{
		if (!isset($count))
		{
			$count = '0';
		}
		
		if ($index != '0')
		{
			if ($value[0] != '-')
			{
				$arg[$count] = $value;		
				$count += '1';
			}
		}
	}

	// Переименовываем переменные в соответствии с введенными параметрами
	if (!empty($arg[0]))
	{
		$arg[0] = trim($arg[0]);
		$arg[0] = preg_replace("/[^\x20-\xFF]/","",@strval($arg[0]));
		$input_file = $arg[0];
	}

	if (!empty($arg[1]))
	{
		$arg[1] = trim($arg[1]);
		$arg[1] = preg_replace("/[^\x20-\xFF]/","",@strval($arg[1]));
		$output_file = $arg[1];
	}
}

// Счетчики
$count = array(
	'uppercase' => '0',
	'color' => '0',
	'space' => '0',
	'commas' => '0');

$input_file = file($input_file);

if (empty($input_file))
{
  echo "Файл не найден!\n";
  exit;
}

$log_file = fopen(LOG_FILE, 'w');

$exclusion_word = file(WORD_FILE);

$color_list = file(COLOR_LIST);

$edit_string = '';

// Получаем число строк для отображения состояния выполнения
$max_price_lines = max(array_keys($input_file));

// Быстрая чистка прайса - убираем все заглавные буквы там где их не должно быть
foreach($input_file as $line => $string)
{
	$string = iconv('CP1251','UTF-8', $string);
	
	// Если в названии есть точка-запятая она убирается
	$string = getForColumn($string);
	
	// Разбор строки на стобцы
	$array_by_column = explode(';', $string);
	
	// Убираем лишние пробелы
	$array_by_column[1] = trim($array_by_column[1]);
	
	// Удаляем двойные ковычки, которые не относятся к дюймам
	$array_by_column[1] = removeDoubleQuotes($array_by_column[1]);
	
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
				
				$log = substr($string, 0, strlen($string)-1).' --> ;'.$array_by_word[$index]."\n";
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
	//$ready_string = iconv('UTF-8','CP1251', $ready_string);

	$input_file[$line] = $ready_string;
	
	// Выводим в процентах состояние обработки
	if (STATISTIC_ON)
	{	
		$result = ShowWhatDone($line, $max_price_lines);
		
		if (isset($result))
		{
			if ($result > $procent)
			{
				$procent = $result;
				
				echo 'Быстрая чистка прайса: '.$result.' %'."\r";
			}
		}
		
	}
}

// Очистка потока вывода
echo "\n";

// Обнуление переменных
$procent = 0;

//-------------------------------------

// Чистка прайса по ключевым словам
foreach($input_file as $line => $string)
{	
	// Разбор строки на стобцы
	$array_by_column = explode(';', $string);
	
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
		// Сохранение строки обработанного прайса в массив
		$array_by_column[1] = trim($array_by_column[1]);
		$input_file[$line] = $array_by_column[0].';'.$array_by_column[1].';'.$array_by_column[2].';'.$array_by_column[3];
	}

	// Выводим в процентах состояние обработки
	if (STATISTIC_ON)
	{
		$result = ShowWhatDone($line, $max_price_lines);
		
		if (isset($result))
		{
			if ($result > $procent)
			{
				$procent = $result;
				
				echo 'Исправление по ключевым словам: '.$result.' %'."\r";
			}
		}
	}
}

// Очистка потока вывода
echo "\n";

// Обнуление переменных
$procent = 0;

//-------------------------------------

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
				$array_by_column[1] = preg_replace($pattern, ' ', $array_by_column[1], -1, $number_of_changes);
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
		$result = ShowWhatDone($line, $max_price_lines);
		
		if (isset($result))
		{
			if ($result > $procent)
			{
				$procent = $result;
				
				echo 'Перемещение названий цветов в конец строки: '.$result.' %'."\r";
			}
		}
	}
}

// Очистка потока вывода
echo "\n";

// Обнуление переменных
$procent = 0;

//-------------------------------------

// исправление положения запятых
foreach($input_file as $line => $string)
{
	$pattern = '/ *,(?![0-9])/';
	$raplace_atring = ', ';
	$input_file[$line] = preg_replace($pattern, $raplace_atring, $string, -1, $result);
	if (0 < $result)
	{
		$count['commas'] += 1;
		$result = NULL;
	}

	// Выводим в процентах состояние обработки
	if (STATISTIC_ON)
	{
		$result = ShowWhatDone($line, $max_price_lines);
		
		if (isset($result))
		{
			if ($result > $procent)
			{
				$procent = $result;
				
				echo 'Исправление положения зяпятых: '.$result.' %'."\r";
			}
		}
	}
}

// Очистка потока вывода
echo "\n";

// Обнуление переменных
$procent = 0;

//-------------------------------------

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
		$result_space = ShowWhatDone($line, $max_price_lines);
		
		if (isset($result))
		{
			if ($result > $procent)
			{
				$procent = $result;
	
				echo 'Удаление двойных пробелов: '.$result_space.' %'."\r";
			}
		}
	}
}

// Очистка потока вывода
echo "\n";

//-------------------------------------

// Сохраняем в файл
$output_file = fopen($output_file, 'w');

foreach ($input_file as $output_string)
{
	$output_string = iconv('UTF-8', 'CP1251', $output_string);
	fputs($output_file, $output_string);
}

//-------------------------------------

// Статистика выполнения скрипта
$time = microtime(true) - $start;

if (STATISTIC_ON)
{
	echo '-------------------------------------'."\n";
	echo 'Исправлено названий: '.$count['uppercase']."\n";
	echo 'Исправлено цветов: '.$count['color']."\n";
	echo 'Исправлено запятых: '.$count['commas']."\n";
	echo 'Удалено лишних пробелов: '.$count['space']."\n";
	$sum_count = $count['uppercase'] + $count['color'] + $count['space'];
	echo 'Всего исправлено: '.$sum_count."\n";
	printf("Скрипт выполнялся %.4F сек.\n", $time);
}

// THE END

//-------------Функции-----------------

// Функция создает шаблон поиска
// Входная строка разбивается на массив слов
// Заменяются все плюсы на поробелы (так как пробелы удаляются иногда нужен шаблон с пробелом)
// Возвращается одномерный массив шаблонов поиска
function PatternCreater($arg)
{
	$array = explode(',', $arg);
	foreach ($array as $key => $value)
	{
		if (!empty($value))
		{
			$value = trim($value);
			
			// Замена знака плюс на пробел
			$array[$key] = str_replace('_', ' ', $value);
		}
		else
		{
			unset($array[$key]);
		}
	}
	return $array;
}

// Функция подсчитывает отношение $current к $max в процентах
// Это нужно для отображения информации о степени завершенности обработки
function ShowWhatDone($current, $max)
{
	if (!empty($current) && !empty($max))
	{
		if ($current < $max)
		{
			$result = (100 / $max) * $current;
			$result = (int)$result;
			return ($result);
		}
		elseif($current == $max)
		{
			return ('100');
		}
	}
}

// Функция возвращает строку разделенную точка-запятой в 3 местах
// Артикул;Название товара;Количество;Цена
function getForColumn($array)
{
	if (!empty($array))
	{
		$column = explode(';', $array);
		
		$count = count($column);
		
		$column[] = '';
		
		$string = '';
		
		foreach($column as $key=>$value)
		{
			if (0 == $key)
			{
				$column[0] = $value.'';
			}
			elseif ($key < ($count - 3))
			{
				$column[1] .= $value;
			}
			elseif ($key == ($count - 2))
			{
				$column[2] = $value;
			}
			elseif ($key == ($count - 1))
			{
				$column[3] = $value;
			}
		}
		
		// Склеиваем массив в строку
		foreach ($column as $string)
		{
			$string .= $column[0].';'.$column[1].';'.$column[2].';'.$column[3];
		}
		
		return $string;
	}
}

// Удаляет двойные ковычки, которые не относятся к дюймам
function removeDoubleQuotes($string)
{
	if (!empty($string))
	{
		$string = preg_replace('/(?<![0-9])(?<=)"/', '', $string);
		
		$string = preg_replace('/\'/', '', $string);
		
		return $string;
	}
}
?>
