<?php

namespace App\Traits;

use App\Models\User;
use App\Notifications\FcmNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use stdClass;

use function PHPUnit\Framework\isNull;

trait NotificationBuilder
{
    /**
     * @param mixed $notification
     * @return void
     */
    public function storeNotification($users, $notification)
    {
        foreach ($users as $user) {
            $user->notifications()->create([
                'title' => $notification->storeTitle,
                'body' => $notification->storeBody,
                'type' => $notification->type,
                'data' => $notification->data ?? null,
                'img' => $notification->img ?? null
            ]);
        }
    }

    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function notifyUser($user, $data = null)
    {
        $notification = new stdClass();
        $notification->title = $data['title'] ?? 'title';
        $notification->body = $data['body'] ?? 'body';
        $notification->storeTitle = 'title';
        $notification->storeBody = 'body';
        $notification->id = $data['id'] ?? null;
        $notification->type = $data['type'] ?? 'type';
        $notification->data = $data;

        // $this->storeNotification([$user], $notification);

        $user->notify(new FcmNotification($notification));
    }

    /**
     * @param array|collection
     * @param mixed $data
     * @return void
     */
    public function notifyUsers($users, $data = null)
    {
        $notification = new stdClass();
        $notification->title = $data['title'] ?? 'title';
        $notification->body = $data['body'] ?? 'body';
        $notification->storeTitle = 'title';
        $notification->storeBody = 'body';
        $notification->id = $data['id'] ?? null;
        $notification->type = $data['type'] ?? 'type';
        $notification->data = $data;

        $this->storeNotification($users, $notification);

        Notification::send(collect($users), new FcmNotification($notification));
    }

    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function profileFinishedNotification($user)
    {
        $notification = new stdClass();
        $notification->title = 'authentication.profile_completed';
        $notification->body =  'authentication.profile_completed_desc';
        $notification->storeTitle = 'authentication.profile_completed';
        $notification->storeBody = 'authentication.profile_completed_desc';
        $notification->id = $user->id ?? null;
        $notification->type = 'profile';

        $this->storeNotification([$user], $notification);

        $user->notify(new FcmNotification($notification));
    }

    /**
     * @param array|collection
     * @param mixed $data
     * @return void
     */
    public function usersNotification($users, $data = null)
    {
        $notification = new stdClass();
        $notification->title = $data['title'] ?? 'title';
        $notification->body = $data['body'] ?? 'body';
        $notification->storeTitle = 'title';
        $notification->storeBody = 'body';
        $notification->id = $data['id'] ?? null;
        $notification->type = $data['type'] ?? 'type';
        $notification->data = $data;

        $this->storeNotificationForUsers($users, $notification);

        Notification::send(collect($users), new FcmNotification($notification));
    }

    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function balanceNotification($user, $data = null)
    {
        $notification = new stdClass();
        $notification->title = 'dashboard.balance';
        $notification->body =  'dashboard.balance_desc';
        $notification->storeTitle = 'dashboard.balance';
        $notification->storeBody = 'dashboard.balance_desc';
        $notification->id = $data['id'] ?? null;
        $notification->type = 'hide_balance';
        $notification->data = $data;
        $notification->img = "https://u-smile.fra1.digitaloceanspaces.com/u_app_assets/balance.png";

        // $this->storeNotification([$user], $notification);

        $user->notify(new FcmNotification($notification));
    }

    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function levelUpgradedNotification($user)
    {
        $userLang = $user->profile->preferences['language'];

        if (Str::lower($userLang === 'fr')) {
            $title = "Niveau terminé";
            $body = "Vous avez cloturé un niveau entier, un truc de ouf!";

        } else if (Str::lower($userLang === 'en')) {
            $title = "level finished";
            $body = "You've closed an entire level, amazing!";

        } else if (Str::lower($userLang === 'nl')) {
            $title = "niveau afgewerkt";
            $body = "Je hebt een heel level voltooid, dat is echt iets!";
        }

        $notification = new stdClass();
        $notification->title = $title ?? 'dashboard.level_finished';
        $notification->body = $body ?? 'dashboard.level_finished_desc';
        $notification->storeTitle = 'dashboard.level_finished';
        $notification->storeBody = 'dashboard.level_finished_desc';
        $notification->id = $user->profile->level ?? null;
        $notification->type = 'level';
        $notification->data = $user->toArray();
        $notification->img = "https://u-smile.fra1.digitaloceanspaces.com/u_app_assets/lvl.png";

        $this->storeNotification([$user], $notification);

        $user->notify(new FcmNotification($notification));
    }
    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function questFinishedNotification($user, $data = null)
    {
        $userLang = $user->profile->preferences['language'];

        if (Str::lower($userLang === 'fr')) {
            $title = "Youpiii, vous avez terminé une quête";
            $body = "Vous êtes un champion, nos quêtes ne sont pas façiles";

        } else if (Str::lower($userLang === 'en')) {
            $title = "Yippee, you've completed a quest!";
            $body = "You're a champion, our quests are not easy";

        } else if (Str::lower($userLang === 'nl')) {
            $title = "Jippie, je hebt een queeste voltooid";
            $body = "Je bent een kampioen, onze zoektochten zijn niet gemakkelijk";
        }

        $notification = new stdClass();
        $notification->title = $title ?? 'dashboard.quest_finished';
        $notification->body = $body ?? 'dashboard.quest_finished_desc';
        $notification->storeTitle = 'dashboard.quest_finished';
        $notification->storeBody = 'dashboard.quest_finished_desc';
        $notification->id = $data['id'] ?? null;
        $notification->type = 'quest';
        $notification->data = $data;
        $notification->img = "https://u-smile.fra1.digitaloceanspaces.com/u_app_assets/quest.png";

        $this->storeNotification([$user], $notification);

        $user->notify(new FcmNotification($notification));
    }
    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function leadOptinNotification($user, $data = null)
    {
        $userLang = $user->profile->preferences['language'];

        if (Str::lower($userLang === 'fr')) {
            $title = "Un vrai de vrai";
            $body = "Vous avez parrainé un proche et il a accepté, vous êtes un vrai champion!";

        } else if (Str::lower($userLang === 'en')) {
            $title = "A Real One";
            $body = "You've sponsored a friend and they've accepted - you're a real champion!";

        } else if (Str::lower($userLang === 'nl')) {
            $title = "Een echte";
            $body = "Je hebt iemand gesponsord die dicht bij je staat en hij of zij heeft het geaccepteerd - je bent een echte kampioen!";
        }

        $notification = new stdClass();
        $notification->title = $title ?? 'dashboard.lead_optin';
        $notification->body =  $body ?? 'dashboard.lead_optin_desc';
        $notification->storeTitle = 'dashboard.lead_optin';
        $notification->storeBody = 'dashboard.lead_optin_desc';
        $notification->id = $data->id ?? null;
        $notification->type = 'lead_approved';
        $notification->data = $data;
        $notification->img = "https://u-smile.fra1.digitaloceanspaces.com/u_app_assets/lead.png";

        $this->storeNotification([$user], $notification);

        $user->notify(new FcmNotification($notification));
    }
    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function activeBalanceCreditNotification($user, $data = null)
    {
        $userLang = $user->profile->preferences['language'];

        if (Str::lower($userLang === 'fr')) {
            $title = "{{value}} U-Coins débloqués";
            $body = "Vos U-Coins sont disponibles sur votre balance active";

        } else if (Str::lower($userLang === 'en')) {
            $title = "{{value}} U-Coins unlocked";
            $body = "You've sponsored a friend and they've accepted - you're a real champion!";

        } else if (Str::lower($userLang === 'nl')) {
            $title = "{{value}} U-Coins ontgrendeld";
            $body = "Your U-Coins are available on your active account";
        }

        $notification = new stdClass();
        $notification->title = $title ?? 'dashboard.active_balance_credit_success';
        $notification->body = $body ?? 'dashboard.active_balance_credit_success_desc';
        $notification->storeTitle = 'dashboard.active_balance_credit_success';
        $notification->storeBody = 'dashboard.active_balance_credit_success_desc';
        $notification->id = $data->id ?? null;
        $notification->type = 'active_balance_credit';
        $notification->data = $data;
        $notification->img = "https://u-smile.fra1.digitaloceanspaces.com/u_app_assets/balance.png";

        $this->storeNotification([$user], $notification);

        $user->notify(new FcmNotification($notification));
    }

    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function productExchangeNotification($user, $data = null)
    {
        $userLang = $user->profile->preferences['language'];

        if (Str::lower($userLang === 'fr')) {
            $title = "L'échange de produit s'est établi avec succès";
            $body = "";

        } else if (Str::lower($userLang === 'en')) {
            $title = "the exchange of product has been successfully established";
            $body = "";

        } else if (Str::lower($userLang === 'nl')) {
            $title = "de uitwisseling van producten is met succes tot stand gebracht";
            $body = "";
        }

        $notification = new stdClass();
        $notification->title = $title ?? 'dashboard.product_exchange_success';
        $notification->body = $body ?? 'dashboard.product_exchange_success_desc';
        $notification->storeTitle = 'dashboard.product_exchange_success';
        $notification->storeBody = 'dashboard.product_exchange_success_desc';
        $notification->id = $data->id ?? null;
        $notification->type = 'product_exchange';
        $notification->data = $data;

        $this->storeNotification([$user], $notification);

        $user->notify(new FcmNotification($notification));
    }

    /**
     * @param User $user
     * @param mixed $data
     * @return void
     */
    public function newProductAvailableNotification($users, $data = null)
    {
        foreach ($users as $user)
        {
            $userLang = $user->profile->preferences['language'];
            if (isNull($userLang)) {
                $userLang = 'en';
            }

            if (Str::lower($userLang === 'fr')) {
                $title = "Nouveau produit disponible";
                $body = "";

            } else if (Str::lower($userLang === 'en')) {
                $title = "new product available";
                $body = "";

            } else if (Str::lower($userLang === 'nl')) {
                $title = "nieuw product beschikbaar";
                $body = "";
            }

            $notification = new stdClass();
            $notification->title = $title ?? 'dashboard.new_product_available';
            $notification->body = $body ?? 'dashboard.new_product_available_desc';
            $notification->storeTitle = 'dashboard.new_product_available';
            $notification->storeBody = 'dashboard.new_product_available_desc';
            $notification->id = $data->id ?? null;
            $notification->type = 'new_product';
            $notification->data = $data;

            $this->storeNotification([$user], $notification);

            $user->notify(new FcmNotification($notification));
        }
    }

}
