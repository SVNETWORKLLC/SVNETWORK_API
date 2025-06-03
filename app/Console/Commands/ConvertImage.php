<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ConvertImage extends Command
{
    protected $signature = 'image:convert {image}';
    protected $description = 'Convierte una imagen pesada en una versión superligera optimizada para web (WebP, calidad baja, resize)';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $image = $this->argument('image');
        $output = 'temp_image.webp';

        // Comando ffmpeg para convertir a WebP, calidad baja, resize a 1280px de ancho máximo
        $comando = [
            'ffmpeg',
            '-i', $image,
            '-vf', 'scale=1280:-1', // Redimensiona a 1280px de ancho, mantiene proporción
            '-quality', '50',       // Calidad baja (0-100, 100 es mejor calidad)
            '-compression_level', '6', // Compresión alta
            '-preset', 'picture',   // Preset para imágenes
            '-y',                   // Sobrescribe si existe
            $output
        ];

        $process = new Process($comando);

        try {
            $process->mustRun();
            rename($output, $image . '.webp');
            $this->info("Imagen convertida y optimizada: {$image}.webp");
        } catch (ProcessFailedException $exception) {
            $this->error("Error al convertir la imagen: {$exception->getMessage()}");
        }
    }
}
