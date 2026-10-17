<?php

namespace App\Domain\Auth\Enums;

enum Permission: string
{
    case StoreManage = 'store.manage';
    case DeviceManage = 'device.manage';
    case StaffManage = 'staff.manage';
    case IntegrationCredentials = 'integration.credentials';
    case CatalogView = 'catalog.view';
    case CatalogManage = 'catalog.manage';
    case PricingPublish = 'pricing.publish';
    case CustomerManage = 'customer.manage';
    case CustomerExport = 'customer.export';
    case SaleCreate = 'sale.create';
    case SaleView = 'sale.view';
    case ReturnCreate = 'return.create';
    case DiscountOverride = 'discount.override';
    case DrawerNoSale = 'drawer.no_sale';
    case CashOperate = 'cash.operate';
    case CashManage = 'cash.manage';
    case CashVarianceApprove = 'cash.variance.approve';
    case InventoryView = 'inventory.view';
    case InventoryManage = 'inventory.manage';
    case InventoryAdjustApprove = 'inventory.adjust.approve';
    case PurchasingManage = 'purchasing.manage';
    case LoyaltyManage = 'loyalty.manage';
    case LoyaltyAdjust = 'loyalty.adjust';
    case AttendanceOwn = 'attendance.own';
    case AttendanceManage = 'attendance.manage';
    case PayrollOwn = 'payroll.own';
    case PayrollView = 'payroll.view';
    case PayrollManage = 'payroll.manage';
    case ExpenseSubmit = 'expense.submit';
    case ExpenseApprove = 'expense.approve';
    case ReportStore = 'report.store';
    case ReportConsolidated = 'report.consolidated';
    case ReportCosts = 'report.costs';
    case ReportFinancial = 'report.financial';
    case AuditView = 'audit.view';
    case ApprovalIssue = 'approval.issue';
}
