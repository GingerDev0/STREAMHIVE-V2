<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class ProfileController
{
    public function index(): string
    {
        return View::render('pages/profile', [
            'title' => 'My Profile',
            'metaDescription' => 'View your local bookmarks and recently viewed movies and TV shows.',
            'ogTitle' => 'My Profile | StreamHIVE',
            'ogDescription' => 'Your local StreamHIVE profile with bookmarks and recently viewed items.',
            'canonicalUrl' => absolute_url('profile'),
            'robots' => 'noindex, follow',
        ]);
    }
}
