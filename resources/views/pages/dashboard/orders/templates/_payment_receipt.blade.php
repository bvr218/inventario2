<?php
use App\Models\Order;
use App\Classes\Hook;
use Illuminate\Support\Facades\View; // Mantenido del primer formato
use Carbon\Carbon; // Mantenido para formateo de fecha si se necesita en otro lugar

// Asumiendo que $paymentTypes y $payment están disponibles como en el contexto del segundo código.
// También $ordersService debe estar disponible para orderTemplateMapping.

// Formatear fecha, aunque las columnas dinámicas podrían manejar esto.
// Si ns_invoice_receipt_column_a/b ya muestran la fecha, esta variable puede no ser necesaria allí.
$formattedDate = $order->created_at ? Carbon::parse($order->created_at)->format('d/m/Y H:i') : 'N/A';
?>
<div class="w-full h-full">
    <div class="w-full md:w-1/2 lg:w-1/3 shadow-lg bg-white p-4 mx-auto font-sans text-sm">

        {{-- Header Section --}}
        <div class="flex justify-between items-start mb-2">
            <div>
                {{-- Logo del segundo formato --}}
                @if ( empty( ns()->option->get( 'ns_invoice_receipt_logo' ) ) )
                    <h1 style="color:#000000; font-size: large;" class="font-bold">{{ ns()->option->get( 'ns_store_name', 'MI EMPRESA S.A.' ) }}</h1>
                @else
                    <img src="{{ ns()->option->get( 'ns_invoice_receipt_logo' ) }}" alt="{{ ns()->option->get( 'ns_store_name' ) }}" class="max-h-20"> {{-- Ajusta max-h según necesidad --}}
                @endif
                <h1 style="color:#000000;" class="text-xl font-bold mt-2">FACTURA</h1>
                <p style="color:#000000" class="text-xs">No. {{ $order->code ?? '00000000' }}</p>
            </div>
            <div class="text-right text-xs">
                {{-- Si el logo ya muestra el nombre de la tienda, podrías omitir este h1 --}}
                @if ( !empty( ns()->option->get( 'ns_invoice_receipt_logo' ) ) )
                     <h1 style="color:#000000;font-size: large;" class="font-bold">{{ ns()->option->get( 'ns_store_name', 'MI EMPRESA S.A.' ) }}</h1>
                @endif
                <p style="color:#000000">NIT: {{ ns()->option->get( 'ns_store_nit', '900.123.456-7' ) }}</p>
                <p style="color:#000000">{{ ns()->option->get( 'ns_store_address', 'Calle Principal #123' ) }}</p>
                <!-- <p style="color:#000000">Tel: {{ ns()->option->get( 'ns_store_phone', '(123) 456-7890' ) }}</p> -->
            </div>
        </div>

        <hr class="my-2 border-gray-300">

        {{-- Invoice Details Section - Usando las columnas flexibles del segundo formato --}}
        <div class="flex flex-wrap -mx-1 mb-2 text-xs"> {{-- Ajustado -mx-1 y px-1 para padding interno --}}
            <div class="px-1 w-1/2">
                {!! nl2br( $ordersService->orderTemplateMapping( 'ns_invoice_receipt_column_a', $order ) ) !!}
            </div>
            <div class="px-1 w-1/2 text-right"> {{-- Alineado a la derecha para simular el original --}}
                {!! nl2br( $ordersService->orderTemplateMapping( 'ns_invoice_receipt_column_b', $order ) ) !!}
            </div>
        </div>

        <hr class="my-2 border-gray-300">

        {{-- Items Table --}}
        <div class="w-full mb-2">
            <table class="w-full text-xs">
                <thead>
                    <tr class="font-semibold border-b border-gray-400">
                        <td class="py-1 text-left">Cant</td>
                        <td class="py-1 text-left w-1/2">Descripción</td>
                        <td class="py-1 text-right">Precio</td>
                        <td class="py-1 text-right">Total</td>
                    </tr>
                </thead>
                <tbody>
                    @foreach( Hook::filter( 'ns-receipt-products', $order->combinedProducts ) as $product )
                    <tr class="border-b border-gray-200">
                        <td class="py-1 align-top">{{ $product->quantity }}</td>
                        <td class="py-1 align-top">
                            <?php
                                // Usar el renderizador de nombre de producto del primer formato si es necesario
                                // O simplemente el nombre del producto como en el segundo formato, añadiendo la unidad
                                $productNameView  =   View::make( 'pages.dashboard.orders.templates._product-name', compact( 'product' ) );
                                $productDisplayName = Hook::filter( 'ns-receipt-product-name', $productNameView->render(), $product );
                                // echo $productDisplayName; // Opción 1 (si _product-name es detallado)

                                echo e($product->name); // Opción 2 (más simple)
                                if ($product->unit) {
                                    echo '<br><span class="text-xs text-gray-500">' . e($product->unit->name) . '</span>';
                                }
                            ?>
                        </td>
                        <td class="py-1 text-right align-top">{{ ns()->currency->define( $product->unit_price ?? ($product->total_price / ($product->quantity ?: 1)) ) }}</td>
                        <td class="py-1 text-right align-top">{{ ns()->currency->define( $product->total_price ) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <hr class="my-2 border-gray-300">

        {{-- Totals Section --}}
        <div class="w-full mb-2 text-xs">
            <div class="flex justify-end">
                <div class="w-3/5 text-right pr-2">{{ __( 'Sub Total' ) }}:</div> {{-- Ajustado w-3/5 para más espacio --}}
                <div class="w-2/5 text-right font-medium">{{ ns()->currency->define( $order->subtotal ?? 0 ) }}</div>
            </div>
            @if ( $order->discount > 0 )
            <div class="flex justify-end">
                <div class="w-3/5 text-right pr-2">
                    <span>{{ __( 'Discount' ) }}</span>
                    @if ( $order->discount_type === 'percentage' )
                    <span>({{ $order->discount_percentage }}%)</span>
                    @endif
                    :
                </div>
                <div class="w-2/5 text-right font-medium">{{ ns()->currency->define( $order->discount ) }}</div>
            </div>
            @endif
            @if ( $order->total_coupons > 0 )
            <div class="flex justify-end">
                <div class="w-3/5 text-right pr-2">{{ __( 'Coupons' ) }}:</div>
                <div class="w-2/5 text-right font-medium">{{ ns()->currency->define( $order->total_coupons ) }}</div>
            </div>
            @endif
            @if ( $order->tax_value > 0 )
            <div class="flex justify-end">
                <div class="w-3/5 text-right pr-2">{{ $order->tax_group?->name ?? __( 'Taxes' ) }} {{-- Intenta ser específico --}}
                    @if($order->tax_group && $order->tax_group->rate)
                        ({{ $order->tax_group->rate }}%)
                    @elseif(empty($order->tax_group?->name)) {{-- Si no hay nombre de grupo, y queremos un % genérico --}}
                        {{-- ({{ $order->taxes->first()->tax_rate ?? '19' }}%) --}} {{-- Esto es una suposición, ajustar si es necesario --}}
                    @endif
                    :
                </div>
                <div class="w-2/5 text-right font-medium">{{ ns()->currency->define( $order->tax_value ) }}</div>
            </div>
            @endif
            @if ( $order->shipping > 0 )
            <div class="flex justify-end">
                <div class="w-3/5 text-right pr-2">{{ __( 'Shipping' ) }}:</div>
                <div class="w-2/5 text-right font-medium">{{ ns()->currency->define( $order->shipping ) }}</div>
            </div>
            @endif
            <div class="flex justify-end mt-1">
                <div class="w-3/5 text-right pr-2 font-bold">TOTAL:</div>
                <div class="w-2/5 text-right font-bold text-base">{{ ns()->currency->define( $order->total ?? 0 ) }}</div>
            </div>
        </div>

        <hr class="my-2 border-gray-300">

        {{-- Payment Section - Lógica del segundo formato, estilo del primero --}}
        <div class="w-full mb-4 text-xs">
            <p style="color:#000000" class="font-semibold mb-1">Forma de pago:</p>
            
            {{-- Asumiendo que $payment y $paymentTypes están disponibles --}}
            @if (!empty($payment) && is_array($payment) && isset($payment['identifier']))
            <div class="flex justify-end">
                <div class="w-3/5 text-right pr-2">{{ __( 'Current Payment' ) }} ({{ $paymentTypes[ $payment[ 'identifier' ] ] ?? __( 'Unknown Payment' ) }}):</div>
                <div class="w-2/5 text-right">{{ ns()->currency->define( $payment[ 'value' ] ) }}</div>
            </div>
            @else
             {{-- Fallback o lógica si $payment no está disponible como se espera --}}
             {{-- Por ejemplo, mostrar el tipo de pago principal del pedido si existe --}}
                @if ($order->payment_type)
                <div class="flex justify-end">
                    <div class="w-3/5 text-right pr-2">{{ $order->payment_type_label ?? Str::title(str_replace('_', ' ', $order->payment_type)) }}:</div>
                    <div class="w-2/5 text-right">{{ ns()->currency->define( $order->tendered ) }}</div> {{-- O $order->total si es más apropiado aquí --}}
                </div>
                @else
                <div class="flex justify-end">
                    <div class="w-3/5 text-right pr-2">Efectivo:</div> {{-- Fallback del primer formato --}}
                    <div class="w-2/5 text-right">{{ ns()->currency->define( $order->tendered ?? 0 ) }}</div>
                </div>
                @endif
            @endif

            <div class="flex justify-end">
                <div class="w-3/5 text-right pr-2">{{ __( 'Total Paid' ) }}:</div>
                <div class="w-2/5 text-right">{{ ns()->currency->define( $order->tendered ) }}</div>
            </div>

            @if ( in_array( $order->payment_status, [ Order::PAYMENT_REFUNDED, Order::PAYMENT_PARTIALLY_REFUNDED ]) )
                @foreach( $order->refunds as $refund ) {{-- Asumiendo que la relación es "refunds" y no "refund" --}}
                <div class="flex justify-end">
                    <div class="w-3/5 text-right pr-2">{{ __( 'Refunded' ) }}:</div>
                    <div class="w-2/5 text-right">{{ ns()->currency->define( - $refund->total ) }}</div>
                </div>
                @endforeach
            @endif

            @switch( $order->payment_status )
                @case( Order::PAYMENT_PAID )
                    @if ($order->change > 0) {{-- Solo mostrar cambio si es positivo --}}
                    <div class="flex justify-end">
                        <div class="w-3/5 text-right pr-2">{{ __( 'Change' ) }}:</div>
                        <div class="w-2/5 text-right">{{ ns()->currency->define( $order->change ) }}</div>
                    </div>
                    @endif
                @break
                @case( Order::PAYMENT_PARTIALLY )
                <div class="flex justify-end">
                    <div class="w-3/5 text-right pr-2">{{ __( 'Due' ) }}:</div>
                    <div class="w-2/5 text-right">{{ ns()->currency->define( abs( $order->change ) ) }}</div>
                </div>
                @break
            @endswitch
        </div>
        
        {{-- Order Note del segundo formato --}}
        @if( $order->note_visibility === 'visible' && !empty($order->note) )
        <div class="mb-2 text-xs text-gray-700">
            <hr class="my-2 border-gray-300">
            <p style="color:#000000"><span class="font-semibold">{{ __( 'Note' ) }}:</span> {{ $order->note }}</p>
        </div>
        @endif

        <hr class="my-2 border-gray-300">

        {{-- Footer Section --}}
        <div class="text-center text-xs text-gray-600">
            <p style="color:#000000" class="mb-1">¡Gracias por su compra!</p>
            <!-- <p style="color:#000000">{{ ns()->option->get( 'ns_invoice_receipt_footer', 'Esta factura es un documento válido para efectos fiscales' ) }}</p> -->
        </div>

    </div>
</div>
@includeWhen( request()->query( 'autoprint' ) === 'true', '/pages/dashboard/orders/templates/_autoprint' )