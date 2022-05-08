#!C:\php\php.exe -q
<?php

require __DIR__ . '/ImageHandler.php';

$options = ['h', 'height', 'w', 'width', 'm', 'mark'];
$width = 0;
$height = 0;

try {
    $args = arguments($argv);

    if ($args['help']) {
        echo "[СПРАВКА]
        giffer [OPTIONS] SRC_FILE DEST_FILE
        где
        OPTIONS - входные параметры:
            -w, --width - ширина изображения в пикселях (целочисленное число)
            -h, --height - высота изображения в пикселях (целочисленное число)
            -m, --mark - путь к файлу-картинки, которая используется в качестве водяного знака (строка)
        
        SRC_FILE - путь к исходному файлу для обработки (строка)
        DEST_FILE - путь к выходному файлу (строка)
        
        Пример:
        giffer --width=640 --height=480 --mark=\"C:\\mark.png\" \"C:\\orig.gif\" \"C:\\result.gif\" 
        ";
    } elseif (!$args['options'] or count($args['options']) > 3 or count($args['arguments']) != 2 or array_diff($args['options'], $options)) {
        throw new Exception("Ошибка: некорректная команда.\nДля вызова справки используйте '-?'");
    } elseif (isset($args['options']['w'], $args['options']['width']) or isset($args['options']['h'], $args['options']['height']) or isset($args['options']['m'], $args['options']['mark'])) {
        throw new Exception("Ошибка: введены две одинаковых опции.");
    } else {
        if (isset($args['options']['w'])) {
            $width = $args['options']['w'];
        }
        if (isset($args['options']['width'])) {
            $width = $args['options']['width'];
        }

        if (isset($args['options']['h'])) {
            $height = $args['options']['h'];
        }
        if (isset($args['options']['height'])) {
            $height = $args['options']['height'];
        }

        if (isset($args['options']['m'])) {
            $mark = $args['options']['m'];
        }
        if (isset($args['options']['mark'])) {
            $mark = $args['options']['mark'];
        }

        $src = $args['arguments'][0];
        $dest = $args['arguments'][1];
    }


    $originalImg = new ImageHandler($src);
    $originalImg->DecodeGIF();
    $originalImg->ImageResize($width, $height);
    if ($mark) {
        $originalImg->PlaceWatermark($mark);
    }
    $originalImg->EncodeGIF($dest);

    exit(0);
} catch (Throwable $ex) {
    echo "Программа завершена с ошибками\n{$ex->getMessage()}";
    exit(1);
}

/**
 * Получение опций и аргументов командной строки
 *
 * @param array $_argv Массив аргументов командной строки
 */
function arguments($_argv)
{
    $options = [];
    $args = array(
        'options' => array(),
        'arguments' => array(),
        'help' => false
    );

    foreach ($_argv as $arg) {
        if (preg_match('/-{1,2}([^=]+)=(.*)/', $arg, $matches)) {
            $args['options'][$matches[1]] = $matches[2];
        } elseif (substr($arg, -3) == "gif") {
            $args['arguments'][] = $arg;
        } elseif ($arg === "-?") {
            $args['help'] = true;
        }
    }
    return $args;
}
