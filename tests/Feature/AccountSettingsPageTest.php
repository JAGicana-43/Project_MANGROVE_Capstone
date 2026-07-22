<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class AccountSettingsPageTest extends TestCase
{
    public function test_account_settings_view_renders_profile_and_password_form(): void
    {
        $user = new User([
            'id' => 'user-1',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
        $user->setRelation('role', new Role(['name' => 'researcher']));

        View::share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = View::make('settings.account', [
            'user' => $user,
            'backRoute' => '/researcher/lobby',
        ])->render();

        $this->assertStringContainsString('Ada Lovelace', $html);
        $this->assertStringContainsString('ada@example.com', $html);
        $this->assertStringContainsString('Account Security', $html);
        $this->assertStringContainsString('settings/account/password', $html);
        $this->assertStringContainsString('/researcher/lobby', $html);
        // Collapsed by default (no prior error/success in this render) — the
        // password form itself shouldn't be sitting open on page load.
        $this->assertStringContainsString('collapsible collapsed', $html);
    }
}
