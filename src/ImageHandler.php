<?php

/**
 * Устанавливаем зависимости библиотек
 */
require __DIR__ . '/../vendor/autoload.php';

use GIFEndec\Events\FrameRenderedEvent;
use GIFEndec\IO\FileStream;
use GIFEndec\Decoder;
use GIFEndec\Color;
use GIFEndec\Encoder;
use GIFEndec\Frame;
use GIFEndec\Renderer;

/**
 * Обработка gif файлов
 */
class ImageHandler
{
    /**
     * Путь до изображения
     * @var string
     */
    protected $imgPath;

    /**
     * Расположение временного хранилища
     * @var string
     */
    protected $tmpDir;

    /**
     * Ширина изображения
     * @var int
     */
    protected $imgX;

    /**
     * Высота изображения
     * @var int
     */
    protected $imgY;

    /**
     * Длина кадра в миллисекундах
     * @var int[]
     */
    protected $framesDuration = [];

    /**
     * Количество кадров
     * @var int
     */
    protected $numOfFrames;


    /**
     * @param string $path Путь до исходного изображения
     * @param string $tmpDir Путь до временного хранилища кадров, по умолчанию /../images/frames/
     */
    public function __construct($path, $tmpDir = __DIR__ . "/../images/frames/")
    {
        $this->imgPath = $path;
        $this->tmpDir = $tmpDir;
    }

    /**
     * Деструктор экземпляра класса, удаляет временную дирректорию для кадров
     */
    public function __destruct()
    {
        // Очищаем дирректорию от файлов
        array_map('unlink', glob($this->tmpDir . "*"));
        if (!rmdir($this->tmpDir)) {
            throw new Exception("Ошибка. Код -1. Ошибка удаления временной директории.");
        }
    }

    /**
     * Разбор GIF анимации на кадры
     */
    public function DecodeGIF()
    {
        $gifStream = new FileStream($this->imgPath);
        $gifDecoder = new Decoder($gifStream);
        $gifRenderer = new Renderer($gifDecoder);

        if (!mkdir($this->tmpDir)) {
            throw new Exception("Ошибка. Код -1. Ошибка создания временного хранилища кадров.");
        }

        $gifRenderer->start(function (FrameRenderedEvent $event) {

            // Задаем формат нумерации 000
            $paddedIndex = str_pad($event->frameIndex, 3, '0', STR_PAD_LEFT);
            // Сохраняем кадр в файл
            imagegif($event->renderedFrame, $this->tmpDir ."frame{$paddedIndex}.gif");
            // Сохраняем длину кадра
            $this->framesDuration[] = $event->decodedFrame->getDuration();
        });
        // Получаем количество кадров
        $this->numOfFrames = count($this->framesDuration);
    }

    /**
     * Сборка GIF анимации в единый файл
     * @param string $destinationPath
     */
    public function EncodeGIF($destinationPath)
    {
        $gif = new Encoder();

        $i = 0;
        foreach (glob($this->tmpDir . 'frame*.gif') as $file) {
            $stream = new FileStream($file);
            $frame = new Frame();
            $frame->setDisposalMethod(1);
            $frame->setStream($stream);
            $frame->setDuration($this->framesDuration[$i]);
            $gif->addFrame($frame);
            $i++;
        }

        $gif->addFooter();

        // Сохраняем готовую анимацию в файл
        $gif->getStream()->copyContentsToFile($destinationPath);
    }

    /**
     *
     * Изменение размера изображения
     *
     * @param int $width Необходимая ширина изображения, если не указана устанавливается пропорционально новой высоте
     * @param int $height Необходимая высота изображения, если не указана устанавливается пропорционально новой ширине
     * @param bool $isWatermark Проверка на водяной знак
     * @param GDImage $watermark Путь до изображения водяного знака
     */
    public function ImageResize($width = 0, $height = 0, $isWatermark = false, $watermark = 0)
    {
        $resizer = function ($i=0, $wm = 0, $img = 0) use ($width, $height) {
            // Загружаем кадр
            if (!$wm) {
                $img = imagecreatefromgif($this->tmpDir . "frame" . str_pad($i, 3, '0', STR_PAD_LEFT).'.gif');
            }

            $oldWidth = imagesx($img);
            $oldHeight = imagesy($img);

            // Проверяем что пользователь ввел корректные значения
            if (($width == 0 and $height == 0) or $width < 0 or $height < 0) {
                throw new Exception("Ошибка. Код -1. Вы ввели некорректные значения ширины и высоты. Изменения не будут внесены.");
            } elseif ($width == 0) {
                $newHeight = $height;
                // Вычисляем ширину пропорциональную новой высоте
                $newWidth = (int)($oldWidth / ($oldHeight / $newHeight));
            } elseif ($height == 0) {
                $newWidth = $width;
                // Вычисляем высоту пропорциональную новой ширине
                $newHeight = (int)($oldHeight / ($oldWidth / $newWidth));
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }

            // Создаем холст под изображение с измененными шириной и высотой
            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            // Копируем оригинальное изображение, с изменением ширины и высоты, на полученный холст
            imagecopyresized($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $oldWidth, $oldHeight);

            // Сохраняем в файл
            if (!$wm) {
                imagegif($newImg, $this->tmpDir . "frame" . str_pad($i, 3, '0', STR_PAD_LEFT).'.gif');
            } else {
                imagepng($newImg, $this->tmpDir . "wm.png");
            }

            // Удаляем кадры из памяти
            imagedestroy($img);
            imagedestroy($newImg);
        };

        // Проверяем, необходимо ли изменить только водяной знак
        if ($isWatermark) {
            $resizer(0, $isWatermark, $watermark);
        } else {
            // Циклично обрабатываем все кадры
            $this->Loop($resizer, " Изменяем размер кадра ");
        }
    }

    /**
     * Наложение водяного знака на изображение
     *
     * @param string $watermarkPath Путь до изображения с водяным знаком
     */
    public function PlaceWatermark($watermarkPath)
    {
        // Загружаем изображение водяного знака в соответствии с форматом
        switch (getimagesize($watermarkPath)["mime"]) {
            case "image/png":
                $stamp = imagecreatefrompng($watermarkPath);
                break;
            case "image/gif":
                $stamp = imagecreatefromgif($watermarkPath);
                break;
            case "image/jpeg":
                $stamp = imagecreatefromjpeg($watermarkPath);
                break;
            case "image/bmp":
                $stamp = imagecreatefrombmp($watermarkPath);
                break;
            default:
                throw new Exception("Неверный формат файла");
        }

        // Получаем размеры кадров, для корректировки водяного знака
        list($this->imgX, $this->imgY) = getimagesize($this->tmpDir . 'frame000.gif');

        // Устанавливаем размер отступа
        $marge_right = 10;
        $marge_bottom = 10;

        // Получаем размер изображения водяного знака
        $stampX = imagesx($stamp);
        $stampY = imagesy($stamp);

        // Если размер водяного знака по ширине или длине больше чем на 40% от ширины и длины исходного изображения,
        // то уменьшаем его
        if ($stampX > $this->imgX*0.40 or $stampY > $this->imgY*0.40) {
            if ($stampX > $stampY) {
                $this->ImageResize((int)($this->imgX*0.40), isWatermark:true, watermark:$stamp);
            } else {
                $this->ImageResize(height:(int)($this->imgY*0.40), isWatermark:true, watermark:$stamp);
            }
            $stamp = imagecreatefrompng($this->tmpDir . "wm.png");
            $stampX = imagesx($stamp);
            $stampY = imagesy($stamp);
        }

        // Анонимная функция для наложения водяного знака на кадр
        $watermarker = function ($i) use ($stamp, $stampX, $stampY, $marge_right, $marge_bottom) {
            // Загружаем кадр
            $frame = imagecreatefromgif($this->tmpDir . "frame" . str_pad($i, 3, '0', STR_PAD_LEFT).'.gif');

            // Получаем размер кадра
            $imgX = imagesx($frame);
            $imgY = imagesy($frame);

            // Совмещаем кадр и водяной знак
            imagecopymerge($frame, $stamp, $imgX - $stampX - $marge_right, $imgY - $stampY - $marge_bottom, 0, 0, $stampX, $stampY, 60);
            // Сохраняем в файл
            imagegif($frame, $this->tmpDir . "frame" . str_pad($i, 3, '0', STR_PAD_LEFT).'.gif');
            // Выгружаем кадр из памяти
            imagedestroy($frame);
        };

        // Циклично обрабатываем все кадры
        $this->Loop($watermarker, " Накладываем водяной знак ");
    }

    /**
     * Метод для цикличной обработки анонимных функций
     *
     * @param function $task Анонимная функция
     * @param string $info Подпись прогрессбара
     */
    private function Loop($task, $info)
    {
        for ($i = 0; $i < $this->numOfFrames; $i++) {
            $task($i);
            /**
             * Progressbar
             * @author: https://gist.github.com/mayconbordin/2860547
             */
            $perc = round(($i * 100) / $this->numOfFrames);
            $bar = round((50 * $perc) / 100);
            echo sprintf("%s%%[%s>%s]%s\r", $perc, str_repeat("=", $bar), str_repeat(" ", 50-$bar), $info);
        }
        echo "\n";
    }
}
