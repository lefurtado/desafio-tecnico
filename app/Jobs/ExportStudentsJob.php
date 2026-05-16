<?php

namespace App\Jobs;

use App\Models\Documento;
use App\Models\Endereco;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\User;
use App\Support\DocumentFormatter;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class ExportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    private const LOCK_TTL_SECONDS = 120;
    private const HEARTBEAT_INTERVAL = 30;

    public function __construct(
        private readonly int $userId
    ) {
    }

    public static function lockKey(int $userId): string
    {
        return "exports:students:user:{$userId}";
    }

    public function handle(): void
    {
        $this->acquireLock();

        try {
            $this->export();
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock(): void
    {
        Cache::put(self::lockKey($this->userId), true, self::LOCK_TTL_SECONDS);
    }

    private function renewLock(): void
    {
        Cache::put(self::lockKey($this->userId), true, self::LOCK_TTL_SECONDS);
    }

    private function releaseLock(): void
    {
        Cache::forget(self::lockKey($this->userId));
    }

    private function export(): void
    {
        $filename = 'exports/alunos_' . now()->format('Ymd_His') . '_' . uniqid() . '.xlsx';
        $fullPath = storage_path('app/' . $filename);

        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $writer = new Writer();
        $writer->openToFile($fullPath);

        $lastHeartbeat = time();
        $buffer = [];

        try {
            $writer->addRow(Row::fromValues([
                'Nome',
                'E-mail',
                'Data de Nascimento',
                'Range de Escolaridade',
                'Escola',
                'CPF',
                'RG',
                'Logradouro',
                'CEP',
                'Aprovações',
                'Reprovações',
            ]));

            User::query()
                ->has('matriculas')
                ->select(['users.id', 'users.name', 'users.email', 'users.data_de_nascimento'])
                ->addSelect([
                    'ano_inicio' => Matricula::selectRaw('MIN(ano_letivo)')
                        ->whereColumn('user_id', 'users.id'),
                    'ano_fim' => Matricula::selectRaw('MAX(ano_letivo)')
                        ->whereColumn('user_id', 'users.id'),
                    'escola_recente' => Escola::select('nome')
                        ->join('matriculas', 'escolas.id', '=', 'matriculas.escola_id')
                        ->whereColumn('matriculas.user_id', 'users.id')
                        ->orderByDesc('matriculas.ano_letivo')
                        ->limit(1),
                    'aprovacoes' => Matricula::selectRaw('COUNT(*)')
                        ->whereColumn('user_id', 'users.id')
                        ->where('resultado_final', 'aprovado'),
                    'reprovacoes' => Matricula::selectRaw('COUNT(*)')
                        ->whereColumn('user_id', 'users.id')
                        ->where('resultado_final', 'reprovado'),
                    'cpf' => Documento::select('cpf')
                        ->whereColumn('user_id', 'users.id')
                        ->limit(1),
                    'rg' => Documento::select('rg')
                        ->whereColumn('user_id', 'users.id')
                        ->limit(1),
                    'logradouro' => Endereco::select('logradouro')
                        ->whereColumn('user_id', 'users.id')
                        ->limit(1),
                    'cep' => Endereco::select('cep')
                        ->whereColumn('user_id', 'users.id')
                        ->limit(1),
                ])
                ->cursor()
                ->each(function (User $user) use ($writer, &$lastHeartbeat, &$buffer): void {
                    if (time() - $lastHeartbeat >= self::HEARTBEAT_INTERVAL) {
                        $this->renewLock();
                        $lastHeartbeat = time();
                    }

                    $buffer[] = Row::fromValues([
                        $user->name,
                        $user->email,
                        $user->data_de_nascimento?->format('d/m/Y'),
                        $user->ano_inicio . '-' . $user->ano_fim,
                        $user->escola_recente,
                        DocumentFormatter::cpf($user->cpf),
                        DocumentFormatter::rg($user->rg),
                        $user->logradouro,
                        DocumentFormatter::cep($user->cep),
                        $user->aprovacoes,
                        $user->reprovacoes,
                    ]);

                    if (count($buffer) >= 1000) {
                        $writer->addRows($buffer);
                        $buffer = [];
                    }
                });

            if (count($buffer) > 0) {
                $writer->addRows($buffer);
            }
        } finally {
            $writer->close();
        }

        $url = URL::temporarySignedRoute(
            'export.download',
            now()->addHours(24),
            ['path' => $filename]
        );

        $recipient = User::find($this->userId);

        if ($recipient) {
            Notification::make()
                ->title('Exportação concluída')
                ->body('O arquivo de alunos está pronto para download.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Baixar Excel')
                        ->url($url)
                        ->button(),
                ])
                ->sendToDatabase($recipient);
        }
    }


    public function failed(Throwable $exception): void
    {
        $this->releaseLock();

        $recipient = User::find($this->userId);

        if (!$recipient) {
            return;
        }

        Notification::make()
            ->title('Falha na exportação')
            ->body('Ocorreu um erro ao gerar a planilha de alunos. Tente novamente.')
            ->danger()
            ->sendToDatabase($recipient);
    }
}