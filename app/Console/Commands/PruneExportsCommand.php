<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class PruneExportsCommand extends Command
{
    protected $signature = 'exports:prune {--hours=24}';

    protected $description = 'Remove arquivos de exportação cujo link assinado já expirou.';

    public function handle(): int
    {
        $dir = storage_path('app/exports');

        if (! File::isDirectory($dir)) {
            $this->info('Diretório de exportações inexistente, nada a remover.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subHours((int) $this->option('hours'))->getTimestamp();
        $deleted = 0;

        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        $this->info("Removidos {$deleted} arquivo(s) de exportação.");

        return self::SUCCESS;
    }
}
