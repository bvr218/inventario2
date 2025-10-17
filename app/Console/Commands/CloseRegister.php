<?php

namespace App\Console\Commands;

use App\Models\RegisterHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB; // <-- 1. Importar DB

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
    protected $description = 'Cierra las cajas que fueron abiertas hoy y que aún no han sido cerradas.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando proceso de cierre de cajas...');

        // 3. Obtener los IDs de los últimos registros de cada caja, basados en fecha
        // Esta consulta es más compleja para ser compatible con SQL estricto
        // y para manejar empates en 'created_at'.

        // 3a. Encuentra la fecha (timestamp) máxima para cada 'register_id'
        $latestTimestamps = RegisterHistory::select('register_id', DB::raw('MAX(created_at) as max_created_at'))
            ->groupBy('register_id');

        // 3b. Une la tabla consigo misma (usando la subconsulta) para encontrar
        //     los registros que coinciden con esa fecha máxima.
        //     Luego, usamos MAX(id) para desempatar si dos registros tuvieran
        //     exactamente el mismo timestamp.
        $latestIds = RegisterHistory::select(DB::raw('MAX(ns_nexopos_registers_history.id) as id')) // <-- MODIFICADO
            ->from('nexopos_registers_history')
            ->joinSub($latestTimestamps, 'latest', function ($join) { // <-- MODIFICADO
                $join->on('nexopos_registers_history.register_id', '=', 'latest.register_id')
                     ->on('nexopos_registers_history.created_at', '=', 'latest.max_created_at');
            })
            ->groupBy('nexopos_registers_history.register_id') // <-- MODIFICADO
            ->pluck('id');


        // 4. Obtener los objetos completos de esos últimos registros
        $latestRegisters = RegisterHistory::whereIn('id', $latestIds)->get();

        $this->info("Revisando " . $latestRegisters->count() . " cajas activas/últimas...");

        foreach ($latestRegisters as $latestRegister) {
            // 5. REQUISITO 1: Verificar si ya está cerrada
            if ($latestRegister->action == RegisterHistory::ACTION_CLOSING) {
                $this->line(" - Caja {$latestRegister->register_id}: Ya está cerrada. Omitiendo.");
                continue; // Saltar a la siguiente caja
            }

            // 6. REQUISITO 2: Verificar si fue abierta HOY
            // Lógica de sesión basada en fecha (created_at) e ID (como desempate)

            // Encontrar el último registro de cierre *anterior* a este registro
            $lastClosingRecord = RegisterHistory::where('register_id', $latestRegister->register_id) // <-- MODIFICADO
                ->where('action', RegisterHistory::ACTION_CLOSING)
                // Asegurarse que es *anterior* al último registro que estamos revisando
                ->where(function ($query) use ($latestRegister) { // <-- MODIFICADO (lógica de fecha + id)
                    $query->where('created_at', '<', $latestRegister->created_at)
                          ->orWhere(function ($q) use ($latestRegister) {
                              $q->where('created_at', '=', $latestRegister->created_at)
                                ->where('id', '<', $latestRegister->id);
                          });
                })
                ->orderBy('created_at', 'desc') // <-- MODIFICADO
                ->orderBy('id', 'desc')       // <-- Añadido desempate
                ->first();                  // <-- MODIFICADO

            // La consulta para encontrar el registro de apertura de esta sesión
            $sessionQuery = RegisterHistory::where('register_id', $latestRegister->register_id);

            if ($lastClosingRecord) { // <-- MODIFICADO
                // Si hubo un cierre previo, la sesión actual empezó *después* de él
                $sessionQuery->where(function ($query) use ($lastClosingRecord) { // <-- MODIFICADO (lógica de fecha + id)
                    $query->where('created_at', '>', $lastClosingRecord->created_at)
                          ->orWhere(function ($q) use ($lastClosingRecord) {
                              $q->where('created_at', '=', $lastClosingRecord->created_at)
                                ->where('id', '>', $lastClosingRecord->id);
                          });
                });
            }
            // Si $lastClosingRecord es null, la caja nunca se ha cerrado,
            // así que la sesión empezó con el primer registro.

            // El registro de apertura es el primer registro de esta sesión
            $openingRecord = $sessionQuery->orderBy('created_at', 'asc') // <-- MODIFICADO
                                         ->orderBy('id', 'asc')       // <-- Añadido desempate
                                         ->first();

            if (!$openingRecord) {
                // Esto es raro, pero por si acaso
                $this->warn(" - Caja {$latestRegister->register_id}: No se pudo determinar el registro de apertura. Omitiendo.");
                continue;
            }

            // 7. Comprobar la fecha del registro de apertura
            if ($openingRecord->created_at->isToday()) {
                
                // ¡CUMPLE AMBAS CONDICIONES! (No está cerrada y se abrió hoy)
                $this->info(" - Caja {$latestRegister->register_id}: Abierta hoy. Creando registro de cierre...");

                $newRegisterHistory = new RegisterHistory();
                $newRegisterHistory->register_id = $latestRegister->register_id;
                $newRegisterHistory->action = RegisterHistory::ACTION_CLOSING; // La acción de cierre
                $newRegisterHistory->author = $latestRegister->author; // O un ID de "Sistema" si lo tienes
                $newRegisterHistory->value = 0; // El cierre no mueve valor
                $newRegisterHistory->balance_before = $latestRegister->balance_after; // El balance antes es el último balance
                $newRegisterHistory->balance_after = $latestRegister->balance_after; // El balance después es el mismo
                
                // Copiar datos relevantes del último registro
               
                $newRegisterHistory->payment_id = NULL;
                $newRegisterHistory->payment_type_id = 0;
                $newRegisterHistory->order_id = NULL;
                $newRegisterHistory->transaction_type = "negative";
                
                $newRegisterHistory->description = NULL; // <-- MODIFICADO
                $newRegisterHistory->uuid = NULL;
                
                // Asegurarnos de que el created_at es AHORA (o al menos después del último registro)
                // Opcional: Si quieres que el cierre tenga el timestamp de "ahora".
                // Si quieres que herede el del último, puedes copiarlo,
                // pero "ahora" tiene más sentido para un cron.
                $newRegisterHistory->created_at = Carbon::now(); // <-- Añadido para claridad
                
                $newRegisterHistory->save();

                $this->info("   -> Cierre para la caja {$latestRegister->register_id} creado con ID: {$newRegisterHistory->id}.");

            } else {
                // Está abierta, pero se abrió un día anterior
                $this->line(" - Caja {$latestRegister->register_id}: Está abierta, pero se abrió el " . $openingRecord->created_at->format('Y-m-d') . ". Omitiendo.");
            }
        }

        $this->info('Proceso de cierre de cajas completado.');
    }
}