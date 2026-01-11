<template>
    <div id="report-section" class="px-4">
        <div class="flex -mx-2">
            <div class="px-2">
                <div class="ns-button">
                    <button @click="loadReport()" class="rounded flex justify-between shadow py-1 items-center px-2">
                        <i class="las la-sync-alt text-xl"></i>
                        <span class="pl-2">{{ __( 'Load' ) }}</span>
                    </button>
                </div>
            </div>
            <div class="px-2">
                <div class="ns-button">
                    <button @click="downloadExcel()" class="rounded flex justify-between shadow py-1 items-center px-2">
                        <i class="las la-file-excel text-xl"></i>
                        <span class="pl-2">Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <div id="products-inventory-report" class="anim-duration-500 fade-in-entrance">
            <div class="flex w-full">
                <div class="my-4 flex justify-between w-full">
                    <div class="text-primary">
                        <ul>
                            <li class="pb-1 border-b border-dashed">{{ __( 'Document : Products Inventory' ) }}</li>
                            <li class="pb-1 border-b border-dashed">{{ __( 'By : {user}' ).replace( '{user}', ns.user.username ) }}</li>
                        </ul>
                    </div>
                    <div>
                        <img class="w-24" :src="storeLogo" :alt="storeName">
                    </div>
                </div>
            </div>

            <div class="text-primary shadow rounded my-4">
                <div class="ns-box">
                    <div class="ns-box-body">
                        <table class="table ns-table w-full">
                            <thead>
                                <tr>
                                    <th class="border p-2 text-left">Producto</th>
                                    <th width="150" class="border p-2 text-right">Existencia</th>
                                    <th width="150" class="border p-2 text-right">Costo Unidad</th>
                                    <th width="180" class="border p-2 text-right">Total Costo</th>
                                    <th width="150" class="border p-2 text-right">Venta Unidad</th>
                                    <th width="180" class="border p-2 text-right">Total Venta</th>
                                    <th width="150" class="border p-2 text-right">% Utilidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="rows.length === 0">
                                    <td colspan="7" class="p-2 border text-center">
                                        <span>{{ __( 'There is no product to display...' ) }}</span>
                                    </td>
                                </tr>
                                <tr :key="index" v-for="(row, index) of rows" class="text-sm">
                                    <td class="p-2 border">{{ row.name }}</td>
                                    <td class="p-2 border text-right">{{ row.quantity }}</td>
                                    <td class="p-2 border text-right">{{ row.cost_unit }}</td>
                                    <td class="p-2 border text-right">{{ row.total_purchase }}</td>
                                    <td class="p-2 border text-right">{{ row.sale_unit }}</td>
                                    <td class="p-2 border text-right">{{ row.total_sale }}</td>
                                    <td class="p-2 border text-right">{{ row.profit_percent }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="font-semibold" v-if="rows.length > 0">
                                <tr>
                                    <td class="p-2 border">Total productos: {{ totals.total_products || 0 }}</td>
                                    <td class="p-2 border"></td>
                                    <td class="p-2 border"></td>
                                    <td class="p-2 border text-right">{{ totals.total_purchase || 0 }}</td>
                                    <td class="p-2 border"></td>
                                    <td class="p-2 border text-right">{{ totals.total_sale || 0 }}</td>
                                    <td class="p-2 border text-right"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';
import * as XLSX from 'xlsx';

export default {
    name: 'ns-products-inventory-report',
    props: [ 'storeLogo', 'storeName' ],
    data() {
        return {
            ns: window.ns,
            rows: [],
            totals: {},
        }
    },
    mounted() {
        this.loadReport();
    },
    methods: {
        __,
        loadReport() {
            nsHttpClient.post( '/api/reports/products-inventory', {} ).subscribe({
                next: (result) => {
                    this.rows = result?.data || [];
                    this.totals = result?.totals || {};
                },
                error: (error) => {
                    nsSnackBar.error( error.message ).subscribe();
                }
            })
        },
        downloadExcel() {
            const header = [
                'Producto',
                'Existencia',
                'CostoUnidad',
                'TotalCosto',
                'VentaUnidad',
                'TotalVenta',
                'PorcentajeUtilidad'
            ];

            const data = this.rows.map( row => ([
                row.name,
                row.quantity,
                row.cost_unit,
                row.total_purchase,
                row.sale_unit,
                row.total_sale,
                row.profit_percent,
            ]));

            const totalsRow = [
                `TOTAL_PRODUCTOS_${this.totals?.total_products || 0}`,
                '',
                '',
                this.totals?.total_purchase || 0,
                '',
                this.totals?.total_sale || 0,
                '',
            ];

            const sheet = XLSX.utils.aoa_to_sheet([ header, ...data, totalsRow ]);
            const book = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet( book, sheet, 'Productos' );

            XLSX.writeFile( book, 'informe_productos.xlsx' );
        }
    }
}
</script>


