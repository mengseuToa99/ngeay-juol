<?php

namespace App\Filament\Widgets;

use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminPlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -4;

    public static function canView(): bool
    {
        return auth()->user()?->isPlatformStaff() ?? false;
    }

    protected function getStats(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $pendingPayments = SubscriptionPayment::withoutGlobalScopes()
            ->where('status', SubscriptionPaymentStatus::Pending->value)
            ->count();

        $monthlyRevenue = (float) SubscriptionPayment::withoutGlobalScopes()
            ->where('status', SubscriptionPaymentStatus::Succeeded->value)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');

        return [
            Stat::make(__('Landlords'), User::role('landlord')->count())
                ->descriptionIcon('heroicon-o-users'),

            Stat::make(
                __('Active subscriptions'),
                Subscription::withoutGlobalScopes()
                    ->withoutTrashed()
                    ->where('status', SubscriptionStatus::Active->value)
                    ->count()
            )
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(__('Pending subscription payments'), $pendingPayments)
                ->descriptionIcon('heroicon-o-clock')
                ->color($pendingPayments > 0 ? 'warning' : 'success'),

            Stat::make(__('Monthly subscription revenue'), '$'.number_format($monthlyRevenue, 2))
                ->description(__('current month'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
