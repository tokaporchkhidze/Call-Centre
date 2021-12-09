<?php

namespace App\Listeners;

use App\Events\ExcelCreated;
use App\Template;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendExcelNotifications
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
     * @param  ExcelCreated  $event
     * @return void
     */
    public function handle(ExcelCreated $event)
    {
        $users = Template::getUsersByTemplateNames(['Manager', 'admins', 'teamLead', 'mainAdmin']);
        Notification::send($users, new \App\Notifications\ExcelCreated(sprintf("Report %s ordered by %s has been generated!",
            substr($event->absoluteFilePath, strrpos($event->absoluteFilePath, "/")+1),
            $event->user->username)));
    }

}
