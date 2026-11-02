<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Core\Module;
use App\Core\Request;
use App\Core\Router;

/** The home page. Replace the cards in views/index.php with what your app needs people to see first. */
final class DashboardModule extends Module
{
    public function routes(Router $router): void
    {
        $router->get('/', [$this, 'index']);
    }

    public function nav(): array
    {
        return [['label' => 'Dashboard', 'path' => '/', 'icon' => 'home', 'order' => 10]];
    }

    public function index(Request $req): string
    {
        $db = $this->app->db;
        $isAdmin = $this->app->auth->isAdmin();

        // Numbers for the stat cards. Administrators see how many people use the app.
        $stats = [];
        if ($isAdmin) {
            $stats['users'] = (int) $db->value('SELECT COUNT(*) FROM users WHERE disabled = 0');
        }

        return $this->render('index', [
            'title'   => 'Dashboard',
            'stats'   => $stats,
            'isAdmin' => $isAdmin,
        ]);
    }
}
