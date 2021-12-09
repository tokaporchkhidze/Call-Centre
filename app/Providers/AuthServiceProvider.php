<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
        'App\User' => 'App\Policies\UserPolicy',
        'App\Template' => 'App\Policies\TemplatePolicy',
        'App\Sip' => 'App\Policies\SipPolicy',
        'App\Queue' => 'App\Policies\QueuePolicy',
        'App\Operator' => 'App\Policies\OperatorPolicy',
        'App\Audio' => 'App\Policies\AudioPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        $this->registerCRRGates();

        $this->registerB2BMailGates();

        $this->registerBlackListGates();

        Passport::routes();

        Passport::tokensExpireIn(now()->addMinute(12000));
    }

    private function registerCRRGates() {
        Gate::define('createCRRReason', 'App\Policies\CRRPolicy@createAndDelete');
        Gate::define('editCRRReason', 'App\Policies\CRRPolicy@createAndDelete');
        Gate::define('reactivateCRRReason', 'App\Policies\CRRPolicy@createAndDelete');
        Gate::define('deleteCRRReason', 'App\Policies\CRRPolicy@createAndDelete');
        Gate::define('addCRRSuggestion', 'App\Policies\CRRPolicy@createAndDelete');
        Gate::define('editCRRSuggestion', 'App\Policies\CRRPolicy@createAndDelete');
        Gate::define('reactivateCRRSuggestion', 'App\Policies\CRRPolicy@createAndDelete');
        Gate::define('deleteCRRSuggestion', 'App\Policies\CRRPolicy@createAndDelete');
        Gate::define('searchCRREntry', 'App\Policies\CRRPolicy@view');
    }

    private function registerB2BMailGates() {
        Gate::define('insertB2BMail', 'App\Policies\B2BMailPolicy@createAndDelete');
        Gate::define('updateB2BMail', 'App\Policies\B2BMailPolicy@createAndDelete');
        Gate::define('addB2BMailReason', 'App\Policies\B2BMailReasonPolicy@createAndDelete');
        Gate::define('deleteB2BMailReason', 'App\Policies\B2BMailReasonPolicy@createAndDelete');
        Gate::define('reactivateB2BMailReason', 'App\Policies\B2BMailReasonPolicy@createAndDelete');
    }

    private function registerBlackListGates() {
        Gate::define('addNumberInBlackList', 'App\Policies\BlackListPolicy@createAndDelete');
        Gate::define('removeNumberFromBlackList', 'App\Policies\BlackListPolicy@createAndDelete');
        Gate::define('getNumbersFromBlackList', 'App\Policies\BlackListPolicy@view');
        Gate::define('getBlackListHistory', 'App\Policies\BlackListPolicy@view');
    }

}
