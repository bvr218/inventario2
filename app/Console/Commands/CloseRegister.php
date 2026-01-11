<?php

namespace App\Console\Commands;

use App\Models\RegisterHistory;
use App\Models\Register;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CloseRegister extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:close-register';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cierra las cajas abiertas. Si es de noche cierra la de hoy, si es al reiniciar cierra las olvidadas de ayer.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando proceso de verificación y cierre de cajas...');

        // ---------------------------------------------------------
        // 1. Obtener los IDs de los últimos registros de cada caja
        // ---------------------------------------------------------
        
        // 1a. Encuentra la fecha (timestamp) máxima para cada 'register_id'
        $latestTimestamps = RegisterHistory::select('register_id', DB::raw('MAX(created_at) as max_created_at'))
            ->groupBy('register_id');

        // 1b. Une la tabla consigo misma para obtener el ID exacto del último movimiento
        $latestIds = RegisterHistory::select(DB::raw('MAX(ns_nexopos_registers_history.id) as id'))
            ->from('nexopos_registers_history')
            ->joinSub($latestTimestamps, 'latest', function ($join) {
                $join->on('nexopos_registers_history.register_id', '=', 'latest.register_id')
                     ->on('nexopos_registers_history.created_at', '=', 'latest.max_created_at');
            })
            ->groupBy('nexopos_registers_history.register_id')
            ->pluck('id');

        // 2. Obtener los objetos completos de esos últimos registros
        $latestRegisters = RegisterHistory::whereIn('id', $latestIds)->get();

        $this->info("Revisando " . $latestRegisters->count() . " cajas activas/últimas detectadas.");

        foreach ($latestRegisters as $latestRegister) {
            
            // ---------------------------------------------------------
            // PASO A: Verificar si ya está cerrada
            // ---------------------------------------------------------
            if ($latestRegister->action == RegisterHistory::ACTION_CLOSING) {
                // Ya está cerrada, no hacemos nada.
                continue; 
            }

            // ---------------------------------------------------------
            // PASO B: Determinar cuándo se abrió esta sesión actual
            // ---------------------------------------------------------
            
            // Buscar el último cierre anterior a este registro actual
            $lastClosingRecord = RegisterHistory::where('register_id', $latestRegister->register_id)
                ->where('action', RegisterHistory::ACTION_CLOSING)
                ->where(function ($query) use ($latestRegister) {
                    $query->where('created_at', '<', $latestRegister->created_at)
                          ->orWhere(function ($q) use ($latestRegister) {
                              $q->where('created_at', '=', $latestRegister->created_at)
                                ->where('id', '<', $latestRegister->id);
                          });
                })
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            // Consulta base para buscar el inicio de la sesión
            $sessionQuery = RegisterHistory::where('register_id', $latestRegister->register_id);

            if ($lastClosingRecord) {
                // La sesión actual empezó DESPUÉS del último cierre encontrado
                $sessionQuery->where(function ($query) use ($lastClosingRecord) {
                    $query->where('created_at', '>', $lastClosingRecord->created_at)
                          ->orWhere(function ($q) use ($lastClosingRecord) {
                              $q->where('created_at', '=', $lastClosingRecord->created_at)
                                ->where('id', '>', $lastClosingRecord->id);
                          });
                });
            }
            // Si $lastClosingRecord es null, es la primera sesión de la historia, tomamos desde el inicio.

            // El registro de apertura es el PRIMERO después del último cierre
            $openingRecord = $sessionQuery->orderBy('created_at', 'asc')
                                         ->orderBy('id', 'asc')
                                         ->first();

            if (!$openingRecord) {
                $this->warn(" - Caja {$latestRegister->register_id}: No se pudo determinar apertura. Omitiendo.");
                continue;
            }

            // ---------------------------------------------------------
            // PASO C: LÓGICA DE DECISIÓN (La parte crítica)
            // ---------------------------------------------------------

            // 1. ¿La caja se abrió ayer o antes? (Es una caja olvidada)
            $esCajaVieja = $openingRecord->created_at->lt(Carbon::today());

            // 2. ¿La caja es de hoy PERO ya es horario de cierre (>= 21:00)?
            // Esto protege contra reinicios del PC a las 2 PM (no cerrará la caja)
            $horaActual = Carbon::now()->hour;
            $esCierreNocturno = $openingRecord->created_at->isToday() && $horaActual >= 21;

            if ($esCajaVieja || $esCierreNocturno) {
                
                $tipoCierre = $esCajaVieja ? "RECUPERACIÓN (Olvido)" : "PROGRAMADO (Fin de día)";
                $this->info(" - Caja {$latestRegister->register_id}: Detectada abierta. Tipo: $tipoCierre. Cerrando...");

                $newRegisterHistory = new RegisterHistory();
                $newRegisterHistory->register_id = $latestRegister->register_id;
                $newRegisterHistory->action = RegisterHistory::ACTION_CLOSING;
                $newRegisterHistory->author = $latestRegister->author;
                $newRegisterHistory->value = 0;
                
                // Mantenemos los balances iguales porque el cierre automático no mueve dinero físico
                $newRegisterHistory->balance_before = $latestRegister->balance_after;
                $newRegisterHistory->balance_after = $latestRegister->balance_after;
                
                $newRegisterHistory->payment_id = NULL;
                $newRegisterHistory->payment_type_id = 0;
                $newRegisterHistory->order_id = NULL;
                $newRegisterHistory->transaction_type = "negative";
                
                // Descripción dinámica según el caso
                if ($esCajaVieja) {
                    $newRegisterHistory->description = "Cierre automático sistema (Recuperación por olvido del día " . $openingRecord->created_at->format('Y-m-d') . ")";
                } else {
                    $newRegisterHistory->description = "Cierre automático sistema (Fin de jornada)";
                }

                $newRegisterHistory->uuid = NULL;
                $newRegisterHistory->created_at = Carbon::now();
                
                $newRegisterHistory->save();

                // Actualizar estado en tabla padre
                Register::where("id", $latestRegister->register_id)->update(['status' => 'closed']);

                $this->info("   -> Caja cerrada correctamente. ID Historial: {$newRegisterHistory->id}.");

            } else {
                // Caso: El script corrió (ej. reinicio a las 2 PM) pero la caja es de HOY y es temprano.
                $fechaApertura = $openingRecord->created_at->format('Y-m-d H:i');
                $this->line(" - Caja {$latestRegister->register_id}: Abierta ($fechaApertura). Es de hoy y aún es horario laboral (< 21:00). NO se cierra.");
            }
        }

        $this->info('Proceso completado.');
    }
}