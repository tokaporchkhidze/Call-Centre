<?php

namespace App\Listeners;

use App\Events\ExcelNotCreated;
use App\Template;
use Illuminate\Support\Facades\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendExcelNotCreatedNotifications
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ExcelNotCreated $event)
    {
        $users = Template::getUsersByTemplateNames(['Manager', 'admins', 'teamLead', 'mainAdmin']);
        Notification::send($users, new \App\Notifications\FailedExcelJobNotification($event->payload));
    }
}
