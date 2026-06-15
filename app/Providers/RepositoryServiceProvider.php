<?php

namespace App\Providers;

use App\Repositories\Contracts\AppointmentReminderRepositoryInterface;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\LeadImportRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\LeadSourceRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AppointmentReminderRepository;
use App\Repositories\Eloquent\AppointmentRepository;
use App\Repositories\Eloquent\DashboardRepository;
use App\Repositories\Eloquent\LeadImportRepository;
use App\Repositories\Eloquent\LeadRepository;
use App\Repositories\Eloquent\LeadSourceRepository;
use App\Repositories\Eloquent\ReportRepository;
use App\Repositories\Eloquent\TeamRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository interface => Eloquent implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    protected array $repositoryBindings = [
        UserRepositoryInterface::class => UserRepository::class,
        LeadRepositoryInterface::class => LeadRepository::class,
        AppointmentRepositoryInterface::class => AppointmentRepository::class,
        TeamRepositoryInterface::class => TeamRepository::class,
        DashboardRepositoryInterface::class => DashboardRepository::class,
        ReportRepositoryInterface::class => ReportRepository::class,
        LeadSourceRepositoryInterface::class => LeadSourceRepository::class,
        AppointmentReminderRepositoryInterface::class => AppointmentReminderRepository::class,
        LeadImportRepositoryInterface::class => LeadImportRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositoryBindings as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }
}
