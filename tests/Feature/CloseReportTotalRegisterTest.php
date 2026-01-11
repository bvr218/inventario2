<?php

namespace Tests\Feature;

use App\Models\Register;
use App\Models\RegisterHistory;
use App\Models\Role;
use App\Services\ReportService;
use Carbon\Carbon;
use Tests\TestCase;

class CloseReportTotalRegisterTest extends TestCase
{
    public function test_close_report_total_register_uses_register_history_and_includes_same_day_cashout(): void
    {
        $admin = Role::namespace( 'admin' )->users->first();

        $this->assertNotNull( $admin, 'Admin user is required for this test.' );

        $register = new Register;
        $register->name = 'Test Close Report Register ' . uniqid();
        $register->status = Register::STATUS_CLOSED;
        $register->description = 'test';
        $register->used_by = null;
        $register->author = $admin->id;
        $register->balance = 0;
        $register->save();

        $start = Carbon::parse( '2025-12-27 00:00:00' );
        $end = Carbon::parse( '2025-12-27 23:59:59' );

        /**
         * Previous close (starting balance for the day).
         */
        $lastClose = new RegisterHistory;
        $lastClose->register_id = $register->id;
        $lastClose->action = RegisterHistory::ACTION_CLOSING;
        $lastClose->author = $admin->id;
        $lastClose->value = 0;
        $lastClose->balance_before = 1000;
        $lastClose->balance_after = 1000;
        $lastClose->transaction_type = 'negative';
        $lastClose->created_at = ( clone $start )->subDay()->setTime( 23, 0, 0 );
        $lastClose->updated_at = $lastClose->created_at;
        $lastClose->save();

        /**
         * Same-day sales payment: +200
         */
        $payment = new RegisterHistory;
        $payment->register_id = $register->id;
        $payment->action = RegisterHistory::ACTION_ORDER_PAYMENT;
        $payment->author = $admin->id;
        $payment->value = 200;
        $payment->balance_before = 1000;
        $payment->balance_after = 1200;
        $payment->created_at = ( clone $start )->setTime( 10, 0, 0 );
        $payment->updated_at = $payment->created_at;
        $payment->save();

        /**
         * Same-day change: -50
         */
        $change = new RegisterHistory;
        $change->register_id = $register->id;
        $change->action = RegisterHistory::ACTION_ORDER_CHANGE;
        $change->author = $admin->id;
        $change->value = 50;
        $change->balance_before = 1200;
        $change->balance_after = 1150;
        $change->created_at = ( clone $start )->setTime( 10, 0, 1 );
        $change->updated_at = $change->created_at;
        $change->save();

        /**
         * Same-day refund cash-out: -10 (must reduce totalRegister).
         */
        $refundCashOut = new RegisterHistory;
        $refundCashOut->register_id = $register->id;
        $refundCashOut->action = RegisterHistory::ACTION_CASHOUT;
        $refundCashOut->author = $admin->id;
        $refundCashOut->value = 10;
        $refundCashOut->balance_before = 1150;
        $refundCashOut->balance_after = 1140;
        $refundCashOut->description = 'devolución test';
        $refundCashOut->created_at = ( clone $start )->setTime( 11, 0, 0 );
        $refundCashOut->updated_at = $refundCashOut->created_at;
        $refundCashOut->save();

        /**
         * Act: compute close report summary for this register and date range.
         */
        $service = app()->make( ReportService::class );
        $report = $service->getCloseReport(
            $start->toDateTimeString(),
            $end->toDateTimeString(),
            'categories_report',
            $register->id,
            []
        );

        $this->assertIsArray( $report );
        $this->assertArrayHasKey( 'summary', $report );
        $this->assertArrayHasKey( 'totalRegister', $report['summary'] );

        /**
         * Expected: 1000 + 200 - 50 - 10 = 1140
         */
        $this->assertEquals( 1140, (int) $report['summary']['totalRegister'] );
    }
}


