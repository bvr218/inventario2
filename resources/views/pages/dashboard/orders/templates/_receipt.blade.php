<?php
use App\Models\Order;
use App\Classes\Hook;
use Illuminate\Support\Facades\View;

// Assuming $paymentTypes is available as in the original code context if needed elsewhere,
// but it's not directly used in the simplified payment section below.

// Format date as DD/MM/YYYY HH:MM (adjust format string if needed)
$formattedDate = $order->created_at ? \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') : 'N/A';

// Placeholder for NIT/CC - adjust based on your customer data structure
$customerIdentification = $order->customer ? ($order->customer->identification ?? 'N/A') : 'N/A';
?>
<div class="w-full h-full">
    {{-- Use a slightly wider container if needed, matching the image aspect ratio better --}}
    <div class="w-full md:w-1/2 lg:w-1/3 shadow-lg bg-white p-4 mx-auto font-sans text-sm">

        {{-- Header Section --}}
        <div class="flex justify-between items-start mb-2">
            <div>
                <h1 style="color:#000000;" class="text-xl font-bold">FACTURA</h1>
                {{-- Assuming $order->code holds the invoice number --}}
                <p style="color:#000000" class="text-xs">No. {{ $order->code ?? '00000000' }}</p>
            </div>
            {{-- Company Info - Right Aligned --}}
            <div class="text-right text-xs">
                <h1 style="color:#000000;font-size: large;" class="font-bold">{{ ns()->option->get( 'ns_store_name', 'MI EMPRESA S.A.' ) }}</h1>
                {{-- Add NIT, Address, Tel from options or order/store data --}}
                <p style="color:#000000">NIT: {{ ns()->option->get( 'ns_store_nit', '900.123.456-7' ) }}</p>
                <p style="color:#000000">{{ ns()->option->get( 'ns_store_address', 'Calle Principal #123' ) }}</p>
                <!-- <p style="color:#000000">Tel: {{ ns()->option->get( 'ns_store_phone', '(123) 456-7890' ) }}</p> -->
            </div>
        </div>

        <hr class="my-2 border-gray-300">

        {{-- Invoice Details Section --}}
        <div class="flex justify-between mb-2 text-xs">
            <div class="w-1/2 pr-1">
                <p style="color:#000000"><span class="font-semibold">Fecha:</span> {{ $formattedDate }}</p>
                {{-- Assuming $order->customer->name holds the client name --}}
                <p style="color:#000000"><span class="font-semibold">Cliente:</span> {{ ($order->customer->first_name ?? 'N/A')." ".($order->customer->last_name ?? 'N/A') }}</p>
            </div>
            <div class="w-1/2 pl-1 text-right">
                 {{-- Assuming $order->author->name holds the cashier name --}}
                <p style="color:#000000" ><span class="font-semibold">Cajero:</span> {{ ($order->user->first_name ?? 'N/A')." ".($order->user->last_name ?? 'N/A') }}</p>
                <!-- <p><span class="font-semibold">NIT/CC:</span> {{ $customerIdentification }}</p> -->
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
                    {{-- Use Hook::filter if you still need extensibility --}}
                    @foreach( Hook::filter( 'ns-receipt-products', $order->combinedProducts ) as $product )
                    <tr class="border-b border-gray-200">
                        <td class="py-1 align-top">{{ $product->quantity }}</td>
                        <td class="py-1 align-top">
                            <?php
                                // Option 1: Direct name
                                // echo e($product->name);

                                // Option 2: Using the View include from original code (if it provides more detail)
                                $productName  =   View::make( 'pages.dashboard.orders.templates._product-name', compact( 'product' ) );
                                echo Hook::filter( 'ns-receipt-product-name', $productName->render(), $product );
                            ?>
                        </td>
                         {{-- Calculate Unit Price if not directly available --}}
                        <td class="py-1 text-right align-top">{{ ns()->currency->define( $product->unit_price ?? ($product->total_price / $product->quantity) ) }}</td>
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
                <div class="w-2/5 text-right pr-2">Subtotal:</div>
                <div class="w-1/3 text-right font-medium">{{ ns()->currency->define( $order->subtotal ?? 0 ) }}</div>
            </div>
            {{-- Simplified Tax Line - Assumes $order->tax_value is the total VAT/IVA --}}
            {{-- Adjust label if needed, maybe check tax_group name or rate --}}
            @if ( $order->tax_value > 0 )
            <div class="flex justify-end">
                 {{-- Trying to replicate "IVA (19%)". This is an approximation. --}}
                 {{-- You might need specific logic if you have multiple taxes. --}}
                <div class="w-2/5 text-right pr-2">{{ $order->tax_group?->name ?? 'IVA' }} ({{-- Placeholder % --}} {{ $order->tax_group?->rate ?? '19' }}%):</div>
                <div class="w-1/3 text-right font-medium">{{ ns()->currency->define( $order->tax_value ) }}</div>
            </div>
            @endif
             {{-- Removed Discount, Coupons, Shipping from this summary block to match the image --}}
             {{-- The $order->total should already reflect these if calculated correctly upstream --}}
            <div class="flex justify-end mt-1">
                <div class="w-2/5 text-right pr-2 font-bold">TOTAL:</div>
                <div class="w-1/3 text-right font-bold text-base">{{ ns()->currency->define( $order->total ?? 0 ) }}</div> {{-- Slightly larger total --}}
            </div>
        </div>

        <hr class="my-2 border-gray-300">

        {{-- Payment Section --}}
        <div class="w-full mb-4 text-xs">
            <p style="color:#000000" class="font-semibold mb-1">Forma de pago:</p>
            {{-- Simplified: Showing Tendered as "Efectivo" and Change --}}
            <div class="flex justify-end">
                <div class="w-2/5 text-right pr-2">Efectivo:</div>
                 {{-- Using tendered amount. If you have specific cash payment, use that --}}
                <div class="w-1/3 text-right">{{ ns()->currency->define( $order->tendered ?? 0 ) }}</div>
            </div>
             {{-- Show Change only if payment is complete (or based on status) --}}
            @if($order->change > 0 && $order->payment_status === Order::PAYMENT_PAID)
            <div class="flex justify-end">
                <div class="w-2/5 text-right pr-2">Cambio:</div>
                <div class="w-1/3 text-right">{{ ns()->currency->define( $order->change ?? 0 ) }}</div>
            </div>
            @endif
            {{-- Note: The original code had more complex logic for Due amounts (PAYMENT_PARTIALLY) --}}
            {{-- and Refunded amounts. Add that back here if needed, adapting the labels. --}}
        </div>

        {{-- Footer Section --}}
        <div class="text-center text-xs text-gray-600">
            <p style="color:#000000" class="mb-1">¡Gracias por su compra!</p>
             {{-- Option 1: Use existing footer option --}}
            <!-- <p style="color:#000000" >{{ ns()->option->get( 'ns_invoice_receipt_footer', 'Esta factura es un documento válido para efectos fiscales' ) }}</p> -->
            {{-- Option 2: Hardcode if the option is used for something else --}}
            {{-- <p style="color:#000000">Esta factura es un documento válido para efectos fiscales</p> --}}
        </div>

        {{-- Optional: Add a print button if this template is viewed directly in browser --}}
        {{-- This might be handled outside the template itself --}}
        {{-- <div class="text-center mt-4">
            <button onclick="window.print()" class="px-4 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Imprimir</button>
        </div> --}}

    </div>
</div>
{{-- Autoprint logic from original code --}}
@includeWhen( request()->query( 'autoprint' ) === 'true', '/pages/dashboard/orders/templates/_autoprint' )