<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

class ImportarCogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importar:cogs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jsonPath = storage_path('export.json'); // Guarda el JSON en storage/
        
        if (!file_exists($jsonPath)) {
            $this->error("El archivo export.json no existe en storage/");
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        foreach ($data as $item) {
            $codigoProducto = $item['codigo_producto'];
            $valorCompra = $item['valor_compra'];

            $product = DB::table('nexopos_products')
                        ->where('sku', $codigoProducto)
                        ->first();

            if ($product) {
                DB::table('nexopos_products_unit_quantities')
                    ->where('product_id', $product->id)
                    ->update(['cogs' => $valorCompra]);

                $this->info("Actualizado SKU: {$codigoProducto} con COGS: {$valorCompra}");
            } else {
                $this->warn("Producto no encontrado: {$codigoProducto}");
            }
        }

        $this->info("Proceso terminado.");
    }
    
}
