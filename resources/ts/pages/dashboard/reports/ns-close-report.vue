<template>
    <div id="report-section" class="px-4">
        <div class="flex -mx-2">
            <div class="px-2">
                <ns-date-time-picker :field="startDateField"></ns-date-time-picker>
            </div>
            <div class="px-2">
                <ns-date-time-picker :field="endDateField"></ns-date-time-picker>
            </div>
            <div class="px-2">
                <button @click="loadReport()" class="rounded flex justify-between bg-input-button shadow py-1 items-center text-primary px-2">
                    <i :class="isLoading ? 'animate-spin' : ''" class="las la-sync-alt text-xl"></i>
                    <span class="pl-2">{{ __( 'Load' ) }}</span>
                </button>
            </div>
        </div>
        <div class="flex -mx-2">
            <div class="px-2">
                <button @click="printCloseReport()" class="rounded flex justify-between bg-input-button shadow py-1 items-center text-primary px-2">
                    <i class="las la-print text-xl"></i>
                    <span class="pl-2">{{ __( 'Print' ) }}</span>
                </button>
            </div>
            <div class="px-2">
                <button @click="openUserFiltering()" class="rounded flex justify-between bg-input-button shadow py-1 items-center text-primary px-2">
                    <i class="las la-filter text-xl"></i>
                    <span class="pl-2">{{ __( 'Por Caja' ) }} : {{ selectedUser  }}</span>
                </button>
            </div>
        </div>
        <div id="close-report" class="anim-duration-500 fade-in-entrance">
            <div class="flex w-full">
                <div class="my-4 flex justify-between w-full">
                    <div class="text-secondary">
                        <ul>
                            <li class="pb-1 border-b border-dashed" v-html="__( 'Range : {date1} &mdash; {date2}' ).replace( '{date1}', startDateField.value ).replace( '{date2}', endDateField.value )"></li>
                            <li class="pb-1 border-b border-dashed">{{ __( 'Document : Close Report' ) }}</li>
                            <li class="pb-1 border-b border-dashed">{{ __( 'By : {user}' ).replace( '{user}', ns.user.username ) }}</li>
                        </ul>
                    </div>
                    <div>
                        <img class="w-24" :src="storeLogo" :alt="storeName">
                    </div>
                </div>
            </div>
            <div>
                <div class="-mx-4 flex md:flex-row flex-col">
                    <div class="w-full md:w-1/2 px-4">
                        <div class="shadow rounded my-4 ns-box">
                            <div class="border-b ns-box-body">
                                <table class="table ns-table w-full">
                                    <tbody class="text-primary">
                                        <tr class="">
                                            <td width="200" class="font-semibold p-2 border text-left bg-success-secondary border-success-primary text-white">{{ 'Cierre anterior' }}</td>
                                            <td class="p-2 border text-right border-success-primary">{{ nsCurrency( summary.lastClose ) }}</td>
                                        </tr>
                                        <tr class="">
                                            <td width="200" class="font-semibold p-2 border text-left bg-success-secondary border-success-primary text-white">{{ 'Venta total' }}</td>
                                            <td class="p-2 border text-right border-success-primary">{{ nsCurrency( summary.totalSale ) }}</td>
                                        </tr>
                                        <tr class="">
                                            <td width="200" class="font-semibold p-2 border text-left bg-error-secondary border-error-primary text-white">{{ __( 'Sales Discounts' ) }}</td>
                                            <td class="p-2 border text-right border-error-primary">{{ nsCurrency( summary.sales_discounts ) }}</td>
                                        </tr>
                                        <tr class="">
                                            <td width="200" class="font-semibold p-2 border text-left bg-error-secondary border-error-primary text-white">{{ 'Devoluciones' }}</td>
                                            <td class="p-2 border text-right border-error-primary">{{ nsCurrency( summary.refund ) }}</td>
                                        </tr>

                                        <tr class="">
                                            <td width="200" class="font-semibold p-2 border text-left bg-error-secondary border-error-secondary text-white">{{ 'Nota debito' }}</td>
                                            <td class="p-2 border text-right border-error-primary">{{ nsCurrency( summary.cashOut ) }}</td>
                                        </tr>
                                        <tr class="">
                                            <td width="200" class="font-semibold p-2 border text-left bg-success-secondary border-success-secondary text-white">{{ __( 'Nota credito' ) }}</td>
                                            <td class="p-2 border text-right border-success-primary">{{ nsCurrency( summary.cashIn ) }}</td>
                                        </tr>
                                         <tr class="">
                                            <td width="200" class="font-semibold p-2 border text-left bg-info-secondary border-info-secondary text-white">{{ __( 'Total en caja' ) }}</td>
                                            <td class="p-2 border text-right border-info-primary">{{ nsCurrency( summary.totalRegister ) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="w-full md:w-1/2 px-4">
                    </div>
                </div>
            </div>
           //reports table
        </div>
    </div>
</template>
<script>
import moment from "moment";
import nsDatepicker from "~/components/ns-datepicker.vue";
import { default as nsDateTimePicker } from '~/components/ns-date-time-picker.vue';
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';
import nsSelectPopupVue from '~/popups/ns-select-popup.vue';
import { nsCurrency } from '~/filters/currency';
import { joinArray } from "~/libraries/join-array";

export default {
    name: 'ns-close-report',
    data() {
        return {
            closeReport: '',
            startDateField: {
                name: 'start_date',
                type: 'datetime',
                value: ns.date.moment.startOf( 'day' ).format()
            },
            endDateField: {
                name: 'end_date',
                type: 'datetime',
                value: ns.date.moment.endOf( 'day' ).format()
            },
            result: [],
            isLoading: false,
            users: [],
            ns: window.ns,
            summary: {},
            selectedUser: '',
            selectedCategory: '',
            reportType: {
                label: __( 'Report Type' ),
                name: 'reportType',
                type: 'select',
                value: 'categories_report',
                options: [
                    {
                        label: __( 'Categories Detailed' ),
                        value: 'categories_report',
                    }, {
                        label: __( 'Categories Summary' ),
                        value: 'categories_summary',
                    }, {
                        label: __( 'Products' ),
                        value: 'products_report',
                    }
                ],
                description: __( 'Allow you to choose the report type.' ),
            },
            filterUser: {
                label: __( 'Filter User' ),
                name: 'filterUser',
                type: 'select',
                required: 'required',
                
                value: '',
                options: [
                    // ...
                ],
                description: __( 'Allow you to choose the report type.' ),
            },
            filterCategory: {
                label: __( 'Filter By Category' ),
                name: 'filterCategory',
                type: 'multiselect',
                value: '',
                options: [
                    // ...
                ],
                description: __( 'Allow you to choose the category.' ),
            },
            field: {
                type: 'datetimepicker',
                value: '2021-02-07',
                name: 'date'
            }
        }
    },
    components: {
        nsDatepicker,
        nsDateTimePicker,
    },
    computed: {
        // ...
    },
    methods: {
        __,
        nsCurrency,
        joinArray,
        printCloseReport() {
            this.$htmlToPaper( 'close-report' );
        },

        async openSettings() {
            try {
                const result    =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsSelectPopupVue, {
                        ...this.reportType,
                        resolve, 
                        reject
                    });
                });

                this.reportType.value   =   result;
                this.result             =   [];
                this.loadReport();
            } catch( exception ) {
                console.log({ exception })
            }
        },

        async openUserFiltering() {
            try {
                /**
                 * let's try to pull the users first.
                 */
                this.isLoading  =   true;
                const result    =   await new Promise( ( resolve, reject ) => {
                    nsHttpClient.get( `/api/cash-registers` )
                        .subscribe({
                            next: (registers) => {
                                this.users      =   registers;
                                this.isLoading  =   false;

                                this.filterUser.options     =   [
                                   
                                    ...this.users.map( user => {
                                        return {
                                            label: user.name,
                                            value: user.id
                                        }
                                    })
                                ];

                                Popup.show( nsSelectPopupVue, {
                                    ...this.filterUser,
                                    resolve, 
                                    reject
                                });
                            },
                            error: error => {
                                this.isLoading  =   false;
                                nsSnackBar.error( __( 'No user was found for proceeding the filtering.' ) );
                                reject( error );
                            }
                        });
                });  
                
                const searchUser  =   this.users.filter( __user => __user.id === result );

                if ( searchUser.length > 0 ) {
                    let user    =   searchUser[0];
                    this.selectedUser       =   `${user.name} ${(user.description) ? '('+user.description+')' : '' }`;
                    this.filterUser.value   =   result;
                    this.result             =   [];
                    this.loadReport();
                }
            } catch( exception ) {
                console.log({ exception });
            }
        },

        async openCategoryFiltering() {
            try {
                let categories  =   [];

                this.isLoading  =   true;
                const result    =   await new Promise( ( resolve, reject ) => {
                    nsHttpClient.get( `/api/categories` )
                        .subscribe({
                            next: (retreivedCategories) => {
                                this.isLoading  =   false;
                                categories  =   retreivedCategories;
                                this.filterCategory.options     =   [
                                    ...retreivedCategories.map( category => {
                                        return {
                                            label: category.name,
                                            value: category.id
                                        }
                                    })
                                ];

                                Popup.show( nsSelectPopupVue, {
                                    ...this.filterCategory,
                                    resolve, 
                                    reject
                                });
                            },
                            error: error => {
                                this.isLoading  =   false;
                                nsSnackBar.error( __( 'No category was found for proceeding the filtering.' ) );
                                reject( error );
                            }
                        });
                });

                if ( result.length > 0 ) {
                    let categoryNames   =   categories
                        .filter( category => result.includes( category.id ) )
                        .map( category => category.name );

                    this.selectedCategory       =   this.joinArray( categoryNames );
                    this.filterCategory.value   =   result;
                } else {
                    this.selectedCategory       =   '';
                    this.filterCategory.value   =   [];
                }

                this.result             =   [];
                this.loadReport();

            } catch( exception ) {
                // ...
                console.log( exception );
            }
        },

        getType( type ) {
            const option    =   this.reportType.options.filter( option => {
                return option.value === type;
            });

            if ( option.length > 0 ) {
                return option[0].label;
            }

            return __( 'Unknown' );
        },        

        loadReport() {
            if ( this.startDate === null || this.endDate ===null ) {
                return nsSnackBar.error( __( 'Unable to proceed. Select a correct time range.' ) ).subscribe();
            }
            console.log(this.filterUser.value);
            if ( this.filterUser.value == null || this.filterUser.value == "" ) {
                return nsSnackBar.error( __( 'Seleccione una caja primero.' ) ).subscribe();
            }

            const startMoment   =   moment( this.startDate );
            const endMoment     =   moment( this.endDate );

            if ( endMoment.isBefore( startMoment ) ) {
                return nsSnackBar.error( __( 'Unable to proceed. The current time range is not valid.' ) ).subscribe();
            }

            this.isLoading  =   true;
            nsHttpClient.post( '/api/reports/close-report', { 
                startDate: this.startDateField.value,
                endDate: this.endDateField.value,
                type: this.reportType.value,
                user_id: this.filterUser.value,
                categories_id: this.filterCategory.value
            }).subscribe({
                next: response => {
                    this.isLoading  =   false;
                    this.result     =   response.result;
                    this.summary    =   response.summary;
                }, 
                error : ( error ) => {
                    this.isLoading  =   false;
                    nsSnackBar.error( error.message ).subscribe();
                }
            });
        },

        computeTotal( collection, attribute ) {
            if ( collection.length > 0 ) {
                return collection.map( entry => parseFloat( entry[ attribute ] ) )
                    .reduce( ( b, a ) => b + a );
            }

            return 0;
        },
    },
    props: [ 'storeLogo', 'storeName' ],
    mounted() {
        // ...
    }
}
</script>