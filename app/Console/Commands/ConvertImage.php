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
        $extension = pathinfo($image, PATHINFO_EXTENSION);
        // Genera el nombre de salida agregando un '2' antes de la extensión
        $output = preg_replace('/(\\.' . preg_quote($extension, '/') . ')$/i', '2.' . $extension, $image);

        // Comando ffmpeg para convertir y optimizar la imagen manteniendo la extensión original
        $comando = [
            'ffmpeg',
            '-i', $image,
            '-vf', "scale='min(320,iw)':'min(240,ih)':force_original_aspect_ratio=decrease",
            '-q:v', '5',
            '-frames:v', '1',
            '-y', // Sobrescribe si existe
            $output
        ];

        $process = new Process($comando);

        try {
            $process->mustRun();
            $this->info("Imagen convertida y optimizada: {$output}");
        } catch (ProcessFailedException $exception) {
            $this->error("Error al convertir la imagen: {$exception->getMessage()}");
        }
    }
}
