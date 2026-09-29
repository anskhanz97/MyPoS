<?php

namespace App\Domain\Cash\Enums;

enum CashMovementKind: string
{
    case OpeningFloat = 'opening_float';
    case PaidIn = 'paid_in';
    case PaidOut = 'paid_out';
    case SaleCash = 'sale_cash';
    case RefundCash = 'refund_cash';
    case SupplierPayment = 'supplier_payment';
    case PayrollPayout = 'payroll_payout';
    case Expense = 'expense';
    case Correction = 'correction';
    case NoSaleOpen = 'no_sale_open';
}
